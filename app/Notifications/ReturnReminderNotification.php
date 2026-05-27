<?php

namespace App\Notifications;

use App\Models\Rental;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class ReturnReminderNotification extends Notification
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
                    ->subject('Return Reminder - ' . $this->rental->rental_code)
                    ->greeting('Hello ' . $notifiable->name . ',')
                    ->line('This is a reminder to return your rental items tomorrow.')
                    ->line('Rental Code: ' . $this->rental->rental_code)
                    ->line('Return Date: ' . $this->rental->end_date->format('d M Y'))
                    ->action('View Booking', url('/customer/rentals/' . $this->rental->id))
                    ->line('Thank you for choosing Gearent!');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Return Reminder')
            ->body("Reminder: Return for booking {$this->rental->rental_code} is due tomorrow.")
            ->icon('heroicon-o-arrow-path')
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->button()
                    ->url("/customer/rentals/{$this->rental->id}")
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
