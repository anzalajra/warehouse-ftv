<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Resources\Rentals\RentalResource;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Rental;
use App\Services\JournalService;
use App\Services\RentalAccountingService;
use App\Services\RentalOccupancyService;
use App\Services\RentalValidationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

class ProcessReturn extends Page
{
    use WithFileUploads;

    protected static string $resource = RentalResource::class;

    public ?Rental $rental = null;

    public ?Delivery $delivery = null;

    // ---- Item editor state ----
    public ?int $editingId = null;

    public ?string $editCondition = 'good';

    public ?string $editNotes = null;

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $editPhotos = [];

    public array $editExistingPhotos = [];

    public function getView(): string
    {
        return 'filament.resources.rentals.pages.return-operation';
    }

    public function mount(int|string $record): void
    {
        $this->rental = Rental::with([
            'customer',
            'items.productUnit.product',
            'items.rentalItemKits.unitKit',
            'deliveries.items.rentalItem.productUnit.product',
            'deliveries.items.rentalItemKit.unitKit',
        ])->findOrFail($record);

        $this->rental->checkAndUpdateLateStatus();
        $this->rental->refresh();

        $this->rental->createDeliveries();

        // Active (not completed) IN delivery, else latest IN delivery.
        $this->delivery = $this->rental->deliveries()
            ->with(['items.rentalItem.productUnit.product', 'items.rentalItemKit.unitKit'])
            ->where('type', Delivery::TYPE_IN)
            ->where('status', '!=', Delivery::STATUS_COMPLETED)
            ->first();

        if (! $this->delivery) {
            $this->delivery = $this->rental->deliveries()
                ->with(['items.rentalItem.productUnit.product', 'items.rentalItemKit.unitKit'])
                ->where('type', Delivery::TYPE_IN)
                ->latest()
                ->first();
        }

        if (! in_array($this->rental->status, [Rental::STATUS_ACTIVE, Rental::STATUS_LATE_RETURN, Rental::STATUS_PARTIAL_RETURN])) {
            Notification::make()
                ->title('Cannot return this rental')
                ->body('This rental is not in active, partial return, or late return status.')
                ->danger()
                ->send();

            $this->redirect(RentalResource::getUrl('index'));
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Return Operation - '.$this->rental->rental_code;
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /* ============================================================
       Read helpers
       ============================================================ */

    public function getDeliveryItems()
    {
        return $this->delivery->items()
            ->with([
                'rentalItem.productUnit.product',
                'rentalItem.productUnit.variation',
                'rentalItemKit.unitKit',
            ])
            ->get()
            ->sortBy(function ($item) {
                $name = $item->rentalItem?->productUnit?->product?->name
                    ?? $item->rentalItem?->product?->name
                    ?? $item->rentalItemKit?->unitKit?->name
                    ?? '';
                $variation = $item->rentalItem?->productUnit?->variation?->name
                    ?? $item->rentalItem?->productVariation?->name
                    ?? '';

                return mb_strtolower($name).'|'.mb_strtolower($variation).'|'
                    .str_pad((string) ($item->rental_item_id ?? 0), 12, '0', STR_PAD_LEFT).'|'
                    .($item->rental_item_kit_id === null ? '0' : '1');
            })->values();
    }

    public function allItemsChecked(): bool
    {
        return $this->delivery->items->where('is_checked', false)->count() === 0;
    }

    public function canValidateReturn(): bool
    {
        return $this->allItemsChecked();
    }

    public function checkedCount(): int
    {
        return $this->delivery->items->where('is_checked', true)->count();
    }

    public function totalCount(): int
    {
        return $this->delivery->items->count();
    }

    public function defaultWaiveRemainingFee(): bool
    {
        $ids = $this->delivery->items()->where('is_checked', false)->whereNull('rental_item_kit_id')->pluck('rental_item_id');

        return $ids->isNotEmpty() && $this->rental->items()->whereIn('id', $ids)->where('late_fee_waived', false)->doesntExist();
    }

    public function defaultPartialDueAt(): string
    {
        $ids = $this->delivery->items()->where('is_checked', false)
            ->whereNull('rental_item_kit_id')->pluck('rental_item_id');
        $latestDue = $this->rental->items()->whereIn('id', $ids)->get()
            ->map(fn ($item) => RentalOccupancyService::dueAt($item))
            ->sortDesc()->first() ?? $this->rental->end_date;

        return $latestDue->isFuture() ? $latestDue->format('Y-m-d\TH:i') : now()->addDay()->format('Y-m-d\TH:i');
    }

    public function customerHistory()
    {
        return Rental::where('user_id', $this->rental->user_id)
            ->withCount('items')
            ->latest('start_date')
            ->limit(6)
            ->get();
    }

    /** Financial figures for the settlement modal. */
    public function settlementData(): array
    {
        $lateFee = $this->rental->currentLateFee();
        $deposit = (float) $this->rental->security_deposit_amount;

        return [
            'late_fee' => $lateFee,
            'late_fee_breakdown' => $this->rental->lateFeeBreakdown(),
            'deposit' => $deposit,
            'deposit_status' => $this->rental->security_deposit_status,
            'total' => (float) $this->rental->total,
            'rental_revenue' => (float) $this->rental->total - $deposit - $lateFee,
            'can_settle_deposit' => $deposit > 0 && $this->rental->security_deposit_status !== 'refunded',
        ];
    }

    /* ============================================================
       Mutations
       ============================================================ */

    protected function resolveCondition(DeliveryItem $record): string
    {
        if ($record->condition) {
            return $record->condition;
        }

        if ($record->rentalItemKit) {
            return $record->rentalItemKit->unitKit->condition ?? 'good';
        }

        return $record->rentalItem->productUnit->condition ?? 'good';
    }

    public function quickCheck(int $id): void
    {
        $record = $this->delivery->items()->with(['rentalItem.productUnit', 'rentalItemKit.unitKit'])->find($id);
        if (! $record) {
            return;
        }

        $condition = $this->resolveCondition($record);
        $record->update(['is_checked' => true, 'condition' => $condition]);
        $this->syncConditionToMaster($record, $condition, true);

        $this->delivery->refresh();
    }

    /**
     * Items the scanner can match against — every delivery item EXCEPT kits
     * flagged `auto_scan_with_parent` (those ride along with their parent unit).
     *
     * @return array<int, array{id:int, name:string, serial:?string, type:string, checked:bool}>
     */
    public function scannableList(): array
    {
        return $this->getDeliveryItems()
            ->reject(fn (DeliveryItem $it) => $it->rentalItemKit && $it->rentalItemKit->unitKit?->auto_scan_with_parent)
            ->map(function (DeliveryItem $it) {
                $isKit = $it->rentalItemKit !== null;

                return [
                    'id' => $it->id,
                    'name' => $this->itemLabel($it),
                    'serial' => $isKit
                        ? $it->rentalItemKit->unitKit->serial_number
                        : $it->rentalItem->productUnit->serial_number,
                    'type' => $isKit ? 'kit' : 'unit',
                    'checked' => (bool) $it->is_checked,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Scan a code (or manually typed value) and check the matching item.
     *
     * @return array<string, mixed>
     */
    public function scanByCode(string $raw, bool $cascade = true, bool $manual = false): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['status' => 'foreign'];
        }

        if ($manual) {
            $serial = $raw;
        } else {
            $serial = app(\App\Services\UnitCodeService::class)->decode($raw);
            if ($serial === null) {
                return ['status' => 'foreign'];
            }
        }

        $items = $this->getDeliveryItems();
        $needle = mb_strtolower($serial);

        $match = $items->first(function (DeliveryItem $it) use ($needle) {
            return ! $it->rentalItemKit
                && mb_strtolower((string) $it->rentalItem->productUnit->serial_number) === $needle;
        });

        if (! $match) {
            $match = $items->first(function (DeliveryItem $it) use ($needle) {
                return $it->rentalItemKit
                    && mb_strtolower((string) $it->rentalItemKit->unitKit->serial_number) === $needle;
            });
        }

        if (! $match && $manual) {
            $match = $items->first(function (DeliveryItem $it) use ($needle) {
                return str_contains(mb_strtolower($this->itemLabel($it)), $needle);
            });
        }

        if (! $match) {
            return ['status' => 'notfound', 'serial' => $serial];
        }

        $label = $this->itemLabel($match);

        if ($match->is_checked) {
            return ['status' => 'already', 'label' => $label];
        }

        $this->quickCheck($match->id);
        $checkedLabels = [$label];
        $checkedIds = [$match->id];

        if ($cascade && ! $match->rentalItemKit) {
            $kits = $items->filter(function (DeliveryItem $it) use ($match) {
                return $it->rentalItemKit
                    && $it->rental_item_id === $match->rental_item_id
                    && ! $it->is_checked
                    && $it->rentalItemKit->unitKit?->auto_scan_with_parent;
            });

            foreach ($kits as $kit) {
                $this->quickCheck($kit->id);
                $checkedLabels[] = $this->itemLabel($kit);
                $checkedIds[] = $kit->id;
            }
        }

        // checked_ids lets the Alpine scanner update its local list without a
        // second `scannableList()` round-trip (the quickCheck() above already
        // re-rendered the page's own checklist in this same request).
        return ['status' => 'ok', 'label' => $label, 'checked' => $checkedLabels, 'checked_ids' => $checkedIds];
    }

    /** Scan-to-check: check the next unchecked item, or notify when none remain. */
    public function scanNext(): void
    {
        $next = $this->getDeliveryItems()->first(fn (DeliveryItem $it) => ! $it->is_checked);

        if (! $next) {
            Notification::make()
                ->title('Nothing left to scan')
                ->body('All items are checked.')
                ->warning()
                ->send();

            return;
        }

        $this->quickCheck($next->id);

        Notification::make()
            ->title('Checked · '.$this->itemLabel($next))
            ->success()
            ->send();
    }

    public function itemLabel(DeliveryItem $item): string
    {
        if ($item->rentalItemKit) {
            return $item->rentalItemKit->unitKit->name;
        }
        $product = $item->rentalItem->productUnit->product->name ?? 'Item';
        $variation = $item->rentalItem->productUnit->variation->name ?? null;

        return $product.($variation ? ' ('.$variation.')' : '');
    }

    public function uncheckItem(int $id): void
    {
        $record = $this->delivery->items()->with('rentalItemKit')->find($id);
        if ($record) {
            $record->update(['is_checked' => false]);
            if ($record->rentalItemKit) {
                $record->rentalItemKit->update(['is_returned' => false]);
            }
            $this->delivery->refresh();
        }
    }

    public function openEditor(int $id): void
    {
        $record = $this->delivery->items()->with(['rentalItem.productUnit', 'rentalItemKit.unitKit'])->find($id);
        if (! $record) {
            return;
        }

        $this->editingId = $id;
        $this->editCondition = $this->resolveCondition($record);
        $this->editNotes = $record->notes;
        $this->editExistingPhotos = $record->photos ?? [];
        $this->editPhotos = [];
    }

    public function closeEditor(): void
    {
        $this->reset(['editingId', 'editCondition', 'editNotes', 'editPhotos', 'editExistingPhotos']);
        $this->editCondition = 'good';
    }

    public function removeExistingPhoto(int $index): void
    {
        if (isset($this->editExistingPhotos[$index])) {
            Storage::disk('public')->delete($this->editExistingPhotos[$index]);
            unset($this->editExistingPhotos[$index]);
            $this->editExistingPhotos = array_values($this->editExistingPhotos);
        }
    }

    public function saveEditor(): void
    {
        $record = $this->delivery->items()->with(['rentalItem.productUnit', 'rentalItemKit.unitKit'])->find($this->editingId);
        if (! $record) {
            return;
        }

        $photos = $this->editExistingPhotos;
        foreach ($this->editPhotos as $upload) {
            $photos[] = $upload->store('delivery-photos/'.$this->delivery->id, 'public');
        }

        $record->update([
            'condition' => $this->editCondition,
            'photos' => $photos ?: null,
            'notes' => $this->editNotes,
            'is_checked' => true,
        ]);

        $this->syncConditionToMaster($record, $this->editCondition, true);

        $this->delivery->refresh();
        $this->closeEditor();

        Notification::make()->title('Item updated')->success()->send();
    }

    public function markAllChecked(): void
    {
        foreach ($this->delivery->items as $record) {
            $condition = $this->resolveCondition($record);
            $record->update(['is_checked' => true, 'condition' => $condition]);
            $this->syncConditionToMaster($record, $condition, true);
        }

        $this->delivery->refresh();

        Notification::make()->title('All items marked as checked')->success()->send();
    }

    /**
     * Validate the return. When all items are checked this runs the financial
     * settlement and completes the rental; otherwise it processes a partial return.
     *
     * @param  array{manual_late_fee?:mixed, final_deposit_action?:string, refund_amount?:mixed}  $data
     */
    public function validateReturn(array $data = []): void
    {
        if (isset($data['manual_late_fee']) && (! is_numeric($data['manual_late_fee']) || (float) $data['manual_late_fee'] < 0)) {
            throw ValidationException::withMessages(['manual_late_fee' => 'Denda harus berupa angka nol atau lebih.']);
        }
        if (($data['final_deposit_action'] ?? null) === 'partial'
            && (! is_numeric($data['refund_amount'] ?? null)
                || (float) $data['refund_amount'] < 0
                || (float) $data['refund_amount'] > (float) $this->rental->security_deposit_amount)) {
            throw ValidationException::withMessages(['refund_amount' => 'Jumlah refund harus berada dalam batas deposit.']);
        }
        $this->delivery->refresh();
        $this->delivery->unsetRelation('items');
        if ($this->delivery->status === Delivery::STATUS_COMPLETED || $this->delivery->items()->count() === 0) {
            throw ValidationException::withMessages(['return' => 'Checklist pengembalian sudah selesai atau kosong. Muat ulang halaman.']);
        }

        if ($this->allItemsChecked()) {
            // Resolve the final late fee once: a manual value from the settlement modal
            // wins over the auto-calculated amount (so it can be adjusted or waived).
            $displayedFee = $this->rental->currentLateFee();
            $manualChanged = isset($data['manual_late_fee']) && abs((float) $data['manual_late_fee'] - $displayedFee) > 0.01;

            // Whole settlement runs in one transaction: it commits together (one round-trip
            // instead of ~a dozen) and rolls back cleanly if any journal/sync step fails.
            DB::transaction(function () use ($data, $manualChanged) {
                $locked = $this->rental->newQuery()->whereKey($this->rental->id)->lockForUpdate()->firstOrFail();
                $this->delivery->refresh();
                if ($locked->status === Rental::STATUS_COMPLETED || $this->delivery->status === Delivery::STATUS_COMPLETED || ! $this->delivery->allItemsChecked()) {
                    throw ValidationException::withMessages(['return' => 'Checklist telah berubah atau selesai diproses. Muat ulang halaman.']);
                }
                $before = $this->financialSnapshot();
                // Complete the rental first — validateReturn() persists the late fee and runs
                // the full recalculateTotal() (subtotal/discount/tax/deposit/total). Doing it
                // here means we recalc ONCE instead of the previous recalc-then-validate (which
                // ran recalculateTotal() twice). $this->rental->total is correct afterwards.
                $this->delivery->complete();
                $this->rental->load('items.deliveryItems.delivery');
                $autoFee = $this->rental->calculateOverdueFee();
                if ($manualChanged) {
                    $this->rental->late_fee_adjustment = (float) $data['manual_late_fee'] - $autoFee;
                }
                $this->rental->validateReturn($this->rental->currentLateFee());
                $this->postReturnFinancialAdjustments($before);

                // Recognize rental revenue — ONCE, and only under IFRS/ASC (SAK recognizes
                // it at invoice issuance). Idempotent via rentals.revenue_recognized_at, so
                // reopening + re-completing never double-posts. Deposit + PPN + late fee are
                // excluded (they have their own journals).
                RentalAccountingService::postRevenueRecognition($this->rental);

                // Deposit settlement.
                if (isset($data['final_deposit_action']) && $this->rental->security_deposit_amount > 0) {
                    $action = $data['final_deposit_action'];
                    $depositAmount = (float) $this->rental->security_deposit_amount;

                    if ($action === 'refund') {
                        $this->rental->security_deposit_status = 'refunded';
                        JournalService::recordSimpleTransaction('SECURITY_DEPOSIT_OUT', $this->rental, $depositAmount, 'Full deposit refund');
                    } elseif ($action === 'forfeit') {
                        $this->rental->security_deposit_status = 'forfeited';
                        JournalService::recordSimpleTransaction('SECURITY_DEPOSIT_DEDUCTION', $this->rental, $depositAmount, 'Full deposit forfeiture');
                    } elseif ($action === 'partial') {
                        $this->rental->security_deposit_status = 'partial_refunded';
                        $refundAmount = (float) ($data['refund_amount'] ?? 0);
                        $forfeitAmount = $depositAmount - $refundAmount;

                        if ($refundAmount > 0) {
                            JournalService::recordSimpleTransaction('SECURITY_DEPOSIT_OUT', $this->rental, $refundAmount, 'Partial deposit refund');
                        }
                        if ($forfeitAmount > 0) {
                            JournalService::recordSimpleTransaction('SECURITY_DEPOSIT_DEDUCTION', $this->rental, $forfeitAmount, 'Partial deposit forfeiture');
                        }
                    }
                    $this->rental->save();
                }

                // Keep finances trackable: sync the linked invoice with the new total/late fee,
                // or issue an invoice when a balance is now owed but none was ever created.
                $this->syncInvoiceAfterReturn();

            });

            Notification::make()
                ->title('Return validated successfully')
                ->body('Rental status completed. Financials updated.')
                ->success()
                ->send();

            $this->redirect(RentalResource::getUrl('view', ['record' => $this->rental]));

            return;
        }

        // ---- PARTIAL RETURN ----
        if ($this->delivery->items()->where('is_checked', true)->count() === 0) {
            throw ValidationException::withMessages(['return' => 'Pilih minimal satu barang yang benar-benar kembali.']);
        }
        $uncheckedKitParentIds = $this->delivery->items()->where('is_checked', false)
            ->whereNotNull('rental_item_kit_id')->pluck('rental_item_id')->unique();
        if ($uncheckedKitParentIds->isNotEmpty() && $this->delivery->items()
            ->whereIn('rental_item_id', $uncheckedKitParentIds)
            ->whereNull('rental_item_kit_id')->where('is_checked', true)->exists()) {
            throw ValidationException::withMessages(['return' => 'Aksesori unit masih di luar. Biarkan unit induk belum dicentang sampai aksesori lengkap.']);
        }

        try {
            $due = Carbon::parse($data['due_at'] ?? $this->rental->end_date);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['due_at' => 'Tanggal dan jam akhir tidak valid.']);
        }
        if ($due->lte(now())) {
            throw ValidationException::withMessages(['due_at' => 'Tanggal dan jam akhir barang tersisa harus di masa depan.']);
        }
        $remainingItemIds = $this->delivery->items()->where('is_checked', false)
            ->whereNull('rental_item_kit_id')->pluck('rental_item_id')->unique();
        foreach ($this->rental->items()->whereIn('id', $remainingItemIds)->get() as $remainingItem) {
            if ($due->lt(RentalOccupancyService::dueAt($remainingItem))) {
                throw ValidationException::withMessages(['due_at' => 'Batas baru tidak boleh lebih awal dari batas yang sudah disepakati.']);
            }
        }
        if (RentalValidationService::isHoliday($due, RentalValidationService::getHolidays())) {
            throw ValidationException::withMessages(['due_at' => 'Tanggal akhir berada pada hari libur operasional.']);
        }
        if ($operationalError = RentalValidationService::validateOperationalDateTime($due, RentalValidationService::getSchedule())) {
            throw ValidationException::withMessages(['due_at' => $operationalError]);
        }
        if (! empty($data['waive_remaining_fee']) && trim((string) ($data['extension_reason'] ?? '')) === '') {
            throw ValidationException::withMessages(['extension_reason' => 'Alasan pembebasan denda harus diisi.']);
        }

        $preview = $this->previewPartial($due->toDateTimeString());
        if ($preview['conflicts'] !== [] && (empty($data['override_conflicts']) || trim((string) ($data['override_reason'] ?? '')) === '')) {
            throw ValidationException::withMessages(['overlap' => 'Ada jadwal bentrok. Buka detail rental lalu konfirmasi override beserta alasannya.']);
        }

        $oldDisplayedFee = $this->rental->currentLateFee();

        // One transaction for the whole partial settlement (split delivery, unit-status
        // refresh/maintenance, recalc + AR sync) — commits together, rolls back on failure.
        DB::transaction(function () use ($data, $due, $oldDisplayedFee) {
            $locked = $this->rental->newQuery()->whereKey($this->rental->id)->lockForUpdate()->firstOrFail();
            $before = $this->financialSnapshot();
            $this->delivery->refresh();
            if ($locked->status === Rental::STATUS_COMPLETED || $this->delivery->status === Delivery::STATUS_COMPLETED) {
                throw ValidationException::withMessages(['return' => 'Checklist telah diproses oleh admin lain.']);
            }
            $checked = $this->delivery->items()->where('is_checked', true)->get();
            $uncheckedItems = $this->delivery->items()->where('is_checked', false)->get();
            if ($checked->isEmpty() || $uncheckedItems->isEmpty()) {
                throw ValidationException::withMessages(['return' => 'Isi checklist berubah. Muat ulang halaman.']);
            }

            $remainingUnitIds = $uncheckedItems->whereNull('rental_item_kit_id')->pluck('rental_item_id')->unique();
            $conflicts = [];
            foreach ($remainingUnitIds as $itemId) {
                $rentalItem = $this->rental->items()->findOrFail($itemId);
                foreach (RentalOccupancyService::conflictsFor($rentalItem, now(), $due) as $other) {
                    $conflicts[] = [$rentalItem, $other];
                }
            }
            $currentPairs = collect($conflicts)->map(fn ($pair) => $pair[0]->id.':'.$pair[1]->id)->sort()->values()->all();
            $acknowledgedPairs = collect($data['conflict_pairs'] ?? [])->sort()->values()->all();
            if ($conflicts && ($currentPairs !== $acknowledgedPairs || empty($data['override_conflicts']) || trim((string) ($data['override_reason'] ?? '')) === '')) {
                throw ValidationException::withMessages(['overlap' => 'Jadwal bentrok berubah. Periksa dan konfirmasi ulang.']);
            }

            $newDelivery = Delivery::create([
                'rental_id' => $this->rental->id,
                'type' => Delivery::TYPE_IN,
                'date' => now(),
                'scheduled_at' => $due,
                'address' => $this->rental->delivery_address,
                'status' => Delivery::STATUS_DRAFT,
            ]);

            foreach ($uncheckedItems as $item) {
                $item->update(['delivery_id' => $newDelivery->id]);
            }

            $this->delivery->complete();
            $this->rental->load('items.deliveryItems.delivery');
            $autoBeforeExtension = $this->rental->calculateOverdueFee();

            foreach ($remainingUnitIds as $itemId) {
                $rentalItem = $this->rental->items()->findOrFail($itemId);
                $oldDue = RentalOccupancyService::dueAt($rentalItem);
                $charge = $due->gt($oldDue)
                    ? (float) $rentalItem->daily_rate * Rental::periodsBetween($oldDue, $due, $rentalItem->rate_type ?? $this->rental->pricing_period ?? 'day')
                    : 0;
                $rentalItem->update([
                    'effective_due_at' => $due,
                    'overtime_started_at' => $rentalItem->overtime_started_at ?? now(),
                    'late_fee_waived' => (bool) ($data['waive_remaining_fee'] ?? false),
                    'extension_charge' => (float) $rentalItem->extension_charge + $charge,
                ]);
                DB::table('rental_item_extensions')->insert([
                    'rental_item_id' => $itemId, 'old_due_at' => $oldDue, 'new_due_at' => $due,
                    'started_at' => now(), 'charge' => $charge,
                    'late_fee_waived' => (bool) ($data['waive_remaining_fee'] ?? false),
                    'confirmed_by' => auth()->id(), 'reason' => $data['extension_reason'] ?? null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            foreach ($conflicts as [$item, $other]) {
                DB::table('rental_overlap_overrides')->insert([
                    'rental_item_id' => $item->id, 'conflicting_rental_item_id' => $other->id,
                    'overlap_start' => now(), 'overlap_end' => $due,
                    'confirmed_by' => auth()->id(), 'reason' => $data['override_reason'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            foreach ($this->delivery->items as $item) {
                if ($item->rental_item_kit_id) {
                    continue;
                }
                if ($item->rentalItem && $item->rentalItem->productUnit) {
                    if (in_array($item->condition, DeliveryItem::getMaintenanceConditions())) {
                        // Idempotent: reuses the ticket already opened at check time.
                        $item->rentalItem->productUnit->sendToMaintenance(
                            "Auto: {$item->condition} saat Partial Return {$this->rental->rental_code} (customer: ".($this->rental->customer?->name ?? 'Unknown').')',
                            \App\Models\MaintenanceRecord::TYPE_CORRECTIVE,
                            null,
                            null,
                            $this->rental->id,
                        );
                    } else {
                        $item->rentalItem->productUnit->refreshStatus();
                    }
                }
            }

            // Settle finances for what came back so far, then keep AR in sync. The late
            // fee is now per-item (items returned in this batch only accrue up to their
            // own check-in time), so re-running this at a later batch / final completion
            // never re-charges already-returned items.
            //
            // NOTE: revenue-recognition (RENTAL_COMPLETION) and deposit settlement journals
            // stay EXCLUSIVE to full completion — posting them per batch would double-count
            // revenue. Partial settlement is late-fee + invoice (AR) only.
            $this->rental->load('items.deliveryItems.delivery');
            $autoFee = $this->rental->calculateOverdueFee();
            if (empty($data['waive_remaining_fee'])) {
                $this->rental->late_fee_adjustment = (float) $this->rental->late_fee_adjustment
                    + max(0, $autoBeforeExtension - $autoFee);
            }
            if (isset($data['manual_late_fee']) && abs((float) $data['manual_late_fee'] - $oldDisplayedFee) > 0.01) {
                $this->rental->late_fee_adjustment = (float) $data['manual_late_fee'] - $autoFee;
            }
            $this->rental->late_fee = $this->rental->currentLateFee();
            $this->rental->status = Rental::STATUS_PARTIAL_RETURN;
            $this->rental->recalculateTotal(); // persists late_fee + status + recomputed total
            $this->postReturnFinancialAdjustments($before);

            $this->rental->logActivity('Partial return: batas sisa barang '.$due->format('d M Y H:i').(count($conflicts) ? '; override '.count($conflicts).' bentrok' : ''), 'status');

            $this->syncInvoiceAfterReturn('partial return');
        });

        $this->rental->refresh();

        Notification::make()
            ->title('Partial Return Processed')
            ->body('Checked items returned. Remaining items moved to a new return checklist.')
            ->warning()
            ->send();

        $this->redirect(RentalResource::getUrl('return', ['record' => $this->rental]));
    }

    private function financialSnapshot(): array
    {
        return [
            'net' => (float) $this->rental->total - (float) $this->rental->security_deposit_amount
                - (float) $this->rental->ppn_amount - (float) $this->rental->late_fee,
            'ppn' => (float) $this->rental->ppn_amount,
            'late_fee' => (float) $this->rental->late_fee,
        ];
    }

    private function postReturnFinancialAdjustments(array $before): void
    {
        if (! $this->rental->invoice_id) {
            return; // First invoice issuance posts all components once.
        }

        RentalAccountingService::postDiscountAdjustment($this->rental, $before['net'], $before['ppn']);
        RentalAccountingService::postLateFee($this->rental, round((float) $this->rental->late_fee - $before['late_fee'], 2));
    }

    /** Conflict preview for the partial-return modal, recalculated at save time. */
    public function previewPartial(string $dueAt): array
    {
        try {
            $due = Carbon::parse($dueAt);
        } catch (\Throwable) {
            return ['conflicts' => [], 'extension_charge' => 0];
        }
        $result = [];
        $extensionCharge = 0;
        $ids = $this->delivery->items()->where('is_checked', false)->whereNull('rental_item_kit_id')->pluck('rental_item_id')->unique();
        foreach ($ids as $id) {
            $item = $this->rental->items()->with('productUnit.product')->find($id);
            if (! $item) {
                continue;
            }
            $oldDue = RentalOccupancyService::dueAt($item);
            if ($due->gt($oldDue)) {
                $extensionCharge += (float) $item->daily_rate * Rental::periodsBetween($oldDue, $due, $item->rate_type ?? $this->rental->pricing_period ?? 'day');
            }
            foreach (RentalOccupancyService::conflictsFor($item, now(), $due) as $other) {
                $result[] = [
                    'item_id' => $item->id,
                    'pair' => $item->id.':'.$other->id,
                    'serial' => $item->productUnit?->serial_number,
                    'rental_code' => $other->rental->rental_code,
                    'customer' => $other->rental->customer?->name,
                    'start' => $other->rental->start_date->format('d M Y H:i'),
                    'end' => RentalOccupancyService::occupiedUntil($other)?->format('d M Y H:i') ?? 'Masih di luar',
                    'url' => RentalResource::getUrl('view', ['record' => $other->rental_id]),
                ];
            }
        }

        return ['conflicts' => $result, 'extension_charge' => round($extensionCharge, 2)];
    }

    /**
     * Keep finances trackable once a return is validated.
     *
     *  - If the rental already has an invoice, recalculate it so the late fee / new total
     *    (and any PAID → PARTIAL reopen) flows into Accounts Receivable.
     *  - If no invoice exists but a balance is now owed (typically the late fee), issue one
     *    so it surfaces in the Invoices list / Accounts Receivable instead of being stranded
     *    on the rental row where nothing tracks the outstanding payment.
     */
    protected function syncInvoiceAfterReturn(string $context = 'on return'): void
    {
        $result = $this->rental->syncOutstandingInvoice($context);

        if ($result['reopened']) {
            Notification::make()
                ->title('Invoice reopened')
                ->body('Late fee added — invoice now has an outstanding balance to collect.')
                ->warning()
                ->send();
        } elseif ($result['action'] === 'created' && $result['invoice']) {
            Notification::make()
                ->title('Invoice issued')
                ->body('Outstanding balance (incl. late fee) — invoice '.$result['invoice']->number.' created. Collect it from Finance → Accounts Receivable.')
                ->success()
                ->send();
        }
    }

    /* ============================================================
       Document / link helpers
       ============================================================ */

    public function whatsappReminderUrl(): ?string
    {
        if (! \App\Models\Setting::get('whatsapp_enabled', true)) {
            return null;
        }

        $customer = $this->rental->customer;
        if (empty($customer->phone)) {
            return null;
        }

        $pdfLink = \Illuminate\Support\Facades\URL::signedRoute('public-documents.rental.checklist', ['rental' => $this->rental]);

        $message = \App\Helpers\WhatsAppHelper::parseTemplate('whatsapp_template_rental_return', [
            'customer_name' => $customer->name,
            'rental_ref' => $this->rental->rental_code,
            'return_date' => ($this->rental->nextOutstandingDueAt() ?? $this->rental->end_date)->format('d M Y H:i'),
            'link_pdf' => $pdfLink,
            'company_name' => \App\Models\Setting::get('site_name', 'Gearent'),
        ]);

        return \App\Helpers\WhatsAppHelper::getLink($customer->phone, $message);
    }

    public function downloadChecklist()
    {
        $this->rental->load(['customer', 'items.productUnit.product', 'items.rentalItemKits.unitKit']);
        $pdf = Pdf::loadView('pdf.checklist-form', ['rental' => $this->rental]);

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'Checklist-'.$this->rental->rental_code.'.pdf'
        );
    }

    public function downloadDeliveryNote()
    {
        $this->delivery->load(['rental.customer', 'items.rentalItem.productUnit.product', 'items.rentalItemKit.unitKit', 'checkedBy']);
        $pdf = Pdf::loadView('pdf.delivery-note', ['delivery' => $this->delivery]);

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $this->delivery->delivery_number.'.pdf'
        );
    }

    public function deliveryDocsUrl(): string
    {
        return RentalResource::getUrl('delivery', ['record' => $this->rental]);
    }

    public function customerUrl(): string
    {
        return route('filament.admin.resources.customers.edit', $this->rental->user_id);
    }

    /* ============================================================
       Internal
       ============================================================ */

    protected function syncConditionToMaster(DeliveryItem $record, string $condition, bool $isReturn = true): void
    {
        $isMaintenance = in_array($condition, DeliveryItem::getMaintenanceConditions());
        $updates = ['condition' => $condition];

        if ($isMaintenance) {
            $baseNotes = $record->rentalItemKit
                ? $record->rentalItemKit->unitKit->notes
                : $record->rentalItem->productUnit->notes;
            $updates['notes'] = $baseNotes."\n[AUTO] Marked as {$condition} during Return.";
        }

        $customer = $this->rental->customer?->name ?? 'Unknown';

        if ($record->rentalItemKit) {
            $kit = $record->rentalItemKit->unitKit;
            $record->rentalItemKit->update(['condition_in' => $condition, 'is_returned' => true]);
            $kit->update($updates);

            // Kit damage opens a kit-level ticket (tracked + costable) but does not
            // pull the parent unit out of availability — see ProductUnit::refreshStatus().
            if ($isMaintenance) {
                $kit->unit?->sendToMaintenance(
                    "Auto: kit {$kit->name} {$condition} saat Return {$this->rental->rental_code} (customer: {$customer})",
                    \App\Models\MaintenanceRecord::TYPE_CORRECTIVE,
                    $kit->id,
                    null,
                    $this->rental->id,
                );
            }
        } else {
            $unit = $record->rentalItem->productUnit;
            $unit->update($updates);

            // Condition is persisted first so refreshStatus() (inside sendToMaintenance)
            // sees broken/lost and lands the unit in MAINTENANCE with an open ticket.
            if ($isMaintenance) {
                $unit->sendToMaintenance(
                    "Auto: {$condition} saat Return {$this->rental->rental_code} (customer: {$customer})",
                    \App\Models\MaintenanceRecord::TYPE_CORRECTIVE,
                    null,
                    null,
                    $this->rental->id,
                );
            }
        }
    }

    /**
     * Units flagged for maintenance based on the conditions recorded so far
     * (broken/lost). Pulled fresh by the Validate modal so the confirmation
     * banner is accurate the moment it opens — no page refresh required.
     *
     * @return array{count:int, labels:array<int,string>}
     */
    public function maintenanceSummary(): array
    {
        $maintenanceConditions = DeliveryItem::getMaintenanceConditions();

        $affected = $this->getDeliveryItems()
            ->filter(fn (DeliveryItem $it) => $it->condition && in_array($it->condition, $maintenanceConditions));

        return [
            'count' => $affected->count(),
            'labels' => $affected->map(fn (DeliveryItem $it) => $this->itemLabel($it))->values()->all(),
        ];
    }
}
