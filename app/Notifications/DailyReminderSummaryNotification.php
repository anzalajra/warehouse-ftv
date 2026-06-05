<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class DailyReminderSummaryNotification extends Notification
{
    use Queueable;

    public int $pickupCount;
    public int $returnCount;
    public string $date;

    public function __construct(int $pickupCount, int $returnCount, string $date)
    {
        $this->pickupCount = $pickupCount;
        $this->returnCount = $returnCount;
        $this->date = $date;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if (Setting::get('notification_app_enabled', true)) {
            $channels[] = 'database';
        }
        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        $body = ($this->pickupCount === 0 && $this->returnCount === 0)
            ? 'Tidak ada pickup atau return yang dijadwalkan untuk besok.'
            : "Besok ada {$this->pickupCount} pickup dan {$this->returnCount} return.";

        return FilamentNotification::make()
            ->title('Reminder H-1 Pickup & Return')
            ->body($body)
            ->icon('heroicon-o-bell-alert')
            ->color('warning')
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->button()
                    ->url('/admin/schedule?reminder=' . $this->date)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
