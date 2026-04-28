<?php

namespace App\Filament\Actions;

use App\Models\Rental;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

class ReminderPickupReturnAction
{
    public static function make(): Action
    {
        return Action::make('reminder_pickup_return')
            ->label('Reminder Pickup & Return')
            ->icon('heroicon-o-bell-alert')
            ->color('warning')
            ->modalHeading('Template Reminder H-1 (WhatsApp)')
            ->modalDescription('Edit teks bila perlu, lalu klik Copy untuk paste ke WhatsApp.')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->fillForm(fn () => ['message' => self::buildMessage()])
            ->form([
                Textarea::make('message')
                    ->label('Template')
                    ->rows(14)
                    ->extraAttributes(['id' => 'reminder-pickup-return-text'])
                    ->hintAction(
                        Action::make('copy')
                            ->label('Copy text')
                            ->icon('heroicon-o-clipboard')
                            ->action(function () {
                                Notification::make()
                                    ->title('Disalin ke clipboard')
                                    ->success()
                                    ->send();
                            })
                            ->extraAttributes([
                                'x-on:click' => "navigator.clipboard.writeText(document.getElementById('reminder-pickup-return-text').value)",
                            ]),
                    ),
            ]);
    }

    protected static function buildMessage(): string
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        $pickups = Rental::with('customer:id,name')
            ->whereIn('status', [
                Rental::STATUS_QUOTATION,
                Rental::STATUS_CONFIRMED,
                Rental::STATUS_LATE_PICKUP,
            ])
            ->whereDate('start_date', $tomorrow)
            ->orderBy('start_date')
            ->get();

        $returns = Rental::with('customer:id,name')
            ->whereIn('status', [
                Rental::STATUS_ACTIVE,
                Rental::STATUS_PARTIAL_RETURN,
                Rental::STATUS_LATE_RETURN,
            ])
            ->whereDate('end_date', $tomorrow)
            ->orderBy('end_date')
            ->get();

        $lines = [];
        $lines[] = '*Reminder H-1 Pickup Alat:*';
        if ($pickups->isEmpty()) {
            $lines[] = '- (tidak ada)';
        } else {
            foreach ($pickups as $r) {
                $name = $r->customer?->name ?? '-';
                $time = optional($r->start_date)->format('H:i');
                $suffix = $r->status === Rental::STATUS_QUOTATION ? ' (belum konfirmasi)' : '';
                $lines[] = "- {$name} ({$r->rental_code}) - {$time}{$suffix}";
            }
        }
        $lines[] = '';
        $lines[] = '*Reminder H-1 Return Alat:*';
        if ($returns->isEmpty()) {
            $lines[] = '- (tidak ada)';
        } else {
            foreach ($returns as $r) {
                $name = $r->customer?->name ?? '-';
                $time = optional($r->end_date)->format('H:i');
                $lines[] = "- {$name} ({$r->rental_code}) - {$time}";
            }
        }

        return implode("\n", $lines);
    }
}
