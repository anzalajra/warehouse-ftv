<?php

namespace App\Notifications;

use App\Models\Rental;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class BookingConfirmedNotification extends Notification
{
    use Queueable;

    public $rental;

    /**
     * Create a new notification instance.
     */
    public function __construct(Rental $rental)
    {
        $this->rental = $rental;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
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

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Booking Confirmed - ' . $this->rental->rental_code)
                    ->greeting('Hello ' . $notifiable->name . ',')
                    ->line('Your booking has been confirmed.')
                    ->line('Rental Code: ' . $this->rental->rental_code)
                    ->line('Start Date: ' . $this->rental->start_date->format('d M Y'))
                    ->line('End Date: ' . $this->rental->end_date->format('d M Y'))
                    ->action('View Booking', url('/customer/rentals/' . $this->rental->id))
                    ->line('Thank you for choosing Gearent!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $isAdmin = $notifiable instanceof User && $notifiable->hasAnyRole(['super_admin', 'admin', 'staff']);
        $url = $isAdmin
            ? "/admin/rentals/{$this->rental->id}/view"
            : "/customer/rentals/{$this->rental->id}";

        return FilamentNotification::make()
            ->title('Booking Confirmed')
            ->body("Your booking {$this->rental->rental_code} has been confirmed.")
            ->icon('heroicon-o-check-circle')
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->button()
                    ->url($url)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
