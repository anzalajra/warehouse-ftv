<?php

namespace App\Notifications;

use App\Models\Setting;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaintenanceDueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $qcDue = 0,
        public int $preventiveDue = 0,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Bell channel only; mirrored to web push by SendWebPushOnNotification.
        return Setting::get('notification_app_enabled', true) ? ['database'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $parts = [];
        if ($this->qcDue > 0) {
            $parts[] = "{$this->qcDue} unit jatuh tempo QC";
        }
        if ($this->preventiveDue > 0) {
            $parts[] = "{$this->preventiveDue} unit perlu servis preventif";
        }

        return FilamentNotification::make()
            ->title('Maintenance jatuh tempo')
            ->body(implode(' · ', $parts) ?: 'Ada unit yang perlu perhatian maintenance.')
            ->icon('heroicon-o-wrench-screwdriver')
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->button()
                    ->url('/admin/maintenances')
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
