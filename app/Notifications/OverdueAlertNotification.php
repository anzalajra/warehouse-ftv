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

class OverdueAlertNotification extends Notification
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
        
        // Email only for Customer (users without admin roles), not Admin
        if ($notifiable instanceof User && !$notifiable->hasAnyRole(['super_admin', 'admin', 'staff']) && Setting::get('notification_email_enabled', true)) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Overdue Alert - ' . $this->rental->rental_code)
                    ->greeting('Hello ' . $notifiable->name . ',')
                    ->line('Your rental is overdue! Please return the items immediately to avoid additional late fees.')
                    ->line('Rental Code: ' . $this->rental->rental_code)
                    ->line('Due Date: ' . $this->rental->end_date->format('d M Y'))
                    ->action('View Booking', url('/customer/rentals/' . $this->rental->id))
                    ->line('Thank you for choosing Gearent!');
    }

    public function toDatabase(object $notifiable): array
    {
        // Check if user is a customer (no admin roles) or admin/staff
        $isCustomer = $notifiable instanceof User && !$notifiable->hasAnyRole(['super_admin', 'admin', 'staff']);
        $url = $isCustomer
            ? "/customer/rentals/{$this->rental->id}"
            : "/admin/rentals/{$this->rental->id}/view";

        return FilamentNotification::make()
            ->title('Overdue Alert')
            ->body("Booking {$this->rental->rental_code} is overdue!")
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->button()
                    ->url($url)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
