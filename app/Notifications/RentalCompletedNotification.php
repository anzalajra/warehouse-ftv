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

class RentalCompletedNotification extends Notification
{
    use Queueable;

    public Rental $rental;

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
        
        // Check if notifiable is admin/staff or customer
        $isAdmin = $notifiable instanceof User && $notifiable->hasAnyRole(['super_admin', 'admin', 'staff']);
        
        if (Setting::get('notification_email_enabled', true)) {
            if ($isAdmin && Setting::get('notify_rental_completed', true)) {
                $channels[] = 'mail';
            } elseif (!$isAdmin) {
                // Always send to customer if email notifications are enabled
                $channels[] = 'mail';
            }
        }
        
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isAdmin = $notifiable instanceof User && $notifiable->hasAnyRole(['super_admin', 'admin', 'staff']);
        
        if ($isAdmin) {
            return (new MailMessage)
                ->subject('Rental Completed - ' . $this->rental->rental_code)
                ->greeting('Hello ' . $notifiable->name . ',')
                ->line('A rental has been completed.')
                ->line('**Rental Details:**')
                ->line('Rental Code: ' . $this->rental->rental_code)
                ->line('Customer: ' . ($this->rental->user?->name ?? 'Unknown'))
                ->line('Total: Rp ' . number_format($this->rental->total, 0, ',', '.'))
                ->action('View Rental', url("/admin/rentals/{$this->rental->id}/view"));
        }
        
        return (new MailMessage)
            ->subject('Rental Completed - ' . $this->rental->rental_code)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your rental has been completed. Thank you for using our service!')
            ->line('**Rental Details:**')
            ->line('Rental Code: ' . $this->rental->rental_code)
            ->line('Total: Rp ' . number_format($this->rental->total, 0, ',', '.'))
            ->action('View Details', url("/customer/rentals/{$this->rental->id}"))
            ->line('We hope to serve you again soon!');
    }

    public function toDatabase(object $notifiable): array
    {
        $isAdmin = $notifiable instanceof User && $notifiable->hasAnyRole(['super_admin', 'admin', 'staff']);
        $url = $isAdmin ? "/admin/rentals/{$this->rental->id}/view" : "/customer/rentals/{$this->rental->id}";
        
        return FilamentNotification::make()
            ->title('Rental Completed')
            ->body("Rental {$this->rental->rental_code} has been completed")
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->button()
                    ->url($url)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
