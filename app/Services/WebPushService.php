<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function isConfigured(): bool
    {
        return ! empty(config('webpush.vapid.public_key'))
            && ! empty(config('webpush.vapid.private_key'));
    }

    public function publicKey(): ?string
    {
        return config('webpush.vapid.public_key');
    }

    /**
     * Send a push to every subscription owned by the user.
     *
     * @param  array{title:string, body?:string, url?:string, tag?:string, icon?:string}  $payload
     */
    public function sendToUser(int $userId, array $payload): void
    {
        if (! $this->isConfigured()) {
            Log::warning('WebPush not configured: missing VAPID keys.');
            return;
        }

        $subs = PushSubscription::where('user_id', $userId)
            ->orderByDesc('last_used_at')
            ->get();
        if ($subs->isEmpty()) {
            return;
        }

        // Collapse duplicate subscriptions belonging to the same physical device.
        // iOS rotates push endpoints and can subscribe through more than one SW
        // scope, leaving several rows per device. Since iOS does not coalesce
        // notifications by `tag`, delivering to each row makes one push appear
        // two (or more) times. Keep only the newest subscription per user agent
        // and prune the stale ones so the table self-heals.
        $seenAgents = [];
        $subs = $subs->filter(function (PushSubscription $sub) use (&$seenAgents) {
            $ua = trim((string) $sub->user_agent);
            if ($ua === '') {
                return true; // unknown device — can't safely dedupe, keep it
            }
            if (isset($seenAgents[$ua])) {
                $sub->delete(); // stale duplicate from the same device
                return false;
            }
            $seenAgents[$ua] = true;
            return true;
        });

        $auth = [
            'VAPID' => [
                'subject' => config('webpush.vapid.subject'),
                'publicKey' => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ],
        ];

        $webPush = new WebPush($auth, [
            'TTL' => config('webpush.ttl'),
            'urgency' => config('webpush.urgency'),
        ]);

        $json = json_encode($payload);

        foreach ($subs as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->p256dh,
                'authToken' => $sub->auth,
                'contentEncoding' => 'aesgcm',
            ]);

            $webPush->queueNotification($subscription, $json);
        }

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();

            if ($report->isSuccess()) {
                PushSubscription::where('endpoint', $endpoint)
                    ->update(['last_used_at' => now()]);
                continue;
            }

            // 404/410 = subscription expired or unsubscribed
            $response = $report->getResponse();
            $status = $response ? $response->getStatusCode() : null;
            if ($status === 404 || $status === 410) {
                PushSubscription::where('endpoint', $endpoint)->delete();
            } else {
                Log::warning('WebPush delivery failed', [
                    'endpoint' => $endpoint,
                    'status' => $status,
                    'reason' => $report->getReason(),
                ]);
            }
        }
    }
}
