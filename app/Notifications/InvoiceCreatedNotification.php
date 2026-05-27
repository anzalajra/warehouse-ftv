<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class InvoiceCreatedNotification extends Notification
{
    use Queueable;

    public Invoice $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function via(object $notifiable): array
    {
        $channels = [];
        
        if (Setting::get('notification_app_enabled', true)) {
            $channels[] = 'database';
        }
        
        if (Setting::get('notification_email_enabled', true) && Setting::get('notify_new_invoice', true)) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customerName = $this->invoice->user?->name ?? 'Unknown';
        
        return (new MailMessage)
            ->subject('New Invoice Created - ' . $this->invoice->number)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A new invoice has been created.')
            ->line('**Invoice Details:**')
            ->line('Invoice Number: ' . $this->invoice->number)
            ->line('Customer: ' . $customerName)
            ->line('Total: Rp ' . number_format($this->invoice->total, 0, ',', '.'))
            ->line('Due Date: ' . $this->invoice->due_date?->format('d M Y'))
            ->action('View Invoice', url("/admin/invoices/{$this->invoice->id}/edit"));
    }

    public function toDatabase(object $notifiable): array
    {
        $customerName = $this->invoice->user?->name ?? 'Unknown';
        
        return FilamentNotification::make()
            ->title('New Invoice')
            ->body("Invoice {$this->invoice->number} created for {$customerName}")
            ->icon('heroicon-o-document-text')
            ->color('success')
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->button()
                    ->url("/admin/invoices/{$this->invoice->id}/edit")
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
