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

        $subs = PushSubscription::where('user_id', $userId)->get();
        if ($subs->isEmpty()) {
            return;
        }

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
