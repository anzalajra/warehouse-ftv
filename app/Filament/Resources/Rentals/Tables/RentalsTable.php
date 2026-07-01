<?php

namespace App\Filament\Resources\Rentals\Tables;

use App\Filament\Resources\Rentals\RentalResource;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Delivery;
use App\Models\Rental;
use App\Models\Quotation;
use App\Models\FinanceTransaction;
use App\Models\FinanceAccount;
use App\Services\JournalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RentalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rental_code')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->label('Customer'),
                TextColumn::make('start_date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('end_date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Rental $record): string => Rental::getStatusOptions()[$record->getRealTimeStatus()] ?? $record->getRealTimeStatus())
                    ->color(fn (Rental $record): string => Rental::getStatusColor($record->getRealTimeStatus()))
                    ->toggleable(),
                // Secondary signal: some items already returned, others still pending.
                // Survives the status flipping to Late Return once the end date passes.
                TextColumn::make('partial_return_flag')
                    ->label('')
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->getStateUsing(fn (Rental $record): ?string => $record->hasPendingPartialReturn() ? 'Partial return' : null)
                    ->toggleable(),
                TextColumn::make('total')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('sm'),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->multiple()
                    ->options(Rental::getStatusOptions()),
                \Filament\Tables\Filters\Filter::make('start_date')
                    ->schema([
                        DatePicker::make('from')->label('Start From'),
                        DatePicker::make('until')->label('Start Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('start_date', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('start_date', '<=', $date));
                    }),
            ])
            ->actions([
                // Confirm Button
                Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Rental')
                    ->modalDescription(fn (Rental $record) => $record->down_payment_amount > 0 && $record->down_payment_status !== 'paid' 
                        ? 'This rental requires a Down Payment. Please confirm receipt to lock the schedule.' 
                        : ($record->total == 0 
                            ? 'This rental has a total value of 0. Do you want to confirm it?'
                            : 'Are you sure you want to confirm this rental? This will change status to Confirmed and allow pickup.'))
                    ->form(function (Rental $record) {
                        $form = [];
                        
                        // Check for existing payments
                        $existingPayment = FinanceTransaction::where(function ($query) use ($record) {
                            $query->where('reference_type', Rental::class)
                                ->where('reference_id', $record->id);
                            
                            if ($record->quotation_id) {
                                $query->orWhere(function ($q) use ($record) {
                                    $q->where('reference_type', Quotation::class)
                                        ->where('reference_id', $record->quotation_id);
                                });
                            }
                        })
                        ->where('type', FinanceTransaction::TYPE_INCOME)
                        ->sum('amount');
                        $isPaidEnough = $existingPayment >= $record->down_payment_amount;

                        if ($record->down_payment_amount > 0 && $record->down_payment_status !== 'paid') {
                            $form[] = \Filament\Forms\Components\Placeholder::make('dp_info')
                                ->label('Down Payment Amount')
                                ->content('Rp ' . number_format($record->down_payment_amount, 0, ',', '.'));
                            
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
                        
                        if ($record->total == 0) {
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
                    ->action(function (Rental $record, array $data) {
                        $dpTransaction = null;
                        
                        if ($record->down_payment_amount > 0 && $record->down_payment_status !== 'paid') {
                            $paymentAlreadyRecorded = $data['payment_already_recorded'] ?? false;

                            if (!$paymentAlreadyRecorded && empty($data['mark_dp_paid'])) {
                                Notification::make()
                                    ->title('Confirmation Failed')
                                    ->body('Down payment must be paid to confirm.')
                                    ->danger()
                                    ->send();
                                return;
                            }
                            $record->update(['down_payment_status' => 'paid']);
                            
                            if (!$paymentAlreadyRecorded) {
                                // Create Income Transaction for DP
                                $dpTransaction = new \App\Models\FinanceTransaction([
                                    'finance_account_id' => $data['finance_account_id'],
                                    'user_id' => Auth::id(),
                                    'type' => \App\Models\FinanceTransaction::TYPE_INCOME,
                                    'amount' => $record->down_payment_amount,
                                    'date' => now(),
                                    'category' => 'Down Payment',
                                    'description' => 'Down Payment for Rental ' . $record->rental_code,
                                    'payment_method' => $data['payment_method'],
                                ]);
                                // Initially link to Rental, will update to Invoice if created
                                $dpTransaction->reference()->associate($record);
                                $dpTransaction->save();
                            }
                        }

                        $record->update(['status' => Rental::STATUS_CONFIRMED]);
                        
                        // Update Quotation Status
                        if ($record->quotation_id) {
                            $quotation = Quotation::find($record->quotation_id);
                            if ($quotation) {
                                $quotation->update(['status' => Quotation::STATUS_ACCEPTED]);
                            }
                        }

                        // Invoice Creation Logic
                        $shouldCreateInvoice = $record->total > 0;
                        if ($record->total == 0 && isset($data['create_invoice_choice']) && $data['create_invoice_choice'] === 'yes') {
                            $shouldCreateInvoice = true;
                        }

                        if ($shouldCreateInvoice && !$record->invoice_id) {
                            $invoice = \App\Models\Invoice::create([
                                'user_id' => $record->user_id,
                                'quotation_id' => $record->quotation_id,
                                'date' => now(),
                                'due_date' => now()->addDays(7),
                                'status' => \App\Models\Invoice::STATUS_WAITING_FOR_PAYMENT,
                                'subtotal' => $record->subtotal,
                                'tax' => 0,
                                'total' => $record->total,
                                'notes' => 'Generated from Rental ' . $record->rental_code,
                            ]);
                            
                            // Move all payments (DP, etc) from Rental/Quotation to Invoice
                            $existingTransactions = FinanceTransaction::where(function ($query) use ($record) {
                                $query->where('reference_type', Rental::class)
                                    ->where('reference_id', $record->id);
                                
                                if ($record->quotation_id) {
                                    $query->orWhere(function ($q) use ($record) {
                                        $q->where('reference_type', Quotation::class)
                                            ->where('reference_id', $record->quotation_id);
                                    });
                                }
                            })
                            ->where('type', FinanceTransaction::TYPE_INCOME)
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
                            
                            $record->update(['invoice_id' => $invoice->id]);
                            
                            Notification::make()
                                ->title('Invoice created')
                                ->success()
                                ->send();
                        }

                        Notification::make()
                            ->title('Rental confirmed')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Rental $record) => $record->status === Rental::STATUS_QUOTATION),

                // Pickup button
                Action::make('pickup')
                    ->label('Pickup')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->url(fn (Rental $record) => RentalResource::getUrl('pickup', ['record' => $record]))
                    ->visible(fn (Rental $record) => in_array($record->getRealTimeStatus(), [
                        Rental::STATUS_CONFIRMED,
                        Rental::STATUS_LATE_PICKUP,
                    ])),

                // Return button
                Action::make('return')
                    ->label('Return')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('info')
                    ->url(fn (Rental $record) => RentalResource::getUrl('return', ['record' => $record]))
                    ->visible(fn (Rental $record) => in_array($record->getRealTimeStatus(), [
                        Rental::STATUS_ACTIVE,
                        Rental::STATUS_LATE_RETURN,
                    ])),

                // Reopen a completed rental (admin only) so items/total can be corrected
                // and the return redone.
                Action::make('reopen_rental')
                    ->label('Reopen Rental')
                    ->icon('heroicon-o-lock-open')
                    ->color('danger')
                    ->visible(fn (Rental $record) => $record->status === Rental::STATUS_COMPLETED
                        && Auth::user()?->hasRole(['super_admin', 'admin']))
                    ->requiresConfirmation()
                    ->modalHeading('Reopen completed rental?')
                    ->modalDescription('Moves this rental back to ACTIVE so items and total can be edited and the return redone. The last return checklist is reopened and units are marked rented again. Financial entries from the previous completion are NOT reversed — review them before completing again to avoid double counting.')
                    ->form([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->placeholder('e.g. koreksi late fee / salah input item'),
                    ])
                    ->action(function (Rental $record, array $data) {
                        $record->reopenFromCompleted();

                        // Status transition (Completed → Active) is auto-logged by
                        // RentalObserver; record the reopen reason in the same activity log.
                        $record->logActivity('Reopen dari Completed. Alasan: ' . $data['reason'], 'status');

                        \Illuminate\Support\Facades\Log::info('Rental REOPEN', [
                            'rental_id' => $record->id,
                            'by' => Auth::id(),
                            'reason' => $data['reason'],
                        ]);

                        Notification::make()
                            ->title('Rental reopened')
                            ->body('Status set to Active. Edit the rental, then process the return again. Review finance entries from the prior completion.')
                            ->warning()
                            ->send();
                    }),

                // Finance Actions Group
                ActionGroup::make([
                    // Record DP / Payment
                    Action::make('record_payment')
                        ->label('Record Payment/DP')
                        ->icon('heroicon-o-banknotes')
                        ->form([
                            Select::make('finance_account_id')
                                ->label('Account')
                                ->options(FinanceAccount::pluck('name', 'id'))
                                ->required(),
                            TextInput::make('amount')
                                ->label('Amount')
                                ->numeric()
                                ->prefix('Rp')
                                ->required(),
                            DatePicker::make('transaction_date')
                                ->default(now())
                                ->required(),
                            Textarea::make('description')
                                ->default(fn (Rental $record) => 'Payment for Rental ' . $record->rental_code),
                        ])
                        ->action(function (Rental $record, array $data) {
                            $transaction = new FinanceTransaction([
                                'finance_account_id' => $data['finance_account_id'],
                                'type' => 'income',
                                'amount' => $data['amount'],
                                'description' => $data['description'],
                                'category' => 'Rental Payment',
                                'date' => $data['transaction_date'],
                            ]);

                            // GL posting: settle the receivable if an invoice already exists
                            // (Dr Kas / Cr Piutang), otherwise treat as a customer advance
                            // (Dr Kas / Cr Uang Muka 2-1300).
                            if ($record->invoice_id && ($invoice = \App\Models\Invoice::find($record->invoice_id))) {
                                $transaction->reference()->associate($invoice);
                                $transaction->save();
                                \App\Services\RentalAccountingService::postPayment($invoice, (int) $data['finance_account_id'], (float) $data['amount'], $data['transaction_date']);
                                $invoice->recalculate();
                            } else {
                                $transaction->reference()->associate($record);
                                $transaction->save();
                                \App\Services\RentalAccountingService::postAdvance($record, (int) $data['finance_account_id'], (float) $data['amount'], $data['transaction_date']);
                            }

                            Notification::make()->title('Payment Recorded')->success()->send();
                        }),

                    // Shared factories (identical GL posting as the Customer Deposits page):
                    // Dr Kas / Cr Uang Jaminan (2-1200) on receive; reversed on refund;
                    // Dr 2-1200 / Cr Pendapatan Denda (4-1200) on deduction.
                    \App\Filament\Actions\ReceiveDepositAction::make(),
                    \App\Filament\Actions\RefundDepositAction::make(),

                    // Add Late Fee
                    Action::make('add_late_fee')
                        ->label('Add Late Fee')
                        ->icon('heroicon-o-clock')
                        ->color('danger')
                        ->form([
                            TextInput::make('late_fee')
                                ->label('Late Fee Amount')
                                ->numeric()
                                ->prefix('Rp')
                                ->default(fn (Rental $record) => $record->late_fee)
                                ->required(),
                        ])
                        ->action(function (Rental $record, array $data) {
                            $previousFee = (float) ($record->late_fee ?? 0);
                            $record->update(['late_fee' => $data['late_fee']]);
                            $record->recalculateTotal();

                            // Recognize only the incremental increase as penalty income
                            // (Dr Piutang / Cr Pendapatan Denda 4-1200). A decrease would
                            // need a manual reversing entry.
                            $delta = (float) $data['late_fee'] - $previousFee;
                            if ($delta > 0) {
                                \App\Services\RentalAccountingService::postLateFee($record, $delta);
                            }

                            $record->logActivity(
                                'Late fee diset Rp ' . number_format((float) $data['late_fee'], 0, ',', '.'),
                                'general'
                            );

                            // Recalc the linked invoice, or issue one when a balance is now
                            // owed but the rental never had an invoice — otherwise the late
                            // fee stays invisible in Accounts Receivable.
                            $result = $record->syncOutstandingInvoice('late fee');

                            if ($result['reopened']) {
                                Notification::make()
                                    ->title('Invoice Reopened')
                                    ->body('Late fee added. Invoice reverted to Partial due to new outstanding balance.')
                                    ->warning()
                                    ->send();
                            } elseif ($result['action'] === 'created' && $result['invoice']) {
                                Notification::make()
                                    ->title('Invoice Issued')
                                    ->body('Late fee added — invoice ' . $result['invoice']->number . ' created. Collect it from Finance → Accounts Receivable.')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Late Fee Updated')
                                    ->body('Rental and invoice totals have been updated.')
                                    ->success()
                                    ->send();
                            }
                        }),
                ])
                ->label('Finance')
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),

                // PDF Actions Group
                ActionGroup::make([
                    // In/Out Status (Delivery Documents)
                    Action::make('documents')
                        ->label('In/Out Status')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->url(fn (Rental $record) => RentalResource::getUrl('delivery', ['record' => $record])),

                    // Checklist Form PDF
                    Action::make('download_checklist')
                        ->label('Download Checklist Form')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->color('gray')
                        ->action(function (Rental $record) {
                            $record->load(['customer', 'items.productUnit.product', 'items.productUnit.kits', 'items.rentalItemKits.unitKit']);
                            
                            $pdf = Pdf::loadView('pdf.checklist-form', ['rental' => $record]);
                            
                            return response()->streamDownload(
                                fn () => print($pdf->output()),
                                'Checklist-' . $record->rental_code . '.pdf'
                            );
                        }),

                    // Make Quotation - REMOVED as per request (auto-quotation)
                    /*
                    Action::make('make_quotation')
                        ->label('Make Quotation')
                        ->icon('heroicon-o-document-plus')
                        ->color('success')
                        ->action(function (Rental $record) {
                            $quotation = Quotation::create([
                                'user_id' => $record->user_id,
                                'date' => now(),
                                'valid_until' => now()->addDays(7),
                                'status' => Quotation::STATUS_ON_QUOTE,
                                'subtotal' => $record->subtotal,
                                'tax' => 0,
                                'total' => $record->total,
                                'notes' => $record->notes,
                            ]);

                            $record->update(['quotation_id' => $quotation->id]);

                            Notification::make()
                                ->title('Quotation created successfully')
                                ->success()
                                ->send();

                            return redirect()->to(QuotationResource::getUrl('edit', ['record' => $quotation]));
                        })
                        ->visible(function (Rental $record) {
                            // If invoice exists, do not show Make Quotation (level up)
                            if ($record->invoice_id) {
                                return false;
                            }

                            // Visible if NO quotation exists OR rental has been modified AFTER quotation creation
                            if (!$record->quotation_id) {
                                return true;
                            }
                            
                            $quotation = Quotation::find($record->quotation_id);
                            if (!$quotation) {
                                return true;
                            }

                            // Check if rental updated_at is greater than quotation created_at
                            // Using a small buffer (e.g. 1 minute) to avoid immediate re-show
                            return $record->updated_at->gt($quotation->created_at->addMinutes(1));
                        }),
                    */

                    // Download Quotation
                    Action::make('download_quotation')
                        ->label('Download Quotation')
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->action(function (Rental $record) {
                            $quotation = Quotation::with(['user', 'rentals.items.productUnit.product', 'rentals.items.product', 'rentals.items.productVariation', 'rentals.items.rentalItemKits.unitKit'])->find($record->quotation_id);
                            
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
                        ->visible(fn (Rental $record) => $record->quotation_id),

                    // Download Invoice
                    Action::make('download_invoice')
                        ->label('Download Invoice')
                        ->icon('heroicon-o-document-currency-dollar')
                        ->color('gray')
                        ->action(function (Rental $record) {
                            $invoice = \App\Models\Invoice::with(['user', 'rentals.items.productUnit.product', 'rentals.items.product', 'rentals.items.productVariation', 'rentals.items.rentalItemKits.unitKit'])->find($record->invoice_id);
                            
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
                        ->visible(fn (Rental $record) => !empty($record->invoice_id)),
                ]),

                // Edit button
                EditAction::make()
                    ->visible(fn (Rental $record) => $record->canBeEdited()),

                // Cancel button
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
                    ->action(function (Rental $record, array $data) {
                        $record->cancelRental($data['cancel_reason']);

                        Notification::make()
                            ->title('Rental cancelled')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Rental $record) => in_array($record->getRealTimeStatus(), [
                        Rental::STATUS_QUOTATION,
                        Rental::STATUS_CONFIRMED,
                        Rental::STATUS_LATE_PICKUP,
                    ])),

                // Delete button
                DeleteAction::make()
                    ->visible(fn (Rental $record) => in_array($record->status, [
                        Rental::STATUS_CANCELLED,
                        Rental::STATUS_COMPLETED,
                    ])),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Rental $record) => RentalResource::getUrl('view', ['record' => $record]))
            ->poll('30s');
    }
}