<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Resources\Rentals\RentalResource;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\ProductUnit;
use App\Models\Rental;
use App\Models\UnitKit;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

class PickupOperation extends Page
{
    use WithFileUploads;

    protected static string $resource = RentalResource::class;

    public ?Rental $rental = null;

    public ?Delivery $delivery = null;

    // ---- Item editor (bottom-sheet / modal) state ----
    public ?int $editingId = null;

    public ?string $editCondition = 'good';

    public ?string $editNotes = null;

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $editPhotos = [];

    public array $editExistingPhotos = [];

    // ---- Swap modal state ----
    public ?int $swapId = null;

    public ?string $swapUnitId = null;

    public function getView(): string
    {
        return 'filament.resources.rentals.pages.pickup-operation';
    }

    public function mount(int|string $record): void
    {
        $this->rental = Rental::with([
            'customer',
            'items.productUnit.product',
            'items.productUnit.kits',
            'items.rentalItemKits',
            'deliveries.items.rentalItem.productUnit.product',
            'deliveries.items.rentalItemKit.unitKit',
        ])->findOrFail($record);

        // Update late status on mount
        $this->rental->checkAndUpdateLateStatus();
        $this->rental->refresh();

        // Always sync deliveries to ensure all kits are present
        $this->rental->createDeliveries();

        // Get delivery out
        $this->delivery = $this->rental->deliveries()
            ->with(['items.rentalItem.productUnit.product', 'items.rentalItemKit.unitKit'])
            ->where('type', Delivery::TYPE_OUT)
            ->first();

        if (! in_array($this->rental->status, [Rental::STATUS_CONFIRMED, Rental::STATUS_LATE_PICKUP])) {
            Notification::make()
                ->title('Cannot pickup this rental')
                ->body('This rental is not in confirmed or late pickup status.')
                ->danger()
                ->send();

            $this->redirect(RentalResource::getUrl('index'));
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Pickup Operation - '.$this->rental->rental_code;
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
       Read helpers used by the blade view
       ============================================================ */

    /** Ordered delivery items: unit first, then its kits. */
    public function getDeliveryItems()
    {
        return $this->delivery->items()
            ->with([
                'rentalItem.productUnit.product',
                'rentalItem.productUnit.variation',
                'rentalItem.rental',
                'rentalItemKit.unitKit',
            ])
            ->orderBy('rental_item_id')
            ->orderByRaw('rental_item_kit_id IS NULL DESC')
            ->orderBy('rental_item_kit_id')
            ->get();
    }

    public function getAvailabilityStatus(): array
    {
        // $this->rental is a Livewire public model property: between requests its
        // `items` / `productUnit` relations are rehydrated from the dehydrated SNAPSHOT,
        // not re-queried. Without forcing a fresh load here the availability is computed
        // against stale relations, so the Validate button could stay disabled until a
        // full page reload re-ran mount(). load() always re-queries.
        $this->rental->load(['items.productUnit.kits']);

        $conflicts = $this->rental->checkAvailability();

        $unavailableUnits = [];
        foreach ($this->rental->items as $item) {
            if ($item->productUnit) {
                // 1. Direct unit status
                if (in_array($item->productUnit->status, [ProductUnit::STATUS_RENTED, ProductUnit::STATUS_MAINTENANCE])) {
                    $unavailableUnits[] = $item->productUnit;
                }

                // 2. Components (kits) of this unit
                $componentIds = $item->productUnit->kits()
                    ->whereNotNull('linked_unit_id')
                    ->pluck('linked_unit_id')
                    ->toArray();

                if (! empty($componentIds)) {
                    $unavailableComponents = ProductUnit::whereIn('id', $componentIds)
                        ->whereIn('status', [ProductUnit::STATUS_RENTED, ProductUnit::STATUS_MAINTENANCE])
                        ->get();

                    foreach ($unavailableComponents as $comp) {
                        if (! collect($unavailableUnits)->contains('id', $comp->id)) {
                            $unavailableUnits[] = $comp;
                        }
                    }
                }

                // 3. Parent kits (if this unit is a component)
                $parentIds = UnitKit::where('linked_unit_id', $item->productUnit->id)
                    ->pluck('unit_id')
                    ->toArray();

                if (! empty($parentIds)) {
                    $unavailableParents = ProductUnit::whereIn('id', $parentIds)
                        ->whereIn('status', [ProductUnit::STATUS_RENTED, ProductUnit::STATUS_MAINTENANCE])
                        ->get();

                    foreach ($unavailableParents as $parent) {
                        if (! collect($unavailableUnits)->contains('id', $parent->id)) {
                            $unavailableUnits[] = $parent;
                        }
                    }
                }
            }
        }

        return [
            'available' => empty($conflicts) && empty($unavailableUnits),
            'conflicts' => $conflicts,
            'unavailable_units' => $unavailableUnits,
        ];
    }

    /** Is a specific delivery item's unit currently unavailable (rented/maintenance elsewhere)? */
    public function isItemUnavailable(DeliveryItem $item): bool
    {
        $unit = $item->rentalItem?->productUnit;
        if (! $unit) {
            return false;
        }

        return in_array($unit->status, [ProductUnit::STATUS_RENTED, ProductUnit::STATUS_MAINTENANCE]);
    }

    /**
     * Which other rentals are holding this unit over a period that overlaps this rental?
     * Used to tell the operator exactly where the conflict is coming from.
     *
     * @return \Illuminate\Support\Collection<int, array{code:string, status:string, customer:?string, start:?string, end:?string, url:string}>
     */
    public function conflictingRentalsFor(ProductUnit $unit): \Illuminate\Support\Collection
    {
        return Rental::whereHas('items', fn ($q) => $q->where('product_unit_id', $unit->id))
            ->where('id', '!=', $this->rental->id)
            ->whereIn('status', [
                Rental::STATUS_QUOTATION, Rental::STATUS_CONFIRMED, Rental::STATUS_ACTIVE,
                Rental::STATUS_LATE_PICKUP, Rental::STATUS_LATE_RETURN, Rental::STATUS_PARTIAL_RETURN,
            ])
            ->where('start_date', '<', $this->rental->end_date)
            ->where('end_date', '>', $this->rental->start_date)
            ->with('customer')
            ->orderBy('start_date')
            ->get()
            ->map(fn (Rental $r) => [
                'code' => $r->rental_code,
                'status' => $r->status,
                'customer' => $r->customer?->name,
                'start' => $r->start_date ? \Carbon\Carbon::parse($r->start_date)->format('d M Y') : null,
                'end' => $r->end_date ? \Carbon\Carbon::parse($r->end_date)->format('d M Y') : null,
                'url' => RentalResource::getUrl('edit', ['record' => $r->id]),
            ]);
    }

    /**
     * Rentals that currently hold this unit OUT — i.e. why its status is RENTED —
     * regardless of whether their period overlaps this rental's window. Used as a
     * fallback when conflictingRentalsFor() finds no date-overlap match but the unit
     * is still physically rented, so the operator is told exactly where it is.
     *
     * @return \Illuminate\Support\Collection<int, array{code:string, status:string, customer:?string, start:?string, end:?string, url:string}>
     */
    public function holdingRentalsFor(ProductUnit $unit): \Illuminate\Support\Collection
    {
        return Rental::whereHas('items', fn ($q) => $q->where('product_unit_id', $unit->id))
            ->where('id', '!=', $this->rental->id)
            ->whereIn('status', [
                Rental::STATUS_ACTIVE, Rental::STATUS_LATE_RETURN, Rental::STATUS_PARTIAL_RETURN,
            ])
            ->with('customer')
            ->orderBy('start_date')
            ->get()
            ->map(fn (Rental $r) => [
                'code' => $r->rental_code,
                'status' => $r->status,
                'customer' => $r->customer?->name,
                'start' => $r->start_date ? \Carbon\Carbon::parse($r->start_date)->format('d M Y') : null,
                'end' => $r->end_date ? \Carbon\Carbon::parse($r->end_date)->format('d M Y') : null,
                'url' => RentalResource::getUrl('edit', ['record' => $r->id]),
            ]);
    }

    public function allItemsChecked(): bool
    {
        return $this->delivery->items->where('is_checked', false)->count() === 0;
    }

    public function checkedCount(): int
    {
        return $this->delivery->items->where('is_checked', true)->count();
    }

    public function totalCount(): int
    {
        return $this->delivery->items->count();
    }

    /** Swap candidate units for a delivery item (available units of the same product). */
    public function swapCandidates(): array
    {
        if (! $this->swapId) {
            return [];
        }

        $record = $this->delivery->items()->with('rentalItem.productUnit')->find($this->swapId);
        if (! $record || ! $record->rentalItem?->productUnit) {
            return [];
        }

        $rental = $this->rental;
        $productId = $record->rentalItem->productUnit->product_id;
        $currentUnitId = $record->rentalItem->product_unit_id;

        return ProductUnit::where('product_id', $productId)
            ->whereNotIn('status', [ProductUnit::STATUS_MAINTENANCE, ProductUnit::STATUS_RETIRED])
            ->where('id', '!=', $currentUnitId)
            ->whereDoesntHave('rentalItems', function ($q) use ($rental) {
                $q->whereHas('rental', function ($r) use ($rental) {
                    $r->whereIn('status', [
                        Rental::STATUS_QUOTATION, Rental::STATUS_CONFIRMED, Rental::STATUS_ACTIVE,
                        Rental::STATUS_LATE_PICKUP, Rental::STATUS_LATE_RETURN,
                    ])
                        ->where('id', '!=', $rental->id)
                        ->where(function ($d) use ($rental) {
                            $d->where('start_date', '<', $rental->end_date)
                                ->where('end_date', '>', $rental->start_date);
                        });
                });
            })
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'serial' => $u->serial_number,
                'status' => ucfirst($u->status),
            ])
            ->toArray();
    }

    /** Customer rental history for the profile modal. */
    public function customerHistory()
    {
        return Rental::where('user_id', $this->rental->user_id)
            ->withCount('items')
            ->latest('start_date')
            ->limit(6)
            ->get();
    }

    /* ============================================================
       Mutations (Livewire actions wired from the blade)
       ============================================================ */

    public function quickCheck(int $id): void
    {
        $record = $this->delivery->items()->with('rentalItem.productUnit')->find($id);
        if (! $record) {
            return;
        }

        if ($this->isItemUnavailable($record)) {
            Notification::make()
                ->title('Unit unavailable')
                ->body('This unit is rented or in maintenance. Swap it before checking.')
                ->danger()
                ->send();

            return;
        }

        $condition = $record->condition ?? 'good';
        $record->update(['is_checked' => true, 'condition' => $condition]);
        $this->syncConditionToMaster($record, $condition);

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
     * Returns a structured result the Alpine layer toasts/animates on.
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

        // Unit-serial match first, then kit-serial. Manual entry also matches by name.
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

        if ($this->isItemUnavailable($match)) {
            return ['status' => 'unavailable', 'label' => $label];
        }

        if ($match->is_checked) {
            return ['status' => 'already', 'label' => $label];
        }

        $this->quickCheck($match->id);
        $checkedLabels = [$label];

        // Cascade: auto-check this unit's auto_scan_with_parent kits.
        if ($cascade && ! $match->rentalItemKit) {
            $kits = $items->filter(function (DeliveryItem $it) use ($match) {
                return $it->rentalItemKit
                    && $it->rental_item_id === $match->rental_item_id
                    && ! $it->is_checked
                    && $it->rentalItemKit->unitKit?->auto_scan_with_parent;
            });

            foreach ($kits as $kit) {
                if ($this->isItemUnavailable($kit)) {
                    continue;
                }
                $this->quickCheck($kit->id);
                $checkedLabels[] = $this->itemLabel($kit);
            }
        }

        return ['status' => 'ok', 'label' => $label, 'checked' => $checkedLabels];
    }

    /** Scan-to-check: check the next available unchecked item, or notify when none remain. */
    public function scanNext(): void
    {
        $next = $this->getDeliveryItems()
            ->first(fn (DeliveryItem $it) => ! $it->is_checked && ! $this->isItemUnavailable($it));

        if (! $next) {
            Notification::make()
                ->title('Nothing left to scan')
                ->body('All available items are checked.')
                ->warning()
                ->send();

            return;
        }

        $this->quickCheck($next->id);

        Notification::make()
            ->title('Checked · '.$this->itemLabel($next))
            ->body('Marked Good. Tap Edit to log damage.')
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
        $record = $this->delivery->items()->find($id);
        if ($record) {
            $record->update(['is_checked' => false]);
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
        $this->editCondition = $record->condition ?: 'good';
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
            $path = $this->editExistingPhotos[$index];
            Storage::disk('public')->delete($path);
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

        // Persist newly uploaded photos to the public disk.
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

        $this->syncConditionToMaster($record, $this->editCondition);

        $this->delivery->refresh();
        $this->closeEditor();

        Notification::make()->title('Item updated')->success()->send();
    }

    public function markAllChecked(): void
    {
        $items = $this->delivery->items;
        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($items as $record) {
            $unit = $record->rentalItem->productUnit;
            $unit->refresh();

            if (in_array($unit->status, [ProductUnit::STATUS_RENTED, ProductUnit::STATUS_MAINTENANCE])) {
                $skippedCount++;

                continue;
            }

            $condition = $record->condition ?? 'good';
            $record->update(['is_checked' => true, 'condition' => $condition]);
            $this->syncConditionToMaster($record, $condition);
            $updatedCount++;
        }

        $this->delivery->refresh();

        if ($updatedCount === 0 && $skippedCount > 0) {
            Notification::make()
                ->title('Cannot mark items as checked')
                ->body('All items are currently unavailable (rented or maintenance).')
                ->danger()
                ->send();
        } elseif ($skippedCount > 0) {
            Notification::make()
                ->title('Some items were skipped')
                ->body("Marked {$updatedCount} items as checked. {$skippedCount} skipped (unavailable).")
                ->warning()
                ->send();
        } else {
            Notification::make()->title('All items marked as checked')->success()->send();
        }
    }

    public function openSwap(int $id): void
    {
        $this->swapId = $id;
        $this->swapUnitId = null;
    }

    public function closeSwap(): void
    {
        $this->reset(['swapId', 'swapUnitId']);
    }

    public function confirmSwap(): void
    {
        if (! $this->swapId || ! $this->swapUnitId) {
            return;
        }

        $record = $this->delivery->items()->with('rentalItem.rentalItemKits')->find($this->swapId);
        if (! $record) {
            return;
        }

        $rentalItem = $record->rentalItem;
        $newUnitId = $this->swapUnitId;

        // 1. Point the rental item at the new unit.
        $rentalItem->update(['product_unit_id' => $newUnitId]);

        // 2. Remove old kits + their delivery items.
        foreach ($rentalItem->rentalItemKits as $kit) {
            $this->delivery->items()->where('rental_item_kit_id', $kit->id)->delete();
            $kit->delete();
        }

        // 3. Attach kits from the new unit.
        $rentalItem->refresh();
        $rentalItem->attachKitsFromUnit();

        // 4. Create delivery items for the new kits.
        $rentalItem->refresh();
        foreach ($rentalItem->rentalItemKits as $kit) {
            $this->delivery->items()->firstOrCreate([
                'rental_item_id' => $rentalItem->id,
                'rental_item_kit_id' => $kit->id,
            ], [
                'is_checked' => false,
                'condition' => $kit->condition_out,
            ]);
        }

        Notification::make()->title('Unit swapped successfully')->success()->send();

        $this->closeSwap();
        $this->redirect(RentalResource::getUrl('pickup', ['record' => $this->rental]));
    }

    public function validatePickup(): void
    {
        if (! $this->allItemsChecked()) {
            Notification::make()
                ->title('Cannot Validate Pickup')
                ->body('Please check all items first.')
                ->warning()
                ->send();

            return;
        }

        $status = $this->getAvailabilityStatus();
        if (! $status['available']) {
            // Spell out exactly which units clash, and with which rental, so the
            // operator can jump straight to the source instead of hunting.
            $lines = [];
            foreach ($status['unavailable_units'] as $unit) {
                $label = ($unit->product->name ?? 'Unit').' ('.$unit->serial_number.')';

                if ($unit->status === ProductUnit::STATUS_MAINTENANCE) {
                    $lines[] = $label.' — in maintenance';

                    continue;
                }

                $others = $this->conflictingRentalsFor($unit);
                if ($others->isEmpty() && $unit->status === ProductUnit::STATUS_RENTED) {
                    $others = $this->holdingRentalsFor($unit);
                }
                if ($others->isNotEmpty()) {
                    $who = $others->map(fn ($r) => $r['code'].($r['customer'] ? " ({$r['customer']})" : ''))->implode(', ');
                    $lines[] = '<strong>'.e($label).'</strong> — held by '.e($who);
                } else {
                    $lines[] = '<strong>'.e($label).'</strong> — currently '.e(strtoupper($unit->status));
                }
            }

            Notification::make()
                ->title('Cannot Validate Pickup')
                ->body($lines
                    ? 'Conflicting units:<br>'.implode('<br>', $lines)
                    : 'There are unresolved scheduling conflicts or unavailable units. Resolve them before validating.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $this->rental->validatePickup();
        $this->delivery->complete();

        Notification::make()
            ->title('Pickup validated successfully')
            ->body('Rental status changed to Active.')
            ->success()
            ->send();

        $this->redirect(RentalResource::getUrl('view', ['record' => $this->rental]));
    }

    /* ============================================================
       Document / link helpers for the toolbar actions
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

        $message = \App\Helpers\WhatsAppHelper::parseTemplate('whatsapp_template_rental_pickup', [
            'customer_name' => $customer->name,
            'rental_ref' => $this->rental->rental_code,
            'pickup_date' => \Carbon\Carbon::parse($this->rental->start_date)->format('d M Y H:i'),
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

    public function editRentalUrl(): string
    {
        return RentalResource::getUrl('edit', ['record' => $this->rental]);
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

    /** Mirror the recorded condition back onto the unit/kit master + flip to maintenance when broken/lost. */
    protected function syncConditionToMaster(DeliveryItem $record, string $condition): void
    {
        $isMaintenance = in_array($condition, DeliveryItem::getMaintenanceConditions());
        $updates = ['condition' => $condition];

        if ($isMaintenance) {
            $baseNotes = $record->rentalItemKit
                ? $record->rentalItemKit->unitKit->notes
                : $record->rentalItem->productUnit->notes;
            $updates['notes'] = $baseNotes."\n[AUTO] Marked as {$condition} during Pickup.";
        }

        $customer = $this->rental->customer?->name ?? 'Unknown';

        if ($record->rentalItemKit) {
            $kit = $record->rentalItemKit->unitKit;
            $record->rentalItemKit->update(['condition_out' => $condition]);
            $kit->update($updates);

            if ($isMaintenance) {
                $kit->unit?->sendToMaintenance(
                    "Auto: kit {$kit->name} {$condition} saat Pickup {$this->rental->rental_code} (customer: {$customer})",
                    \App\Models\MaintenanceRecord::TYPE_CORRECTIVE,
                    $kit->id,
                    null,
                    $this->rental->id,
                );
            }
        } else {
            $unit = $record->rentalItem->productUnit;
            $unit->update($updates);

            if ($isMaintenance) {
                $unit->sendToMaintenance(
                    "Auto: {$condition} saat Pickup {$this->rental->rental_code} (customer: {$customer})",
                    \App\Models\MaintenanceRecord::TYPE_CORRECTIVE,
                    null,
                    null,
                    $this->rental->id,
                );
            }
        }
    }
}
