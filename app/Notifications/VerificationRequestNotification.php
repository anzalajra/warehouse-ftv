<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class VerificationRequestNotification extends Notification
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
        
        if (Setting::get('notification_email_enabled', true) && Setting::get('notify_verification_request', true)) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Document Verification Request - ' . $this->customer->name)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A customer has uploaded documents for verification.')
            ->line('**Customer Details:**')
            ->line('Name: ' . $this->customer->name)
            ->line('Email: ' . $this->customer->email)
            ->action('Review Documents', url("/admin/customers/{$this->customer->id}/edit"))
            ->line('Please review and verify the documents.');
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Verification Request')
            ->body("{$this->customer->name} has requested document verification")
            ->icon('heroicon-o-document-check')
            ->color('warning')
            ->actions([
                \Filament\Actions\Action::make('review')
                    ->button()
                    ->url("/admin/customers/{$this->customer->id}/edit")
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
