<?php

namespace App\Livewire\Admin;

use App\Filament\Resources\Rentals\RentalResource;
use App\Filament\Resources\Rentals\Schemas\RentalForm;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\Quotation;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\RentalItemTransferService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RentalEditor extends Component
{
    public ?Rental $record = null;

    // Header / status
    public string $rental_code = 'AUTO';
    public string $status = Rental::STATUS_QUOTATION;

    // Customer + dates
    public ?int $customer_id = null;
    public ?string $start_date = null;
    public ?string $end_date = null;

    // Items: ordered array; each: composite_id, product_id, variation_id, quantity, daily_rate, discount, unit_ids[], subtotal
    public array $items = [];

    // Money
    public string $discount_type = 'fixed'; // fixed | percent
    public float $discount = 0;
    public string $deposit_type = 'fixed';
    public float $deposit = 0;
    public float $down_payment_amount = 0;
    public ?string $notes = null;

    // Search
    public string $searchTerm = '';

    // Modals
    public bool $unitModalOpen = false;
    public ?string $unitModalKey = null;
    public bool $catalogOpen = false;

    // Transfer (Move/Swap) modal
    public bool $transferModalOpen = false;
    public string $transferMode = 'move'; // 'move' | 'swap'
    public ?int $transferUnitId = null;   // serial unit being transferred (source side)
    public ?string $transferItemKey = null; // editor items[] key when opened from a row (limits unit picker)
    public ?int $transferTargetRentalId = null;
    public ?int $transferTargetItemId = null; // for swap only

    protected $listeners = [
        'qr-scanned' => 'handleScanned',
    ];

    public function mount(?Rental $record = null): void
    {
        if ($record && $record->exists) {
            $this->record = $record->load('items.productUnit');
            $this->rental_code = $record->rental_code ?? 'AUTO';
            $this->status = $record->status;
            $this->customer_id = $record->user_id;
            $this->start_date = $record->start_date?->format('Y-m-d\TH:i');
            $this->end_date = $record->end_date?->format('Y-m-d\TH:i');
            $this->discount_type = $record->discount_type ?? 'fixed';
            $this->discount = (float) ($record->discount ?? 0);
            $this->deposit_type = $record->deposit_type ?? 'fixed';
            $this->deposit = (float) ($record->deposit ?? 0);
            $this->down_payment_amount = (float) ($record->down_payment_amount ?? 0);
            $this->notes = $record->notes;
            $this->loadItemsFromRecord();
        } else {
            $this->start_date = now()->format('Y-m-d\TH:i');
            $this->end_date = now()->addDay()->format('Y-m-d\TH:i');
            $this->status = Rental::STATUS_QUOTATION;
        }
    }

    protected function loadItemsFromRecord(): void
    {
        $grouped = RentalForm::groupItemsForForm($this->record->items);
        $this->items = [];
        foreach ($grouped as $g) {
            $compositeId = $g['product_id'];
            $productId = $compositeId;
            $variationId = null;
            if (str_contains((string) $compositeId, ':')) {
                [$productId, $variationId] = array_pad(explode(':', (string) $compositeId), 2, null);
            }
            $this->items[] = [
                'key' => (string) Str::uuid(),
                'composite_id' => (string) $compositeId,
                'product_id' => (int) $productId,
                'variation_id' => $variationId ? (int) $variationId : null,
                'quantity' => (int) $g['quantity'],
                'daily_rate' => (float) $g['daily_rate'],
                'discount' => (float) ($g['discount'] ?? 0),
                'unit_ids' => json_decode($g['unit_ids'], true) ?: [],
            ];
        }
    }

    // ─── Derived ───
    #[Computed]
    public function days(): int
    {
        if (! $this->start_date || ! $this->end_date) {
            return 1;
        }
        try {
            $s = Carbon::parse($this->start_date);
            $e = Carbon::parse($this->end_date);

            return max(1, (int) ceil($s->diffInHours($e) / 24));
        } catch (\Throwable $e) {
            return 1;
        }
    }

    #[Computed]
    public function durationLabel(): string
    {
        if (! $this->start_date || ! $this->end_date) {
            return '—';
        }
        try {
            $s = Carbon::parse($this->start_date);
            $e = Carbon::parse($this->end_date);
            $totalHours = max(0, (int) $s->diffInHours($e));
            $d = intdiv($totalHours, 24);
            $h = $totalHours % 24;

            return "{$d} hari {$h} jam";
        } catch (\Throwable $e) {
            return '—';
        }
    }

    #[Computed]
    public function dateRangeLabel(): string
    {
        if (! $this->start_date || ! $this->end_date) {
            return '';
        }
        try {
            $s = Carbon::parse($this->start_date)->locale('id')->translatedFormat('j M');
            $e = Carbon::parse($this->end_date)->locale('id')->translatedFormat('j M');

            return "{$s} – {$e}";
        } catch (\Throwable $e) {
            return '';
        }
    }

    #[Computed]
    public function customers(): array
    {
        return User::query()
            ->select('id', 'name', 'phone')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
            ])
            ->all();
    }

    #[Computed]
    public function statuses(): array
    {
        $out = [];
        foreach (Rental::getStatusOptions() as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label, 'tone' => $this->statusTone($value)];
        }

        return $out;
    }

    protected function statusTone(string $status): string
    {
        return match ($status) {
            Rental::STATUS_QUOTATION => 'amber',
            Rental::STATUS_CONFIRMED => 'blue',
            Rental::STATUS_ACTIVE => 'green',
            Rental::STATUS_COMPLETED => 'gray',
            Rental::STATUS_CANCELLED => 'gray',
            Rental::STATUS_LATE_PICKUP, Rental::STATUS_LATE_RETURN => 'red',
            Rental::STATUS_PARTIAL_RETURN => 'amber',
            default => 'gray',
        };
    }

    #[Computed]
    public function currentStatus(): array
    {
        foreach ($this->statuses as $s) {
            if ($s['value'] === $this->status) {
                return $s;
            }
        }

        return ['value' => $this->status, 'label' => $this->status, 'tone' => 'gray'];
    }

    #[Computed]
    public function customerInfo(): ?array
    {
        if (! $this->customer_id) {
            return null;
        }
        $user = User::find($this->customer_id);
        if (! $user) {
            return null;
        }
        $status = $user->getVerificationStatus();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'verified' => $status === 'verified',
            'verification_status' => $status,
            'verification_label' => $user->getVerificationStatusLabel(),
        ];
    }

    // ─── Product search ───
    #[Computed]
    public function searchResults(): array
    {
        if (trim($this->searchTerm) === '') {
            return [];
        }

        return $this->productOptions($this->searchTerm, 8);
    }

    /**
     * Return array of catalog rows: [composite_id, product_id, variation_id, sku_label, name, cat, brand, price, avail]
     */
    protected function productOptions(?string $needle = null, int $limit = 50): array
    {
        $q = Product::query()
            ->with(['variations:id,product_id,name,daily_rate', 'category:id,name'])
            ->select(['id', 'name', 'category_id', 'daily_rate', 'image', 'is_active'])
            ->where('is_active', true);

        if ($needle) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $needle).'%';
            $q->where(function ($qq) use ($like) {
                $qq->where('name', 'like', $like)
                    ->orWhereHas('variations', fn ($v) => $v->where('name', 'like', $like))
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like));
            });
        }

        $products = $q->limit(40)->get();

        $rows = [];
        foreach ($products as $p) {
            $cat = optional($p->category)->name ?? 'Other';
            $img = $p->image ? \Illuminate\Support\Facades\Storage::url($p->image) : null;
            if ($p->variations && $p->variations->isNotEmpty()) {
                foreach ($p->variations as $v) {
                    $rows[] = [
                        'composite_id' => "{$p->id}:{$v->id}",
                        'product_id' => $p->id,
                        'variation_id' => $v->id,
                        'sku' => "P{$p->id}V{$v->id}",
                        'name' => $p->name.' ('.$v->name.')',
                        'cat' => $cat,
                        'price' => (float) ($v->daily_rate ?? $p->daily_rate ?? 0),
                        'avail' => $this->availableCount($p->id, $v->id),
                        'image' => $img,
                    ];
                }
            } else {
                $rows[] = [
                    'composite_id' => (string) $p->id,
                    'product_id' => $p->id,
                    'variation_id' => null,
                    'sku' => "P{$p->id}",
                    'name' => $p->name,
                    'cat' => $cat,
                    'price' => (float) ($p->daily_rate ?? 0),
                    'avail' => $this->availableCount($p->id, null),
                    'image' => $img,
                ];
            }
        }

        return array_slice($rows, 0, $limit);
    }

    protected function availableCount(int $productId, ?int $variationId): int
    {
        $excludeId = $this->record?->id;
        $units = RentalForm::getAvailableUnitsOptimized(
            $this->start_date,
            $this->end_date,
            $excludeId,
            $productId,
            $variationId,
            $this->allUsedUnitIds()
        );

        return $units->count();
    }

    protected function allUsedUnitIds(?string $exceptKey = null): array
    {
        $ids = [];
        foreach ($this->items as $it) {
            if ($exceptKey && $it['key'] === $exceptKey) {
                continue;
            }
            $ids = array_merge($ids, $it['unit_ids']);
        }

        return array_values(array_unique($ids));
    }

    // ─── Catalog / bulk add ───
    #[Computed]
    public function catalogRows(): array
    {
        return $this->productOptions(null, 200);
    }

    #[Computed]
    public function catalogCategories(): array
    {
        $cats = [];
        foreach ($this->catalogRows as $r) {
            $cats[$r['cat']] = true;
        }

        return array_values(array_keys($cats));
    }

    // ─── Item operations ───
    public function addProduct(string $compositeId, int $qty = 1): void
    {
        [$productId, $variationId] = $this->parseComposite($compositeId);

        $product = $variationId
            ? ProductVariation::find($variationId)
            : Product::find($productId);
        if (! $product) {
            return;
        }
        $name = $variationId
            ? (Product::find($productId)?->name.' ('.$product->name.')')
            : $product->name;
        $dailyRate = (float) ($product->daily_rate ?? 0);

        $excludeId = $this->record?->id;
        $available = RentalForm::getAvailableUnitsOptimized(
            $this->start_date,
            $this->end_date,
            $excludeId,
            $productId,
            $variationId,
            $this->allUsedUnitIds()
        );

        $availCount = $available->count();
        $newUnitIds = $available->take(min($qty, $availCount))->pluck('id')->all();
        $missing = max(0, $qty - $availCount);

        if ($missing > 0) {
            Notification::make()
                ->title('Stok kurang — unit kosong ditambahkan')
                ->body("Hanya {$availCount} dari {$qty} unit yang ter-assign. {$missing} slot kosong. Gunakan tombol Transfer (Move / Swap) untuk mengambil unit dari rental lain.")
                ->warning()
                ->send();
        }

        // merge with existing line
        foreach ($this->items as &$it) {
            if ($it['composite_id'] === $compositeId) {
                $it['unit_ids'] = array_values(array_merge($it['unit_ids'], $newUnitIds));
                $it['quantity'] = count($it['unit_ids']);
                $this->dispatch('rent-toast', message: "{$name} qty +{$qty}");

                return;
            }
        }
        unset($it);

        $this->items[] = [
            'key' => (string) Str::uuid(),
            'composite_id' => $compositeId,
            'product_id' => $productId,
            'variation_id' => $variationId,
            'quantity' => $qty,
            'daily_rate' => $dailyRate,
            'discount' => 0,
            'unit_ids' => $newUnitIds,
        ];
        $this->dispatch('rent-toast', message: "{$name} ditambahkan");
    }

    protected function parseComposite(string $compositeId): array
    {
        if (str_contains($compositeId, ':')) {
            [$p, $v] = explode(':', $compositeId);

            return [(int) $p, (int) $v];
        }

        return [(int) $compositeId, null];
    }

    public function addFromSearch(string $compositeId): void
    {
        $this->addProduct($compositeId, 1);
        $this->searchTerm = '';
    }

    /**
     * Resolve a scanned code (QR/barcode) to a product and add it.
     * Products/variations don't have SKU columns in this system; matching is via:
     *   - ProductUnit.serial_number  (the physical unit code on the QR/label)
     *   - URL containing /products/{id} or /product-units/{id} (system QRs)
     */
    public function handleScanned(string $code): void
    {
        $needle = trim($code);
        if ($needle === '') {
            return;
        }

        // 1) Direct serial number match
        $unit = ProductUnit::whereRaw('LOWER(serial_number) = ?', [strtolower($needle)])->first();
        if ($unit) {
            $composite = $unit->product_variation_id
                ? "{$unit->product_id}:{$unit->product_variation_id}"
                : (string) $unit->product_id;
            $this->addProduct($composite, 1);

            return;
        }

        // 2) URL-embedded ID (e.g. system QR pointing to /admin/products/{id})
        if (preg_match('#/(products|product-units)/(\d+)#', $needle, $m)) {
            $resource = $m[1];
            $id = (int) $m[2];
            if ($resource === 'product-units') {
                $u = ProductUnit::find($id);
                if ($u) {
                    $composite = $u->product_variation_id
                        ? "{$u->product_id}:{$u->product_variation_id}"
                        : (string) $u->product_id;
                    $this->addProduct($composite, 1);

                    return;
                }
            } else {
                if (Product::whereKey($id)->exists()) {
                    $this->addProduct((string) $id, 1);

                    return;
                }
            }
        }

        $this->dispatch('rent-toast', message: "Kode tidak dikenali: {$needle}");
    }

    public function updateItem(string $key, string $field, $value): void
    {
        foreach ($this->items as $i => $it) {
            if ($it['key'] !== $key) {
                continue;
            }

            if ($field === 'quantity') {
                $newQty = max(1, (int) $value);
                $oldQty = (int) $it['quantity'];
                if ($newQty === $oldQty) {
                    return;
                }
                if ($newQty > $oldQty) {
                    $needed = $newQty - $oldQty;
                    $excludeId = $this->record?->id;
                    $available = RentalForm::getAvailableUnitsOptimized(
                        $this->start_date,
                        $this->end_date,
                        $excludeId,
                        $it['product_id'],
                        $it['variation_id'],
                        $this->allUsedUnitIds()
                    );
                    $availCount = $available->count();
                    $takeCount = min($needed, $availCount);
                    $shortBy = $needed - $takeCount;

                    if ($shortBy > 0) {
                        Notification::make()
                            ->title('Stok kurang — slot kosong ditambahkan')
                            ->body("Hanya {$availCount} dari {$needed} unit tambahan yang tersedia. {$shortBy} slot kosong. Gunakan tombol Transfer untuk mengambil unit dari rental lain.")
                            ->warning()
                            ->send();
                    }

                    if ($takeCount > 0) {
                        $this->items[$i]['unit_ids'] = array_values(array_merge(
                            $it['unit_ids'],
                            $available->take($takeCount)->pluck('id')->all()
                        ));
                    }
                } else {
                    $this->items[$i]['unit_ids'] = array_slice($it['unit_ids'], 0, $newQty);
                }
                $this->items[$i]['quantity'] = $newQty;
            } elseif ($field === 'daily_rate') {
                $this->items[$i]['daily_rate'] = max(0, (float) $value);
            } elseif ($field === 'discount') {
                $this->items[$i]['discount'] = max(0, min(100, (float) $value));
            }

            return;
        }
    }

    public function removeItem(string $key): void
    {
        $this->items = array_values(array_filter($this->items, fn ($it) => $it['key'] !== $key));
    }

    /**
     * Decrement quantity by composite id (used by catalog stepper).
     * Drops the row entirely when quantity reaches zero.
     */
    public function decrementByComposite(string $compositeId): void
    {
        foreach ($this->items as $i => $it) {
            if ($it['composite_id'] !== $compositeId) {
                continue;
            }
            $newQty = (int) $it['quantity'] - 1;
            if ($newQty <= 0) {
                $this->items = array_values(array_filter($this->items, fn ($x) => $x['key'] !== $it['key']));
                return;
            }
            $this->items[$i]['quantity'] = $newQty;
            $this->items[$i]['unit_ids'] = array_slice($it['unit_ids'], 0, $newQty);
            return;
        }
    }

    public function reorder(array $orderedKeys): void
    {
        $byKey = collect($this->items)->keyBy('key');
        $next = [];
        foreach ($orderedKeys as $k) {
            if ($byKey->has($k)) {
                $next[] = $byKey[$k];
            }
        }
        // Append anything missing
        foreach ($this->items as $it) {
            if (! in_array($it['key'], $orderedKeys, true)) {
                $next[] = $it;
            }
        }
        $this->items = $next;
    }

    // ─── Unit modal ───
    public function openUnitModal(string $key): void
    {
        $this->unitModalKey = $key;
        $this->unitModalOpen = true;
    }

    public function closeUnitModal(): void
    {
        $this->unitModalKey = null;
        $this->unitModalOpen = false;
    }

    public function saveUnits(array $unitIds): void
    {
        if (! $this->unitModalKey) {
            return;
        }
        $clean = array_values(array_filter(array_map('intval', $unitIds)));
        foreach ($this->items as $i => $it) {
            if ($it['key'] === $this->unitModalKey) {
                $this->items[$i]['unit_ids'] = $clean;
                $this->items[$i]['quantity'] = max(1, count($clean));
                break;
            }
        }
        $this->dispatch('rent-toast', message: 'Unit diperbarui');
        $this->closeUnitModal();
    }

    // ─── Transfer (Move / Swap) cross-rental ───
    private const TRANSFERABLE_STATUSES = [
        Rental::STATUS_QUOTATION,
        Rental::STATUS_CONFIRMED,
        Rental::STATUS_LATE_PICKUP,
    ];

    #[Computed]
    public function canTransfer(): bool
    {
        return $this->record && $this->record->exists
            && in_array($this->status, self::TRANSFERABLE_STATUSES, true);
    }

    /**
     * Open Transfer modal in MOVE mode for a given serial unit.
     * Auto-saves the editor first so the source RentalItem exists in DB and matches state.
     */
    public function openMoveModal(int $unitId): void
    {
        $this->beginTransfer($unitId, 'move', null);
    }

    public function openSwapModal(int $unitId): void
    {
        $this->beginTransfer($unitId, 'swap', null);
    }

    /**
     * Open Transfer modal from a row (group). Unit will be picked inside the modal.
     */
    public function openTransferForRow(string $itemKey, string $mode): void
    {
        $this->beginTransfer(null, $mode, $itemKey);
    }

    protected function beginTransfer(?int $unitId, string $mode, ?string $itemKey): void
    {
        if (!$this->canTransfer) {
            Notification::make()
                ->title('Tidak dapat di-transfer')
                ->body('Rental harus berstatus quotation, confirmed, atau late_pickup.')
                ->danger()
                ->send();
            return;
        }

        // Auto-save current editor state to ensure source RentalItem exists & matches.
        try {
            $this->persistInline();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal menyimpan perubahan')->body($e->getMessage())->danger()->send();
            return;
        }

        if ($unitId !== null) {
            $exists = RentalItem::where('rental_id', $this->record->id)
                ->where('product_unit_id', $unitId)
                ->exists();
            if (!$exists) {
                Notification::make()->title('Unit tidak ditemukan di rental ini')->danger()->send();
                return;
            }
        }

        $this->transferMode = $mode;
        $this->transferUnitId = $unitId;
        $this->transferItemKey = $itemKey;
        $this->transferTargetRentalId = null;
        $this->transferTargetItemId = null;
        $this->transferModalOpen = true;

        // If opened from a row and there's only one unit assigned, auto-pick it.
        if ($unitId === null && $itemKey !== null) {
            $row = collect($this->items)->firstWhere('key', $itemKey);
            if ($row && count($row['unit_ids']) === 1) {
                $this->transferUnitId = (int) $row['unit_ids'][0];
            }
        }
    }

    public function closeTransferModal(): void
    {
        $this->transferModalOpen = false;
        $this->transferUnitId = null;
        $this->transferItemKey = null;
        $this->transferTargetRentalId = null;
        $this->transferTargetItemId = null;
    }

    public function updatedTransferTargetRentalId(): void
    {
        // Reset chosen item when target rental changes (swap mode).
        $this->transferTargetItemId = null;
    }

    #[Computed]
    public function transferContext(): ?array
    {
        if (!$this->transferModalOpen || !$this->record?->exists) {
            return null;
        }

        // Build pickable units list when opened from a row (no fixed unit yet).
        $pickableUnits = [];
        if ($this->transferItemKey) {
            $row = collect($this->items)->firstWhere('key', $this->transferItemKey);
            if ($row && !empty($row['unit_ids'])) {
                $units = ProductUnit::whereIn('id', $row['unit_ids'])->get(['id', 'serial_number']);
                $pickableUnits = $units->map(fn ($u) => [
                    'id' => $u->id,
                    'serial' => $u->serial_number,
                ])->values()->all();
            }
        }

        $unit = $this->transferUnitId
            ? ProductUnit::with('product')->find($this->transferUnitId)
            : null;

        $targets = Rental::query()
            ->where('id', '!=', $this->record->id)
            ->whereIn('status', self::TRANSFERABLE_STATUSES)
            ->with('customer')
            ->when($this->transferMode === 'swap', fn ($q) => $q->whereHas('items'))
            ->orderBy('start_date', 'desc')
            ->limit(200)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'label' => sprintf(
                    '%s — %s (%s → %s) [%s]',
                    $r->rental_code ?? '#'.$r->id,
                    $r->customer?->name ?? 'Unknown',
                    optional($r->start_date)->format('Y-m-d'),
                    optional($r->end_date)->format('Y-m-d'),
                    $r->status,
                ),
            ])
            ->all();

        $targetItems = [];
        if ($this->transferMode === 'swap' && $this->transferTargetRentalId) {
            $targetItems = RentalItem::query()
                ->where('rental_id', $this->transferTargetRentalId)
                ->with('productUnit.product')
                ->get()
                ->map(fn ($ri) => [
                    'id' => $ri->id,
                    'label' => ($ri->productUnit?->product?->name ?? 'Unknown')
                        . ' — ' . ($ri->productUnit?->serial_number ?? '#'.$ri->product_unit_id),
                ])
                ->all();
        }

        return [
            'mode' => $this->transferMode,
            'unit_serial' => $unit?->serial_number,
            'product_name' => $unit?->product?->name,
            'pickable_units' => $pickableUnits,
            'needs_unit_pick' => $this->transferUnitId === null,
            'targets' => $targets,
            'target_items' => $targetItems,
        ];
    }

    public function confirmTransfer(): void
    {
        if (!$this->canTransfer) {
            Notification::make()->title('Rental tidak dapat di-transfer')->danger()->send();
            return;
        }
        if (!$this->transferUnitId) {
            Notification::make()->title('Pilih unit terlebih dahulu')->danger()->send();
            return;
        }
        if (!$this->transferTargetRentalId) {
            Notification::make()->title('Pilih rental tujuan')->danger()->send();
            return;
        }

        $sourceItem = RentalItem::where('rental_id', $this->record->id)
            ->where('product_unit_id', $this->transferUnitId)
            ->first();

        if (!$sourceItem) {
            Notification::make()->title('Item sumber tidak ditemukan')->danger()->send();
            return;
        }

        $service = app(RentalItemTransferService::class);

        try {
            if ($this->transferMode === 'move') {
                $target = Rental::find($this->transferTargetRentalId);
                if (!$target) {
                    Notification::make()->title('Rental tujuan tidak ditemukan')->danger()->send();
                    return;
                }
                $service->move($sourceItem, $target);
                $msg = "Unit dipindahkan ke {$target->rental_code}";
            } else {
                if (!$this->transferTargetItemId) {
                    Notification::make()->title('Pilih item untuk di-swap')->danger()->send();
                    return;
                }
                $other = RentalItem::find($this->transferTargetItemId);
                if (!$other) {
                    Notification::make()->title('Item tujuan tidak ditemukan')->danger()->send();
                    return;
                }
                $service->swap($sourceItem, $other);
                $msg = "Unit di-swap dengan {$other->rental->rental_code}";
            }

            Notification::make()->title('Transfer berhasil')->body($msg)->success()->send();
            $this->closeTransferModal();

            // Reload editor state from DB (items may have changed).
            $this->redirect(RentalResource::getUrl('edit', ['record' => $this->record]), navigate: false);
        } catch (\Throwable $e) {
            Notification::make()
                ->title($this->transferMode === 'move' ? 'Gagal memindahkan unit' : 'Gagal swap unit')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Persist current editor state to DB without redirect/notification side effects.
     * Reused by transfer auto-save.
     */
    protected function persistInline(): void
    {
        $this->validate([
            'customer_id' => 'required|integer|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'required|string',
        ]);

        $days = $this->days;
        $totals = $this->totals;

        $payload = [
            'user_id' => $this->customer_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'subtotal' => $totals['subtotal'],
            'discount' => $this->discount,
            'discount_type' => $this->discount_type,
            'deposit' => $this->deposit,
            'deposit_type' => $this->deposit_type,
            'down_payment_amount' => $this->down_payment_amount,
            'tax_base' => $totals['tax_base'],
            'ppn_amount' => $totals['ppn_amount'],
            'ppn_rate' => $totals['ppn_rate'],
            'total' => $totals['total'],
            'notes' => $this->notes,
        ];

        if (!$this->record || !$this->record->exists) {
            $this->record = Rental::create($payload);
        } else {
            $this->record->fill($payload);
            $this->record->saveQuietly();
        }

        $grouped = [];
        foreach ($this->items as $it) {
            $grouped[] = [
                'product_id' => $it['composite_id'],
                'quantity' => (int) $it['quantity'],
                'unit_ids' => json_encode($it['unit_ids']),
                'daily_rate' => (float) $it['daily_rate'],
                'days' => $days,
                'discount' => (float) $it['discount'],
                'subtotal' => max(0, ((float) $it['daily_rate'] * $days) - ((float) $it['daily_rate'] * $days) * ((float) $it['discount'] / 100)),
            ];
        }

        RentalForm::syncRentalItems($this->record, $grouped);
        $this->record->refresh();
    }

    #[Computed]
    public function unitModalContext(): ?array
    {
        if (! $this->unitModalOpen || ! $this->unitModalKey) {
            return null;
        }
        $row = collect($this->items)->firstWhere('key', $this->unitModalKey);
        if (! $row) {
            return null;
        }

        $product = Product::find($row['product_id']);
        $variation = $row['variation_id'] ? ProductVariation::find($row['variation_id']) : null;
        $name = $variation ? ($product?->name.' ('.$variation->name.')') : ($product?->name ?? 'Produk');
        $sku = $variation ? "P{$row['product_id']}V{$row['variation_id']}" : "P{$row['product_id']}";

        $excludeId = $this->record?->id;
        $allUsed = $this->allUsedUnitIds($this->unitModalKey);

        $available = RentalForm::getAvailableUnitsOptimized(
            $this->start_date,
            $this->end_date,
            $excludeId,
            $row['product_id'],
            $row['variation_id'],
            $allUsed
        );

        $currentUnits = ProductUnit::whereIn('id', $row['unit_ids'])->get();
        $byId = $currentUnits->keyBy('id');

        // Total pool size for this product/variation regardless of availability
        $pool = ProductUnit::query()
            ->where('product_id', $row['product_id'])
            ->when($row['variation_id'], fn ($q) => $q->where('product_variation_id', $row['variation_id']))
            ->whereNotIn('status', [ProductUnit::STATUS_RETIRED])
            ->get(['id', 'serial_number']);

        $availableMap = $available->keyBy('id');
        // Build pool list for dropdown: available + already-assigned, exclude booked-elsewhere
        $options = [];
        foreach ($pool as $u) {
            $isAssignedHere = in_array($u->id, $row['unit_ids'], true);
            $isAvailable = $availableMap->has($u->id);
            $options[] = [
                'id' => $u->id,
                'serial' => $u->serial_number,
                'available' => $isAvailable || $isAssignedHere,
            ];
        }

        return [
            'key' => $this->unitModalKey,
            'product_name' => $name,
            'sku' => $sku,
            'qty' => (int) $row['quantity'],
            'unit_ids' => $row['unit_ids'],
            'unit_labels' => $row['unit_ids'] && $byId->count()
                ? collect($row['unit_ids'])->map(fn ($id) => $byId[$id]?->serial_number ?? '—')->all()
                : [],
            'pool' => $options,
            'pool_total' => $pool->count(),
            'available_total' => $available->count() + collect($row['unit_ids'])->filter(fn ($id) => $byId->has($id))->count(),
            'date_label' => $this->dateRangeLabel,
        ];
    }

    // ─── Totals ───
    #[Computed]
    public function subtotal(): float
    {
        $days = $this->days;
        $sum = 0;
        foreach ($this->items as $it) {
            $gross = (float) $it['daily_rate'] * (int) $it['quantity'] * $days;
            $sum += max(0, $gross - ($gross * ((float) $it['discount'] / 100)));
        }

        return $sum;
    }

    #[Computed]
    public function totals(): array
    {
        $grossSubtotal = $this->subtotal;
        $discountAmount = $this->discount_type === 'percent'
            ? $grossSubtotal * ($this->discount / 100)
            : $this->discount;
        $netSubtotal = max(0, $grossSubtotal - $discountAmount);

        $taxEnabled = filter_var(Setting::get('tax_enabled', true), FILTER_VALIDATE_BOOLEAN);
        $isPkp = filter_var(Setting::get('is_pkp', false), FILTER_VALIDATE_BOOLEAN);
        $isTaxable = filter_var(Setting::get('is_taxable', true), FILTER_VALIDATE_BOOLEAN);
        $priceIncludesTax = filter_var(Setting::get('price_includes_tax', false), FILTER_VALIDATE_BOOLEAN);
        $ppnRate = (float) Setting::get('ppn_rate', 11);

        $taxBase = $netSubtotal;
        $ppnAmount = 0;
        if ($taxEnabled && $isPkp && $isTaxable) {
            if ($priceIncludesTax) {
                $taxBase = $netSubtotal / (1 + ($ppnRate / 100));
            }
            $ppnAmount = $taxBase * ($ppnRate / 100);
        } else {
            $ppnRate = 0;
        }

        $payable = $priceIncludesTax ? $netSubtotal : ($taxBase + $ppnAmount);
        $depositAmount = $this->deposit_type === 'percent'
            ? $grossSubtotal * ($this->deposit / 100)
            : $this->deposit;
        $total = $payable + $depositAmount;

        return [
            'subtotal' => $grossSubtotal,
            'discount_amount' => $discountAmount,
            'net_subtotal' => $netSubtotal,
            'tax_base' => round($taxBase, 2),
            'ppn_amount' => round($ppnAmount, 2),
            'ppn_rate' => $ppnRate,
            'deposit_amount' => round($depositAmount, 2),
            'total' => round($total, 2),
            'tax_enabled' => $taxEnabled && $isPkp && $isTaxable,
        ];
    }

    // ─── Save / Cancel ───
    public function save()
    {
        $this->validate([
            'customer_id' => 'required|integer|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'required|string',
            'items' => 'array',
        ], [], [
            'customer_id' => 'Customer',
            'start_date' => 'Mulai',
            'end_date' => 'Selesai',
        ]);

        $days = $this->days;
        $totals = $this->totals;

        $payload = [
            'user_id' => $this->customer_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'subtotal' => $totals['subtotal'],
            'discount' => $this->discount,
            'discount_type' => $this->discount_type,
            'deposit' => $this->deposit,
            'deposit_type' => $this->deposit_type,
            'down_payment_amount' => $this->down_payment_amount,
            'tax_base' => $totals['tax_base'],
            'ppn_amount' => $totals['ppn_amount'],
            'ppn_rate' => $totals['ppn_rate'],
            'total' => $totals['total'],
            'notes' => $this->notes,
        ];

        if (! $this->record || ! $this->record->exists) {
            $this->record = Rental::create($payload);
        } else {
            $this->record->fill($payload);
            $this->record->saveQuietly();
        }

        // Build grouped_items in the shape syncRentalItems expects
        $grouped = [];
        foreach ($this->items as $it) {
            $grouped[] = [
                'product_id' => $it['composite_id'],
                'quantity' => (int) $it['quantity'],
                'unit_ids' => json_encode($it['unit_ids']),
                'daily_rate' => (float) $it['daily_rate'],
                'days' => $days,
                'discount' => (float) $it['discount'],
                'subtotal' => max(0, ((float) $it['daily_rate'] * $days) - ((float) $it['daily_rate'] * $days) * ((float) $it['discount'] / 100)),
            ];
        }

        RentalForm::syncRentalItems($this->record, $grouped);
        $this->record->touch();
        $this->record->refresh();

        Notification::make()
            ->title('Perubahan disimpan')
            ->success()
            ->send();

        return redirect(RentalResource::getUrl('edit', ['record' => $this->record]));
    }

    public function cancel()
    {
        return redirect(RentalResource::getUrl('index'));
    }

    // ─── Header dropdown actions ───
    public function duplicateRental()
    {
        if (! $this->record || ! $this->record->exists) {
            Notification::make()->title('Simpan rental dulu sebelum duplikat')->warning()->send();
            return null;
        }

        $original = $this->record->load('items');

        $new = $original->replicate(['rental_code', 'quotation_id', 'invoice_id']);
        $new->status = Rental::STATUS_QUOTATION;
        $new->rental_code = null;
        $new->quotation_id = null;
        $new->invoice_id = null;
        $new->down_payment_status = null;
        $new->save();

        foreach ($original->items as $item) {
            $copy = $item->replicate();
            $copy->rental_id = $new->id;
            $copy->save();
        }

        Notification::make()->title('Rental berhasil diduplikat')->success()->send();

        return redirect(RentalResource::getUrl('edit', ['record' => $new]));
    }

    public function printQuotation()
    {
        if (! $this->record || ! $this->record->exists || ! $this->record->quotation_id) {
            Notification::make()->title('Quotation tidak ditemukan')->danger()->send();
            return null;
        }

        $quotation = Quotation::with(['user', 'rentals.items.productUnit.product', 'rentals.items.rentalItemKits.unitKit'])
            ->find($this->record->quotation_id);

        if (! $quotation) {
            Notification::make()->title('Quotation tidak ditemukan')->danger()->send();
            return null;
        }

        $pdf = Pdf::loadView('pdf.quotation', ['quotation' => $quotation]);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'Quotation-'.$quotation->number.'.pdf'
        );
    }

    public function printInvoice()
    {
        if (! $this->record || ! $this->record->exists || ! $this->record->invoice_id) {
            Notification::make()->title('Invoice tidak ditemukan')->danger()->send();
            return null;
        }

        $invoice = Invoice::with(['user', 'rentals.items.productUnit.product', 'rentals.items.rentalItemKits.unitKit'])
            ->find($this->record->invoice_id);

        if (! $invoice) {
            Notification::make()->title('Invoice tidak ditemukan')->danger()->send();
            return null;
        }

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'Invoice-'.$invoice->number.'.pdf'
        );
    }

    public function cancelRental(string $reason = '')
    {
        if (! $this->record || ! $this->record->exists) {
            return null;
        }

        $reason = trim($reason) !== '' ? $reason : 'Dibatalkan dari halaman edit';
        $this->record->cancelRental($reason);

        Notification::make()->title('Rental dibatalkan')->success()->send();

        return redirect(RentalResource::getUrl('index'));
    }

    // ─── Discount / deposit type toggles ───
    public function toggleDiscountType(): void
    {
        $this->discount_type = $this->discount_type === 'percent' ? 'fixed' : 'percent';
        // Clamp percent to 0-100 so an old Rp value doesn't stay as 750000%
        if ($this->discount_type === 'percent') {
            $this->discount = min(100, max(0, $this->discount));
        }
    }

    public function toggleDepositType(): void
    {
        $this->deposit_type = $this->deposit_type === 'percent' ? 'fixed' : 'percent';
        if ($this->deposit_type === 'percent') {
            $this->deposit = min(100, max(0, $this->deposit));
        }
    }

    public function render()
    {
        return view('livewire.admin.rental-editor');
    }
}
