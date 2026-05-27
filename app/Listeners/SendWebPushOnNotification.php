<?php

namespace App\Listeners;

use App\Models\Setting;
use App\Services\WebPushService;
use Illuminate\Notifications\Events\NotificationSent;

class SendWebPushOnNotification
{
    public function __construct(protected WebPushService $push) {}

    public function handle(NotificationSent $event): void
    {
        // Only mirror the database channel — that is what populates the bell.
        if ($event->channel !== 'database') {
            return;
        }

        if (! Setting::get('pwa_admin_push_enabled', true)) {
            return;
        }

        $notifiable = $event->notifiable;
        if (! method_exists($notifiable, 'getKey')) {
            return;
        }
        $userId = $notifiable->getKey();
        if (! $userId) {
            return;
        }

        // Per-event opt-out: if the user disabled this notification class entirely
        // we still write to the bell (controlled by `notify_*` flags inside via()),
        // but suppress the push if the class is listed in the "no_push" blocklist.
        $class = get_class($event->notification);
        $blockedKey = 'pwa_admin_push_block_' . $this->classKey($class);
        if (Setting::get($blockedKey, false)) {
            return;
        }

        $payload = $this->buildPayload($event->notification, $notifiable);
        if (! $payload) {
            return;
        }

        try {
            $this->push->sendToUser((int) $userId, $payload);
        } catch (\Throwable $e) {
            \Log::warning('Web push dispatch failed: ' . $e->getMessage());
        }
    }

    protected function buildPayload($notification, $notifiable): ?array
    {
        $title = Setting::get('pwa_admin_name', 'Warehouse FTV');
        $body = '';
        $url = '/admin';

        if (method_exists($notification, 'toDatabase')) {
            $data = $notification->toDatabase($notifiable);
            if (is_array($data)) {
                $title = $data['title'] ?? $title;
                $body = $data['body'] ?? $data['message'] ?? $body;
                if (! empty($data['actions']) && is_array($data['actions'])) {
                    foreach ($data['actions'] as $action) {
                        if (! empty($action['url'])) {
                            $url = $action['url'];
                            break;
                        }
                    }
                }
            }
        } elseif (method_exists($notification, 'toArray')) {
            $data = $notification->toArray($notifiable);
            $body = is_array($data) ? ($data['message'] ?? '') : '';
        }

        return [
            'title' => $title,
            'body' => is_string($body) ? strip_tags($body) : '',
            'url' => $url,
            'tag' => class_basename($notification),
        ];
    }

    protected function classKey(string $class): string
    {
        return strtolower(str_replace('\\', '_', $class));
    }
}
