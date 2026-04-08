<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Resources\Rentals\RentalResource;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Rental;
use App\Models\Quotation;
use App\Services\JournalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

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
            'items.productUnit.product',
            'items.rentalItemKits.unitKit'
        ])->findOrFail($record);
    }

    public function getTitle(): string|Htmlable
    {
        return 'View Rental - ' . $this->rental->rental_code;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('send_whatsapp')
                    ->label('via WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->visible(fn () => \App\Models\Setting::get('whatsapp_enabled', true))
                    ->disabled(fn () => empty($this->rental->user->phone))
                    ->tooltip(fn () => empty($this->rental->user->phone) ? 'Customer phone number is missing' : null)
                    ->url(function () {
                        $rental = $this->rental;
                        $customer = $rental->user;
                        
                        if (empty($customer->phone)) {
                            return '#';
                        }
                        
                        $itemsList = $rental->items->map(function ($item) {
                             return "- " . $item->productUnit->product->name . " (" . $item->productUnit->unit_code . ")";
                        })->join("\n");
                        
                        $pdfLink = \Illuminate\Support\Facades\URL::signedRoute('public-documents.rental.checklist', ['rental' => $rental]);
                        
                        $data = [
                            'customer_name' => $customer->name,
                            'rental_ref' => $rental->rental_code,
                            'items_list' => $itemsList,
                            'pickup_date' => \Carbon\Carbon::parse($rental->start_date)->format('d M Y H:i'),
                            'return_date' => \Carbon\Carbon::parse($rental->end_date)->format('d M Y H:i'),
                            'link_pdf' => $pdfLink,
                            'company_name' => \App\Models\Setting::get('site_name', 'Gearent'),
                        ];
                        
                        $message = \App\Helpers\WhatsAppHelper::parseTemplate('whatsapp_template_rental_detail', $data);
                        
                        return \App\Helpers\WhatsAppHelper::getLink($customer->phone, $message);
                    })
                    ->openUrlInNewTab(),
                
                Action::make('send_order_confirmed')
                    ->label('Order Confirmed')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->disabled(fn () => empty($this->rental->user->phone))
                    ->tooltip(fn () => empty($this->rental->user->phone) ? 'Customer phone number is missing' : null)
                    ->url(function () {
                        $rental = $this->rental;
                        $customer = $rental->user;

                        if (empty($customer->phone)) {
                            return '#';
                        }

                        $rentalDetailUrl = route('customer.rental.detail', $rental->id);

                        $defaultTemplate = "Halo [customer_name], pesanan Anda [rental_code] telah dikonfirmasi.\n\nSilakan cek detail rental Anda di:\n[my_rental]";
                        $template = \App\Models\Setting::get('order_confirmed_wa_template', $defaultTemplate);

                        $message = str_replace(
                            ['[customer_name]', '[rental_code]', '[my_rental]'],
                            [$customer->name, $rental->rental_code, $rentalDetailUrl],
                            $template
                        );

                        return \App\Helpers\WhatsAppHelper::getLink($customer->phone, $message);
                    })
                    ->openUrlInNewTab(),

                Action::make('send_email')
                    ->label('via Email')
                    ->icon('heroicon-o-envelope')
                    ->disabled()
                    ->tooltip('Coming Soon'),
            ])
            ->label('Send')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->button(),

            ActionGroup::make([
                // Checklist Form PDF
                Action::make('download_checklist')
                    ->label('Download Checklist Form')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('gray')
                    ->action(function () {
                        $this->rental->load(['user', 'items.productUnit.product', 'items.productUnit.kits', 'items.rentalItemKits.unitKit']);
                        
                        $pdf = Pdf::loadView('pdf.checklist-form', ['rental' => $this->rental]);
                        
                        return response()->streamDownload(
                            fn () => print($pdf->output()),
                            'Checklist-' . $this->rental->rental_code . '.pdf'
                        );
                    }),

                // Make Quotation - REMOVED as per request (auto-quotation)
                /*
                Action::make('make_quotation')
                    ->label('Make Quotation')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->action(function () {
                        $quotation = Quotation::create([
                            'user_id' => $this->rental->user_id,
                            'date' => now(),
                            'valid_until' => now()->addDays(7),
                            'status' => Quotation::STATUS_ON_QUOTE,
                            'subtotal' => $this->rental->subtotal,
                            'tax' => 0,
                            'total' => $this->rental->total,
                            'notes' => $this->rental->notes,
                        ]);

                        $this->rental->update(['quotation_id' => $quotation->id]);

                        Notification::make()
                            ->title('Quotation created successfully')
                            ->success()
                            ->send();

                        return redirect()->to(QuotationResource::getUrl('edit', ['record' => $quotation]));
                    })
                    ->visible(function () {
                        // If invoice exists, do not show Make Quotation (level up)
                        if ($this->rental->invoice_id) {
                            return false;
                        }

                        if (!$this->rental->quotation_id) {
                            return true;
                        }
                        
                        $quotation = Quotation::find($this->rental->quotation_id);
                        if (!$quotation) {
                            return true;
                        }

                        return $this->rental->updated_at->gt($quotation->created_at->addMinutes(1));
                    }),
                */

                // Download Quotation
                Action::make('download_quotation')
                    ->label('Download Quotation')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->action(function () {
                        $quotation = Quotation::with(['user', 'rentals.items.productUnit.product', 'rentals.items.rentalItemKits.unitKit'])->find($this->rental->quotation_id);
                        
                        if (!$quotation) {
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
                    })
                    ->visible(function () {
                        // If invoice exists, do not show Download Quotation (level up)
                        if ($this->rental->invoice_id) {
                            return false;
                        }

                        if (!$this->rental->quotation_id) {
                            return false;
                        }

                        $quotation = Quotation::find($this->rental->quotation_id);
                        if (!$quotation) {
                            return false;
                        }

                        return !$this->rental->updated_at->gt($quotation->created_at->addMinutes(1));
                    }),

                // Download Invoice
                Action::make('download_invoice')
                    ->label('Download Invoice')
                    ->icon('heroicon-o-document-currency-dollar')
                    ->color('gray')
                    ->action(function () {
                        $invoice = \App\Models\Invoice::with(['user', 'rentals.items.productUnit.product', 'rentals.items.rentalItemKits.unitKit'])->find($this->rental->invoice_id);
                        
                        if (!$invoice) {
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
                    })
                    ->visible(fn () => !empty($this->rental->invoice_id)),
            ])
            ->label('Print')
            ->icon('heroicon-o-printer')
            ->color('gray'),

            EditAction::make()
                ->record($this->rental)
                ->visible(fn () => $this->rental->canBeEdited()),

            Action::make('rental_documents')
                ->label('Delivery')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->url(fn () => RentalResource::getUrl('documents', ['record' => $this->rental])),

            Action::make('confirm')
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

                        if (!$paymentAlreadyRecorded && empty($data['mark_dp_paid'])) {
                            Notification::make()
                                ->title('Confirmation Failed')
                                ->body('Down payment must be paid to confirm.')
                                ->danger()
                                ->send();
                            return;
                        }
                        $this->rental->update(['down_payment_status' => 'paid']);
                        
                        if (!$paymentAlreadyRecorded) {
                            // Create Income Transaction for DP
                            $dpTransaction = new \App\Models\FinanceTransaction([
                                'finance_account_id' => $data['finance_account_id'],
                                'user_id' => \Illuminate\Support\Facades\Auth::id(),
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

                    if ($shouldCreateInvoice && !$this->rental->invoice_id) {
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
                            if (!str_contains($transaction->description, 'Invoice #')) {
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
                })
                ->visible(fn () => $this->rental->status === Rental::STATUS_QUOTATION),

            Action::make('cancel_confirm')
                ->label('Cancel Confirm')
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
                })
                ->visible(fn () => $this->rental->status === Rental::STATUS_CONFIRMED),

            Action::make('pickup')
                ->label('Process Pickup')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->url(fn () => RentalResource::getUrl('pickup', ['record' => $this->rental]))
                ->visible(fn () => in_array($this->rental->status, [Rental::STATUS_CONFIRMED, Rental::STATUS_LATE_PICKUP])),

            Action::make('return')
                ->label('Process Return')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('success')
                ->url(fn () => RentalResource::getUrl('return', ['record' => $this->rental]))
                ->visible(fn () => in_array($this->rental->status, [Rental::STATUS_ACTIVE, Rental::STATUS_LATE_RETURN, Rental::STATUS_PARTIAL_RETURN])),

            Action::make('cancel')
                ->label('Cancel')
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
                })
                ->visible(fn () => in_array($this->rental->getRealTimeStatus(), [
                    Rental::STATUS_QUOTATION,
                    Rental::STATUS_LATE_PICKUP,
                ])),

            DeleteAction::make()
                ->record($this->rental)
                ->visible(fn () => in_array($this->rental->status, [
                    Rental::STATUS_CANCELLED,
                    Rental::STATUS_COMPLETED,
                ]))
                ->successRedirectUrl(RentalResource::getUrl('index')),
        ];
    }
}
