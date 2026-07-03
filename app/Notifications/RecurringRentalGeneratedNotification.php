<?php

namespace App\Notifications;

use App\Models\Rental;
use App\Models\Setting;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to admins when the recurring-rental scheduler generates a fresh draft
 * quotation from a recurring source. Passive review — no auto-charge.
 */
class RecurringRentalGeneratedNotification extends Notification
{
    use Queueable;

    public Rental $rental;

    public Rental $parent;

    public function __construct(Rental $rental, Rental $parent)
    {
        $this->rental = $rental;
        $this->parent = $parent;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (Setting::get('notification_app_enabled', true)) {
            $channels[] = 'database';
        }

        if (Setting::get('notification_email_enabled', true) && Setting::get('notify_new_rental', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customerName = $this->rental->user?->name ?? 'Unknown';

        return (new MailMessage)
            ->subject('Recurring Rental Quotation - '.$this->rental->rental_code)
            ->greeting('Hello '.$notifiable->name.',')
            ->line("A recurring rental generated a new draft quotation from {$this->parent->rental_code}.")
            ->line('Quotation: '.$this->rental->rental_code)
            ->line('Customer: '.$customerName)
            ->line('Start Date: '.$this->rental->start_date?->format('d M Y'))
            ->line('End Date: '.$this->rental->end_date?->format('d M Y'))
            ->action('Review Quotation', url("/admin/rentals/{$this->rental->id}/edit"))
            ->line('Please review, assign units and confirm.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $customerName = $this->rental->user?->name ?? 'Unknown';

        return FilamentNotification::make()
            ->title('Recurring quotation generated')
            ->body("New draft {$this->rental->rental_code} for {$customerName} (from {$this->parent->rental_code})")
            ->icon('heroicon-o-arrow-path')
            ->actions([
                \Filament\Actions\Action::make('review')
                    ->button()
                    ->url("/admin/rentals/{$this->rental->id}/edit")
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
