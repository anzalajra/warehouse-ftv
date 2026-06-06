<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Resources\Rentals\RentalResource;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Helpers\WhatsAppHelper;
use App\Models\Rental;
use App\Models\Quotation;
use App\Models\Setting;
use App\Services\JournalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class ViewRental extends Page
{
    protected static string $resource = RentalResource::class;

    protected static bool $canCreateAnother = false;

    public ?Rental $rental = null;

    public function getView(): string
    {
        return 'filament.resources.rentals.pages.view-rental';
    }

    public function mount(int|string $record): void
    {
        $this->rental = Rental::with([
            'user',
            'items.productUnit.product.category',
            'items.product.category',
            'items.rentalItemKits.unitKit'
        ])->findOrFail($record);
    }

    public function getTitle(): string|Htmlable
    {
        return 'View Rental - ' . $this->rental->rental_code;
    }

    // The page renders its own design topbar (in the Blade view), so we fully
    // suppress Filament's default page header (heading + breadcrumbs + actions).
    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | URL / visibility helpers used by the custom topbar (Blade)
    |--------------------------------------------------------------------------
    */

    public function isWhatsappEnabled(): bool
    {
        return (bool) Setting::get('whatsapp_enabled', true);
    }

    public function getWhatsappUrl(): ?string
    {
        $rental = $this->rental;
        $customer = $rental->user;

        if (empty($customer?->phone)) {
            return null;
        }

        $itemsList = $rental->items->map(function ($item) {
            $name = $item->productUnit?->product?->name ?? $item->product?->name ?? '-';
            $code = $item->productUnit?->unit_code ?? '-';
            return "- " . $name . " (" . $code . ")";
        })->join("\n");

        $pdfLink = URL::signedRoute('public-documents.rental.checklist', ['rental' => $rental]);

        $data = [
            'customer_name' => $customer->name,
            'rental_ref' => $rental->rental_code,
            'items_list' => $itemsList,
            'pickup_date' => Carbon::parse($rental->start_date)->format('d M Y H:i'),
            'return_date' => Carbon::parse($rental->end_date)->format('d M Y H:i'),
            'link_pdf' => $pdfLink,
            'company_name' => Setting::get('site_name', 'Gearent'),
        ];

        $message = WhatsAppHelper::parseTemplate('whatsapp_template_rental_detail', $data);

        return WhatsAppHelper::getLink($customer->phone, $message);
    }

    public function getOrderConfirmedUrl(): ?string
    {
        $rental = $this->rental;
        $customer = $rental->user;

        if (empty($customer?->phone)) {
            return null;
        }

        $rentalDetailUrl = route('customer.rental.detail', $rental->id);

        $defaultTemplate = "Halo [customer_name], pesanan Anda [rental_code] telah dikonfirmasi.\n\nSilakan cek detail rental Anda di:\n[my_rental]";
        $template = Setting::get('order_confirmed_wa_template', $defaultTemplate);

        [$dateSearch, $dateReplace] = WhatsAppHelper::rentalDatePlaceholders($rental);
        $message = str_replace(
            array_merge(['[customer_name]', '[rental_code]', '[my_rental]'], $dateSearch),
            array_merge([$customer->name, $rental->rental_code, $rentalDetailUrl], $dateReplace),
            $template
        );

        return WhatsAppHelper::getLink($customer->phone, $message);
    }

    public function getEditUrl(): ?string
    {
        return $this->rental->canBeEdited()
            ? RentalResource::getUrl('edit', ['record' => $this->rental])
            : null;
    }

    public function getDeliveryUrl(): string
    {
        return RentalResource::getUrl('delivery', ['record' => $this->rental]);
    }

    public function getPickupUrl(): string
    {
        return RentalResource::getUrl('pickup', ['record' => $this->rental]);
    }

    public function getReturnUrl(): string
    {
        return RentalResource::getUrl('return', ['record' => $this->rental]);
    }

    public function canDownloadQuotation(): bool
    {
        if ($this->rental->invoice_id) {
            return false;
        }
        if (! $this->rental->quotation_id) {
            return false;
        }
        $quotation = Quotation::find($this->rental->quotation_id);
        if (! $quotation) {
            return false;
        }
        return ! $this->rental->updated_at->gt($quotation->created_at->addMinutes(1));
    }

    public function canDownloadInvoice(): bool
    {
        return ! empty($this->rental->invoice_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Mountable actions (modals / downloads) — triggered via wire:click="mountAction('name')"
    |--------------------------------------------------------------------------
    */

    public function downloadChecklistAction(): Action
    {
        return Action::make('downloadChecklist')
            ->action(function () {
                $this->rental->load(['user', 'items.productUnit.product', 'items.productUnit.kits', 'items.rentalItemKits.unitKit']);

                $pdf = Pdf::loadView('pdf.checklist-form', ['rental' => $this->rental]);

                return response()->streamDownload(
                    fn () => print($pdf->output()),
                    'Checklist-' . $this->rental->rental_code . '.pdf'
                );
            });
    }

    public function downloadQuotationAction(): Action
    {
        return Action::make('downloadQuotation')
            ->action(function () {
                $quotation = Quotation::with(['user', 'rentals.items.productUnit.product', 'rentals.items.rentalItemKits.unitKit'])->find($this->rental->quotation_id);

                if (! $quotation) {
                    Notification::make()
                        ->title('Quotation not found')
                        ->danger()
                        ->send();
                    return;
                }

                $pdf = Pdf::loadView('pdf.quotation', ['quotation' => $quotation]);

                return response()->streamDownload(
                    fn () => print($pdf->output()),
                    'Quotation-' . $quotation->number . '.pdf'
                );
            });
    }

    public function downloadInvoiceAction(): Action
    {
        return Action::make('downloadInvoice')
            ->action(function () {
                $invoice = \App\Models\Invoice::with(['user', 'rentals.items.productUnit.product', 'rentals.items.rentalItemKits.unitKit'])->find($this->rental->invoice_id);

                if (! $invoice) {
                    Notification::make()
                        ->title('Invoice not found')
                        ->danger()
                        ->send();
                    return;
                }

                $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);

                return response()->streamDownload(
                    fn () => print($pdf->output()),
                    'Invoice-' . $invoice->number . '.pdf'
                );
            });
    }

    public function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label('Confirm')
            ->icon('heroicon-o-check')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Confirm Rental')
            ->modalDescription(fn () => $this->rental->down_payment_amount > 0 && $this->rental->down_payment_status !== 'paid'
                ? 'This rental requires a Down Payment. Please confirm receipt to lock the schedule.'
                : ($this->rental->total == 0
                    ? 'This rental has a total value of 0. Do you want to confirm it?'
                    : 'Are you sure you want to confirm this rental? This will change status to Confirmed and allow pickup.'))
            ->form(function () {
                $form = [];

                // Check for existing payments
                $existingPayment = \App\Models\FinanceTransaction::where(function ($query) {
                    $query->where('reference_type', Rental::class)
                        ->where('reference_id', $this->rental->id);

                    if ($this->rental->quotation_id) {
                        $query->orWhere(function ($q) {
                            $q->where('reference_type', Quotation::class)
                                ->where('reference_id', $this->rental->quotation_id);
                        });
                    }
                })
                ->where('type', \App\Models\FinanceTransaction::TYPE_INCOME)
                ->sum('amount');

                $isPaidEnough = $existingPayment >= $this->rental->down_payment_amount;

                if ($this->rental->down_payment_amount > 0 && $this->rental->down_payment_status !== 'paid') {
                    $form[] = \Filament\Forms\Components\Placeholder::make('dp_info')
                        ->label('Down Payment Amount')
                        ->content('Rp ' . number_format($this->rental->down_payment_amount, 0, ',', '.'));

                    if ($isPaidEnough) {
                        $form[] = \Filament\Forms\Components\Placeholder::make('payment_detected')
                            ->label('Payment Detected')
                            ->content('A payment of Rp ' . number_format($existingPayment, 0, ',', '.') . ' has already been recorded. Confirming will mark DP as paid.')
                            ->extraAttributes(['class' => 'text-success-600 font-bold']);

                        $form[] = \Filament\Forms\Components\Hidden::make('payment_already_recorded')->default(true);
                        $form[] = \Filament\Forms\Components\Hidden::make('mark_dp_paid')->default(true);
                    } else {
                        $form[] = \Filament\Forms\Components\Toggle::make('mark_dp_paid')
                            ->label('Mark Down Payment as Paid')
                            ->helperText('Required to confirm this rental.')
                            ->required();

                        $form[] = \Filament\Forms\Components\Select::make('finance_account_id')
                            ->label('Deposit To Account')
                            ->options(\App\Models\FinanceAccount::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->visible(fn ($get) => $get('mark_dp_paid'));

                        $form[] = \Filament\Forms\Components\Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                'Cash' => 'Cash',
                                'Transfer' => 'Bank Transfer',
                                'QRIS' => 'QRIS',
                                'Credit Card' => 'Credit Card',
                            ])
                            ->required()
                            ->visible(fn ($get) => $get('mark_dp_paid'));
                    }
                }

                if ($this->rental->total == 0) {
                    $form[] = \Filament\Forms\Components\Radio::make('create_invoice_choice')
                        ->label('Create Invoice?')
                        ->options([
                            'yes' => 'Yes, create invoice',
                            'no' => 'No, skip invoice'
                        ])
                        ->default('no')
                        ->required();
                }

                return $form;
            })
            ->modalSubmitActionLabel('Yes, Confirm')
            ->action(function (array $data) {
                $dpTransaction = null;

                if ($this->rental->down_payment_amount > 0 && $this->rental->down_payment_status !== 'paid') {
                    $paymentAlreadyRecorded = $data['payment_already_recorded'] ?? false;

                    if (! $paymentAlreadyRecorded && empty($data['mark_dp_paid'])) {
                        Notification::make()
                            ->title('Confirmation Failed')
                            ->body('Down payment must be paid to confirm.')
                            ->danger()
                            ->send();
                        return;
                    }
                    $this->rental->update(['down_payment_status' => 'paid']);

                    if (! $paymentAlreadyRecorded) {
                        // Create Income Transaction for DP
                        $dpTransaction = new \App\Models\FinanceTransaction([
                            'finance_account_id' => $data['finance_account_id'],
                            'user_id' => Auth::id(),
                            'type' => \App\Models\FinanceTransaction::TYPE_INCOME,
                            'amount' => $this->rental->down_payment_amount,
                            'date' => now(),
                            'category' => 'Down Payment',
                            'description' => 'Down Payment for Rental ' . $this->rental->rental_code,
                            'payment_method' => $data['payment_method'],
                        ]);
                        // Initially link to Rental, will update to Invoice if created
                        $dpTransaction->reference()->associate($this->rental);
                        $dpTransaction->save();

                        JournalService::recordSimpleTransaction(
                            'RECEIVE_RENTAL_PAYMENT',
                            $this->rental,
                            $this->rental->down_payment_amount,
                            'Down Payment for Rental ' . $this->rental->rental_code
                        );
                    }
                }

                $this->rental->update(['status' => Rental::STATUS_CONFIRMED]);

                // Update Quotation Status
                if ($this->rental->quotation_id) {
                    $quotation = Quotation::find($this->rental->quotation_id);
                    if ($quotation) {
                        $quotation->update(['status' => Quotation::STATUS_ACCEPTED]);
                    }
                }

                // Invoice Creation Logic
                $shouldCreateInvoice = $this->rental->total > 0;
                if ($this->rental->total == 0 && isset($data['create_invoice_choice']) && $data['create_invoice_choice'] === 'yes') {
                    $shouldCreateInvoice = true;
                }

                if ($shouldCreateInvoice && ! $this->rental->invoice_id) {
                    $invoice = \App\Models\Invoice::create([
                        'user_id' => $this->rental->user_id,
                        'quotation_id' => $this->rental->quotation_id,
                        'date' => now(),
                        'due_date' => now()->addDays(7),
                        'status' => \App\Models\Invoice::STATUS_WAITING_FOR_PAYMENT,
                        'subtotal' => $this->rental->subtotal,
                        'tax_base' => $this->rental->tax_base ?? $this->rental->subtotal,
                        'ppn_rate' => $this->rental->ppn_rate ?? 0,
                        'ppn_amount' => $this->rental->ppn_amount ?? 0,
                        'tax' => $this->rental->ppn_amount ?? 0,
                        'total' => $this->rental->total,
                        'is_taxable' => $this->rental->is_taxable ?? false,
                        'price_includes_tax' => $this->rental->price_includes_tax ?? false,
                        'notes' => 'Generated from Rental ' . $this->rental->rental_code,
                    ]);

                    // Auto Journal: Rental Invoice Issued
                    JournalService::recordSimpleTransaction(
                        'RENTAL_INVOICE_ISSUED',
                        $invoice,
                        $invoice->total,
                        'Invoice Generated for Rental ' . $this->rental->rental_code
                    );

                    // Move all payments (DP, etc) from Rental/Quotation to Invoice
                    $existingTransactions = \App\Models\FinanceTransaction::where(function ($query) {
                        $query->where('reference_type', Rental::class)
                            ->where('reference_id', $this->rental->id);

                        if ($this->rental->quotation_id) {
                            $query->orWhere(function ($q) {
                                $q->where('reference_type', Quotation::class)
                                    ->where('reference_id', $this->rental->quotation_id);
                            });
                        }
                    })
                    ->where('type', \App\Models\FinanceTransaction::TYPE_INCOME)
                    ->get();

                    $totalPaid = 0;

                    foreach ($existingTransactions as $transaction) {
                        $transaction->reference()->associate($invoice);
                        if (! str_contains($transaction->description, 'Invoice #')) {
                            $transaction->description = $transaction->description . ' (Inv #' . $invoice->number . ')';
                        }
                        $transaction->save();

                        $totalPaid += $transaction->amount;
                    }

                    $invoice->paid_amount = $totalPaid;

                    // Update Status if fully paid
                    if ($invoice->paid_amount >= $invoice->total) {
                        $invoice->status = \App\Models\Invoice::STATUS_PAID;
                    } elseif ($invoice->paid_amount > 0) {
                        $invoice->status = \App\Models\Invoice::STATUS_PARTIAL;
                    }
                    $invoice->save();

                    $this->rental->update(['invoice_id' => $invoice->id]);

                    Notification::make()
                        ->title('Invoice created')
                        ->success()
                        ->send();
                }

                Notification::make()
                    ->title('Rental confirmed')
                    ->success()
                    ->send();
                $this->redirect(RentalResource::getUrl('view', ['record' => $this->rental]));
            });
    }

    public function revertAction(): Action
    {
        return Action::make('revert')
            ->label('Revert to Quotation')
            ->icon('heroicon-o-x-circle')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Revert to Quotation')
            ->modalDescription('Are you sure you want to revert this rental status to Quotation?')
            ->modalSubmitActionLabel('Yes, Revert')
            ->action(function () {
                $this->rental->update([
                    'status' => Rental::STATUS_QUOTATION,
                    'checklist_downloaded_at' => null,
                    'permit_template_clicked_at' => null,
                ]);
                Notification::make()
                    ->title('Rental status reverted to Quotation')
                    ->success()
                    ->send();
                $this->redirect(RentalResource::getUrl('view', ['record' => $this->rental]));
            });
    }

    public function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel Rental')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancel Rental')
            ->modalDescription('Are you sure you want to cancel this rental?')
            ->form([
                Textarea::make('cancel_reason')
                    ->label('Reason for cancellation')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data) {
                $this->rental->cancelRental($data['cancel_reason']);

                Notification::make()
                    ->title('Rental cancelled')
                    ->success()
                    ->send();

                $this->redirect(RentalResource::getUrl('view', ['record' => $this->rental]));
            });
    }

    public function deleteAction(): Action
    {
        return DeleteAction::make()
            ->record($this->rental)
            ->successRedirectUrl(RentalResource::getUrl('index'));
    }
}
