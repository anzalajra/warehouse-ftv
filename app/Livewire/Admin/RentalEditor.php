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

    // Customer typeahead search
    public string $customerSearch = '';
    public bool $newCustomerModalOpen = false;
    public string $newCustomerName = '';
    public string $newCustomerEmail = '';
    public string $newCustomerPhone = '';

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

    // Per-request caches — cleared in updated() when dates/items change
    protected ?array $availMapCache = null;
    protected ?array $catalogRowsCache = null;
    protected ?array $bookedUnitIdsCache = null;
    protected ?array $totalOwnedMapCache = null;

    public function updatedStartDate(): void
    {
        $this->invalidateAvailabilityCaches();
    }

    public function updatedEndDate(): void
    {
        $this->invalidateAvailabilityCaches();
    }

    protected function invalidateAvailabilityCaches(): void
    {
        $this->availMapCache = null;
        $this->catalogRowsCache = null;
        $this->bookedUnitIdsCache = null;
        unset($this->catalogRows, $this->catalogCategories, $this->searchResults);
    }

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

    /**
     * Server-side searchable customer list. Returns up to 20 matches based on
     * $customerSearch (name / email / phone). Empty term returns top 20 by name.
     */
    #[Computed]
    public function customers(): array
    {
        $q = User::query()->select('id', 'name', 'phone', 'email');
        $needle = trim($this->customerSearch);

        if ($needle !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $needle) . '%';
            $q->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        return $q->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'email' => $u->email,
            ])
            ->all();
    }

    public function selectCustomer(int $id): void
    {
        $this->customer_id = $id;
        $this->customerSearch = '';
    }

    public function openNewCustomerModal(): void
    {
        // Pre-fill name from current search term if it looks like a name (no @, no digits-only).
        $needle = trim($this->customerSearch);
        $this->newCustomerName = $needle && !str_contains($needle, '@') && !ctype_digit($needle) ? $needle : '';
        $this->newCustomerEmail = $needle && str_contains($needle, '@') ? $needle : '';
        $this->newCustomerPhone = $needle && ctype_digit(str_replace(['+', ' ', '-'], '', $needle)) ? $needle : '';
        $this->newCustomerModalOpen = true;
    }

    public function closeNewCustomerModal(): void
    {
        $this->newCustomerModalOpen = false;
        $this->newCustomerName = '';
        $this->newCustomerEmail = '';
        $this->newCustomerPhone = '';
    }

    public function createCustomer(): void
    {
        $this->validate([
            'newCustomerName' => 'required|string|max:255',
            'newCustomerEmail' => 'nullable|email|max:255|unique:users,email',
            'newCustomerPhone' => 'nullable|string|max:30',
        ], [], [
            'newCustomerName' => 'Nama',
            'newCustomerEmail' => 'Email',
            'newCustomerPhone' => 'Telepon',
        ]);

        try {
            $user = User::create([
                'name' => $this->newCustomerName,
                'email' => $this->newCustomerEmail ?: null,
                'phone' => $this->newCustomerPhone ?: null,
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(24)),
            ]);

            $this->customer_id = $user->id;
            $this->customerSearch = '';
            $this->closeNewCustomerModal();

            Notification::make()
                ->title('Customer dibuat')
                ->body("{$user->name} berhasil ditambahkan & dipilih.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal membuat customer')->body($e->getMessage())->danger()->send();
        }
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
    protected function productOptions(?string $needle = null, ?int $limit = null): array
    {
        $products = $this->loadProductsForCatalog($needle, $limit);
        $availMap = $this->availableCountMap();
        $totalMap = $this->totalOwnedMap();

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
                        'avail' => $availMap["{$p->id}:{$v->id}"] ?? 0,
                        'total' => $totalMap["{$p->id}:{$v->id}"] ?? 0,
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
                    'avail' => $availMap["{$p->id}:0"] ?? 0,
                    'total' => $totalMap["{$p->id}:0"] ?? 0,
                    'image' => $img,
                ];
            }
        }

        return $limit !== null ? array_slice($rows, 0, $limit) : $rows;
    }

    /**
     * Batch-compute available unit counts keyed by "{product_id}:{variation_id|0}".
     * Cached per-request; invalidated when dates/items change.
     * SQL-side aggregation (GROUP BY) avoids hydrating every ProductUnit row.
     */
    protected function availableCountMap(): array
    {
        $cacheKey = md5(implode('|', [
            $this->start_date ?? '',
            $this->end_date ?? '',
            implode(',', $this->allUsedUnitIds()),
        ]));

        if ($this->availMapCache !== null && ($this->availMapCache['_key'] ?? null) === $cacheKey) {
            return $this->availMapCache['data'];
        }

        $excludeId = $this->record?->id;
        $bookedIds = $this->getBookedUnitIdsCached();
        $allExcluded = array_values(array_unique(array_merge($bookedIds, $this->allUsedUnitIds())));

        $rows = ProductUnit::query()
            ->selectRaw('product_id, COALESCE(product_variation_id, 0) as vid, COUNT(*) as c')
            ->whereNotIn('status', [ProductUnit::STATUS_MAINTENANCE, ProductUnit::STATUS_RETIRED])
            ->whereNotIn('condition', ['broken', 'lost'])
            ->when(!empty($allExcluded), fn ($q) => $q->whereNotIn('id', $allExcluded))
            ->groupBy('product_id', 'vid')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map["{$r->product_id}:{$r->vid}"] = (int) $r->c;
        }

        $this->availMapCache = ['_key' => $cacheKey, 'data' => $map];
        return $map;
    }

    /**
     * Total physically-owned, rentable units per "{product_id}:{variation_id|0}".
     * Same status/condition filter as availability, but WITHOUT excluding units
     * booked elsewhere or already used in this rental — this is the absolute ceiling
     * of how many units of a product a single rental can ever hold (the rest become
     * empty slots filled via Transfer). Request-scoped cache; independent of dates.
     */
    protected function totalOwnedMap(): array
    {
        if ($this->totalOwnedMapCache !== null) {
            return $this->totalOwnedMapCache;
        }

        $rows = ProductUnit::query()
            ->selectRaw('product_id, COALESCE(product_variation_id, 0) as vid, COUNT(*) as c')
            ->whereNotIn('status', [ProductUnit::STATUS_MAINTENANCE, ProductUnit::STATUS_RETIRED])
            ->whereNotIn('condition', ['broken', 'lost'])
            ->groupBy('product_id', 'vid')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map["{$r->product_id}:{$r->vid}"] = (int) $r->c;
        }

        return $this->totalOwnedMapCache = $map;
    }

    /** Ceiling of rentable units owned for a product/variation (see totalOwnedMap). */
    public function totalOwnedFor(int $productId, ?int $variationId): int
    {
        return $this->totalOwnedMap()["{$productId}:" . ($variationId ?: 0)] ?? 0;
    }

    /**
     * Cache booked unit IDs for the current date range — reused by availableCountMap
     * and addProduct() within the same request.
     */
    protected function getBookedUnitIdsCached(): array
    {
        $cacheKey = ($this->start_date ?? '') . '|' . ($this->end_date ?? '') . '|' . ($this->record?->id ?? '');
        if ($this->bookedUnitIdsCache !== null && ($this->bookedUnitIdsCache['_key'] ?? null) === $cacheKey) {
            return $this->bookedUnitIdsCache['data'];
        }
        $ids = \Illuminate\Support\Facades\Cache::remember(
            'booked_units:' . $cacheKey,
            5, // 5 seconds — short TTL since this changes when any rental is saved
            fn () => $this->callPrivateGetBookedUnitIds()
        );
        $this->bookedUnitIdsCache = ['_key' => $cacheKey, 'data' => $ids];
        return $ids;
    }

    protected function callPrivateGetBookedUnitIds(): array
    {
        // RentalForm::getBookedUnitIds is private — replicate the logic here so
        // we can cache. Kept in sync with that method.
        if (!$this->start_date || !$this->end_date) return [];

        $activeStatuses = [
            Rental::STATUS_QUOTATION,
            Rental::STATUS_CONFIRMED,
            Rental::STATUS_ACTIVE,
            Rental::STATUS_LATE_PICKUP,
            Rental::STATUS_LATE_RETURN,
        ];

        $excludeRentalId = $this->record?->id;

        $directlyBooked = RentalItem::query()
            ->when($excludeRentalId, fn ($q) => $q->where('rental_id', '!=', $excludeRentalId))
            ->whereHas('rental', function ($query) use ($activeStatuses) {
                $query->whereIn('status', $activeStatuses)
                    ->where('start_date', '<', $this->end_date)
                    ->where('end_date', '>', $this->start_date);
            })
            ->pluck('product_unit_id')
            ->filter()
            ->toArray();

        if (empty($directlyBooked)) {
            return [];
        }

        $componentBooked = \App\Models\UnitKit::whereNotNull('linked_unit_id')
            ->whereIn('unit_id', function ($query) use ($directlyBooked) {
                $query->select('unit_id')
                    ->from('unit_kits')
                    ->whereIn('unit_id', $directlyBooked);
            })
            ->pluck('linked_unit_id')
            ->toArray();

        $bundleBooked = \App\Models\UnitKit::whereNotNull('linked_unit_id')
            ->whereIn('linked_unit_id', $directlyBooked)
            ->pluck('unit_id')
            ->toArray();

        return array_values(array_unique(array_merge($directlyBooked, $componentBooked, $bundleBooked)));
    }

    /**
     * Heavy product list query — cached per-request so re-renders don't repeat it.
     * For unfiltered/unlimited catalog, also cached in laravel cache for 60s (shared across users).
     */
    protected ?array $productsLoadCache = null;
    protected function loadProductsForCatalog(?string $needle, ?int $limit)
    {
        $isUnfilteredFull = ($needle === null && $limit === null);
        $cacheKey = 'cat_products|' . ($needle ?? '') . '|' . ($limit ?? 'all');

        if ($this->productsLoadCache !== null && isset($this->productsLoadCache[$cacheKey])) {
            return $this->productsLoadCache[$cacheKey];
        }

        $loader = function () use ($needle, $limit) {
            $q = Product::query()
                ->with(['variations:id,product_id,name,daily_rate', 'category:id,name,slug'])
                ->select(['id', 'name', 'category_id', 'daily_rate', 'image', 'is_active'])
                ->where('is_active', true)
                ->whereDoesntHave('category', fn ($c) => $c->where('slug', 'accessories-kits'))
                ->orderBy('name');

            if ($needle) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $needle).'%';
                $q->where(function ($qq) use ($like) {
                    $qq->where('name', 'like', $like)
                        ->orWhereHas('variations', fn ($v) => $v->where('name', 'like', $like))
                        ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like));
                });
            }
            if ($limit !== null) {
                $q->limit($limit);
            }
            return $q->get();
        };

        // Only cache the unfiltered full list across requests; search results vary too much.
        $products = $isUnfilteredFull
            ? \Illuminate\Support\Facades\Cache::remember('catalog_products_full_v1', 60, $loader)
            : $loader();

        $this->productsLoadCache[$cacheKey] = $products;
        return $products;
    }

    protected function availableCount(int $productId, ?int $variationId): int
    {
        return $this->fetchAvailableUnitsCached($productId, $variationId)->count();
    }

    /**
     * Returns Collection of available units for a product+variation, using the cached
     * booked-units list. Much cheaper than calling RentalForm::getAvailableUnitsOptimized
     * repeatedly (which always re-runs the 3 booked-id sub-queries).
     */
    protected function fetchAvailableUnitsCached(int $productId, ?int $variationId)
    {
        $bookedIds = $this->getBookedUnitIdsCached();
        $allExcluded = array_values(array_unique(array_merge($bookedIds, $this->allUsedUnitIds())));

        return ProductUnit::query()
            ->select('id', 'serial_number', 'product_id', 'product_variation_id', 'status')
            ->whereNotIn('status', [ProductUnit::STATUS_MAINTENANCE, ProductUnit::STATUS_RETIRED])
            ->whereNotIn('condition', ['broken', 'lost'])
            ->where('product_id', $productId)
            ->when($variationId, fn ($q) => $q->where('product_variation_id', $variationId))
            ->when(!empty($allExcluded), fn ($q) => $q->whereNotIn('id', $allExcluded))
            ->get();
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
        // Cache key includes used unit ids so avail counts refresh after add/remove.
        $cacheKey = md5(($this->start_date ?? '') . '|' . ($this->end_date ?? '') . '|' . ($this->record?->id ?? '') . '|' . implode(',', $this->allUsedUnitIds()));
        if ($this->catalogRowsCache !== null && ($this->catalogRowsCache['_key'] ?? null) === $cacheKey) {
            return $this->catalogRowsCache['data'];
        }
        $rows = $this->productOptions(null, null);
        $this->catalogRowsCache = ['_key' => $cacheKey, 'data' => $rows];
        return $rows;
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

        // Hard cap: a rental can never hold more units of a product than physically
        // owned. Anything beyond available is allowed (becomes empty slots → Transfer),
        // but the line quantity may not exceed the total owned pool.
        $total = $this->totalOwnedFor($productId, $variationId);
        $existingQty = 0;
        foreach ($this->items as $exIt) {
            if ($exIt['composite_id'] === $compositeId) {
                $existingQty = (int) $exIt['quantity'];
                break;
            }
        }
        $room = max(0, $total - $existingQty);
        if ($room <= 0) {
            $this->dispatch('rent-toast', message: "{$name}: maksimal {$total} unit (total dimiliki)");

            return;
        }
        $qty = min($qty, $room);

        $available = $this->fetchAvailableUnitsCached($productId, $variationId);

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

        // merge with existing line. Quantity grows by the (capped) requested amount —
        // assigning available units first, the remainder staying as empty slots — so the
        // stepper can climb up to the owned total just like the items-table qty input.
        foreach ($this->items as &$it) {
            if ($it['composite_id'] === $compositeId) {
                $it['unit_ids'] = array_values(array_merge($it['unit_ids'], $newUnitIds));
                $it['quantity'] = (int) $it['quantity'] + $qty;
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
     * Add multiple products in one Livewire round-trip. Payload shape:
     *   [{ "id": "<composite_id>", "qty": <int> }, ...]
     * Used by the catalog popup's optimistic-UI batching layer (Alpine).
     */
    public function addProductsBatch(array $batch): void
    {
        foreach ($batch as $entry) {
            $cid = (string) ($entry['id'] ?? '');
            $qty = max(1, (int) ($entry['qty'] ?? 1));
            if ($cid === '') continue;
            $this->addProduct($cid, $qty);
        }
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

                // Cap at the total units physically owned for this product/variation.
                $total = $this->totalOwnedFor($it['product_id'], $it['variation_id']);
                if ($total > 0 && $newQty > $total) {
                    $newQty = $total;
                    Notification::make()
                        ->title('Melebihi total unit')
                        ->body("Maksimal {$total} unit untuk produk ini (jumlah unit yang dimiliki).")
                        ->warning()
                        ->send();
                }

                if ($newQty === $oldQty) {
                    return;
                }
                if ($newQty > $oldQty) {
                    $needed = $newQty - $oldQty;
                    $available = $this->fetchAvailableUnitsCached($it['product_id'], $it['variation_id']);
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
        // Allowed in both Create (record not yet persisted) and Edit, as long as
        // the editor status is eligible. beginTransfer() will auto-save (creating
        // the rental if needed) before opening the modal.
        return in_array($this->status, self::TRANSFERABLE_STATUSES, true);
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

    /**
     * Open modal in PULL mode: tarik unit dari rental lain ke rental ini.
     * Dipakai saat row punya slot kosong karena unit lagi dipakai rental lain.
     */
    public function openPullModal(string $itemKey): void
    {
        $this->beginTransfer(null, 'pull', $itemKey);
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

        // For MOVE / SWAP: list any other eligible rentals as target.
        // For PULL: list rentals that actually HAVE units matching this row's product
        //   and overlap the current rental period.
        $pullCandidates = [];
        $pullRowInfo = null;

        if ($this->transferMode === 'pull' && $this->transferItemKey) {
            $row = collect($this->items)->firstWhere('key', $this->transferItemKey);
            if ($row) {
                $productId = (int) $row['product_id'];
                $variationId = $row['variation_id'] ? (int) $row['variation_id'] : null;
                $pullRowInfo = [
                    'product_id' => $productId,
                    'variation_id' => $variationId,
                ];

                // Use editor state dates (may differ from saved record if user changed them).
                // Fall back to record dates if state somehow empty.
                $start = $this->start_date ?: $this->record->start_date;
                $end = $this->end_date ?: $this->record->end_date;
                $startCarbon = $start ? \Carbon\Carbon::parse($start) : null;
                $endCarbon = $end ? \Carbon\Carbon::parse($end) : null;

                $query = RentalItem::query()
                    ->where('rental_id', '!=', $this->record->id)
                    ->whereNotNull('product_unit_id') // ghost rows (no serial) can't be pulled from
                    ->whereHas('productUnit', function ($q) use ($productId, $variationId) {
                        $q->where('product_id', $productId);
                        if ($variationId) {
                            $q->where('product_variation_id', $variationId);
                        } else {
                            $q->whereNull('product_variation_id');
                        }
                    })
                    ->whereHas('rental', function ($q) use ($startCarbon, $endCarbon) {
                        $q->whereIn('status', self::TRANSFERABLE_STATUSES);
                        if ($startCarbon && $endCarbon) {
                            $q->where('start_date', '<', $endCarbon)
                                ->where('end_date', '>', $startCarbon);
                        }
                    })
                    ->with(['rental.customer', 'productUnit.product']);

                $pullCandidates = $query->limit(200)->get()
                    ->map(fn ($ri) => [
                        'item_id' => $ri->id,
                        'rental_id' => $ri->rental_id,
                        'label' => sprintf(
                            '%s — %s · %s · %s → %s',
                            $ri->rental->rental_code ?? '#'.$ri->rental_id,
                            $ri->rental->customer?->name ?? 'Unknown',
                            $ri->productUnit?->serial_number ?? '#'.$ri->product_unit_id,
                            optional($ri->rental->start_date)->format('Y-m-d'),
                            optional($ri->rental->end_date)->format('Y-m-d'),
                        ),
                    ])
                    ->all();

                \Illuminate\Support\Facades\Log::info('RentalEditor PULL candidate query', [
                    'rental_id' => $this->record->id,
                    'product_id' => $productId,
                    'variation_id' => $variationId,
                    'start' => $startCarbon?->toIso8601String(),
                    'end' => $endCarbon?->toIso8601String(),
                    'matches_count' => count($pullCandidates),
                ]);
            }
        }

        $targets = [];
        if ($this->transferMode !== 'pull') {
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
        }

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
            'needs_unit_pick' => $this->transferUnitId === null && $this->transferMode !== 'pull',
            'targets' => $targets,
            'target_items' => $targetItems,
            'pull_candidates' => $pullCandidates,
            'pull_row_info' => $pullRowInfo,
        ];
    }

    public function confirmTransfer(): void
    {
        if (!$this->canTransfer) {
            Notification::make()->title('Rental tidak dapat di-transfer')->danger()->send();
            return;
        }

        $service = app(RentalItemTransferService::class);

        try {
            if ($this->transferMode === 'pull') {
                // Pull = MOVE inverted. Source item lives in another rental; target = this rental.
                if (!$this->transferTargetItemId) {
                    Notification::make()->title('Pilih unit dari rental lain untuk ditarik')->danger()->send();
                    return;
                }
                $other = RentalItem::find($this->transferTargetItemId);
                if (!$other) {
                    Notification::make()->title('Item sumber tidak ditemukan')->danger()->send();
                    return;
                }
                $sourceCode = $other->rental->rental_code;
                $service->move($other, $this->record);
                $msg = "Unit ditarik dari {$sourceCode}";
            } else {
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
            }

            Notification::make()->title('Transfer berhasil')->body($msg)->success()->send();
            $this->closeTransferModal();

            // Reload editor state from DB (items may have changed).
            $this->redirect(RentalResource::getUrl('edit', ['record' => $this->record]), navigate: false);
        } catch (\Throwable $e) {
            $titleMap = ['move' => 'Gagal memindahkan unit', 'swap' => 'Gagal swap unit', 'pull' => 'Gagal menarik unit'];
            Notification::make()
                ->title($titleMap[$this->transferMode] ?? 'Gagal transfer')
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

        $allUsed = $this->allUsedUnitIds($this->unitModalKey);
        $bookedIds = $this->getBookedUnitIdsCached();
        $excluded = array_values(array_unique(array_merge($bookedIds, $allUsed)));

        $available = ProductUnit::query()
            ->select('id', 'serial_number', 'product_id', 'product_variation_id', 'status')
            ->whereNotIn('status', [ProductUnit::STATUS_MAINTENANCE, ProductUnit::STATUS_RETIRED])
            ->whereNotIn('condition', ['broken', 'lost'])
            ->where('product_id', $row['product_id'])
            ->when($row['variation_id'], fn ($q) => $q->where('product_variation_id', $row['variation_id']))
            ->when(!empty($excluded), fn ($q) => $q->whereNotIn('id', $excluded))
            ->get();

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
            $previousStatus = $this->record->getOriginal('status');
            $this->record->fill($payload);
            $this->record->saveQuietly();

            if ($previousStatus !== Rental::STATUS_CONFIRMED && $this->record->status === Rental::STATUS_CONFIRMED && $this->record->customer) {
                $this->record->customer->notify(new \App\Notifications\BookingConfirmedNotification($this->record));
            }
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
