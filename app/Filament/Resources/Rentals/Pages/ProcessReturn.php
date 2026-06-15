<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Resources\Rentals\RentalResource;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\ProductUnit;
use App\Models\Rental;
use App\Services\JournalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
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
            ->orderBy('rental_item_id')
            ->orderByRaw('rental_item_kit_id IS NULL DESC')
            ->orderBy('rental_item_kit_id')
            ->get();
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
        $lateFee = $this->rental->calculateOverdueFee();
        $deposit = (float) $this->rental->security_deposit_amount;

        return [
            'late_fee' => $lateFee,
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
            }
        }

        return ['status' => 'ok', 'label' => $label, 'checked' => $checkedLabels];
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
        if ($this->allItemsChecked()) {
            // Apply manual late fee adjustment.
            if (isset($data['manual_late_fee'])) {
                $this->rental->late_fee = (float) $data['manual_late_fee'];
                $this->rental->recalculateTotal();
                $this->rental->save();
            }

            // Recognize rental revenue (excl. deposit + late fee).
            $rentalRevenue = $this->rental->total - $this->rental->security_deposit_amount - ($this->rental->late_fee ?? 0);
            if ($rentalRevenue > 0) {
                JournalService::recordSimpleTransaction(
                    'RENTAL_COMPLETION',
                    $this->rental,
                    $rentalRevenue,
                    'Revenue recognition for Rental '.$this->rental->rental_code
                );
            }

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

            $this->rental->validateReturn();
            $this->delivery->complete();

            Notification::make()
                ->title('Return validated successfully')
                ->body('Rental status completed. Financials updated.')
                ->success()
                ->send();

            $this->redirect(RentalResource::getUrl('view', ['record' => $this->rental]));

            return;
        }

        // ---- PARTIAL RETURN ----
        $newDelivery = Delivery::create([
            'rental_id' => $this->rental->id,
            'type' => Delivery::TYPE_IN,
            'date' => now(),
            'status' => Delivery::STATUS_DRAFT,
        ]);

        $uncheckedItems = $this->delivery->items()->where('is_checked', false)->get();
        foreach ($uncheckedItems as $item) {
            $item->update(['delivery_id' => $newDelivery->id]);
        }

        $this->delivery->complete();

        foreach ($this->delivery->items as $item) {
            if ($item->rental_item_kit_id) {
                continue;
            }
            if ($item->rentalItem && $item->rentalItem->productUnit) {
                if (in_array($item->condition, DeliveryItem::getMaintenanceConditions())) {
                    $item->rentalItem->productUnit->update(['status' => ProductUnit::STATUS_MAINTENANCE]);
                } else {
                    $item->rentalItem->productUnit->refreshStatus();
                }
            }
        }

        $this->rental->fresh()->update(['status' => Rental::STATUS_PARTIAL_RETURN]);
        $this->rental->refresh();

        Notification::make()
            ->title('Partial Return Processed')
            ->body('Checked items returned. Remaining items moved to a new return checklist.')
            ->warning()
            ->send();

        $this->redirect(RentalResource::getUrl('return', ['record' => $this->rental]));
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
            'return_date' => \Carbon\Carbon::parse($this->rental->end_date)->format('d M Y H:i'),
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

            if (! $record->rentalItemKit) {
                $updates['status'] = ProductUnit::STATUS_MAINTENANCE;
            }
        }

        if ($record->rentalItemKit) {
            $record->rentalItemKit->update(['condition_in' => $condition, 'is_returned' => true]);
            $record->rentalItemKit->unitKit->update($updates);
        } else {
            $record->rentalItem->productUnit->update($updates);
        }
    }
}
