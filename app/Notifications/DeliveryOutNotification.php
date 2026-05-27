<?php

namespace App\Notifications;

use App\Models\Delivery;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class DeliveryOutNotification extends Notification
{
    use Queueable;

    public Delivery $delivery;

    public function __construct(Delivery $delivery)
    {
        $this->delivery = $delivery;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        
        if (Setting::get('notification_app_enabled', true)) {
            $channels[] = 'database';
        }
        
        if (Setting::get('notification_email_enabled', true) && Setting::get('notify_delivery_out', true)) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rental = $this->delivery->rental;
        $customerName = $rental?->user?->name ?? 'Unknown';
        
        return (new MailMessage)
            ->subject('Surat Jalan Keluar - ' . $this->delivery->delivery_number)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A delivery out (Surat Jalan Keluar) has been created.')
            ->line('**Delivery Details:**')
            ->line('Delivery Number: ' . $this->delivery->delivery_number)
            ->line('Rental Code: ' . ($rental?->rental_code ?? '-'))
            ->line('Customer: ' . $customerName)
            ->line('Date: ' . $this->delivery->date?->format('d M Y'))
            ->action('View Delivery', url("/admin/deliveries/{$this->delivery->id}/edit"));
    }

    public function toDatabase(object $notifiable): array
    {
        $rental = $this->delivery->rental;
        
        return FilamentNotification::make()
            ->title('Surat Jalan Keluar')
            ->body("Delivery {$this->delivery->delivery_number} for rental {$rental?->rental_code}")
            ->icon('heroicon-o-truck')
            ->color('warning')
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->button()
                    ->url("/admin/deliveries/{$this->delivery->id}/edit")
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
