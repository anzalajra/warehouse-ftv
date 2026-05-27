<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class NewCustomerNotification extends Notification
{
    use Queueable;

    public User $customer;

    public function __construct(User $customer)
    {
        $this->customer = $customer;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        
        if (Setting::get('notification_app_enabled', true)) {
            $channels[] = 'database';
        }
        
        if (Setting::get('notification_email_enabled', true) && Setting::get('notify_new_customer', true)) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Customer Registration - ' . $this->customer->name)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A new customer has registered on the system.')
            ->line('**Customer Details:**')
            ->line('Name: ' . $this->customer->name)
            ->line('Email: ' . $this->customer->email)
            ->line('Phone: ' . ($this->customer->phone ?? '-'))
            ->action('View Customer', url("/admin/customers/{$this->customer->id}/edit"))
            ->line('Please verify their documents if required.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('New Customer')
            ->body("New customer registered: {$this->customer->name}")
            ->icon('heroicon-o-user-plus')
            ->color('info')
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->button()
                    ->url("/admin/customers/{$this->customer->id}/edit")
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
