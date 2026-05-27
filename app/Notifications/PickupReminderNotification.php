<?php

namespace App\Notifications;

use App\Models\Rental;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class PickupReminderNotification extends Notification
{
    use Queueable;

    public $rental;

    public function __construct(Rental $rental)
    {
        $this->rental = $rental;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        if (Setting::get('notification_app_enabled', true)) {
            $channels[] = 'database';
        }
        if (Setting::get('notification_email_enabled', true)) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Pickup Reminder - ' . $this->rental->rental_code)
                    ->greeting('Hello ' . $notifiable->name . ',')
                    ->line('This is a reminder to pick up your rental tomorrow.')
                    ->line('Rental Code: ' . $this->rental->rental_code)
                    ->line('Pickup Date: ' . $this->rental->start_date->format('d M Y'))
                    ->action('View Booking', url('/customer/rentals/' . $this->rental->id))
                    ->line('Thank you for choosing Gearent!');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Pickup Reminder')
            ->body("Reminder: Pickup for booking {$this->rental->rental_code} is scheduled for tomorrow.")
            ->icon('heroicon-o-clock')
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->button()
                    ->url("/customer/rentals/{$this->rental->id}")
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
