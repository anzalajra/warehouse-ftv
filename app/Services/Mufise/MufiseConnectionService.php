<?php

namespace App\Services\Mufise;

use App\Models\MufiseAccount;
use App\Models\User;
use App\Services\MufiseClient;
use Illuminate\Support\Facades\Cache;

class MufiseConnectionService
{
    public function __construct(private MufiseClient $client) {}

    public function account(int $userId): MufiseAccount
    {
        return MufiseAccount::firstOrCreate([
            'warehouse_user_id' => $userId,
            'connection_id' => MufiseSettings::connectionId(),
            'server_url' => MufiseSettings::url(),
        ]);
    }

    public function token(MufiseAccount $account): string
    {
        if ($account->connection_id !== MufiseSettings::connectionId() || $account->server_url !== MufiseSettings::url() || ! $account->access_token || ! $account->expires_at?->isFuture()) {
            throw new MufiseException('reconnect_required', 'Hubungkan ulang akun Mufise milik peminjam.');
        }

        return $account->access_token;
    }

    public function start(User $user): array
    {
        $account = $this->account($user->id);

        return Cache::lock('mufise-account-'.$account->id, 60)->block(1, function () use ($account, $user) {
            $result = $this->client->request('POST', 'link-requests', data: ['external_user_id' => (string) $user->id, 'account_label' => $user->name]);
            if (empty($result['request_id']) || empty($result['request_secret']) || ! str_starts_with($result['verification_path'] ?? '', '/connect/warehouse?request=') || str_contains($result['verification_path'], '#')) {
                throw new MufiseException('invalid_response', 'Respons pemasangan Mufise tidak valid.', 503);
            }
            $expires = now()->addSeconds(min(600, (int) ($result['expires_in'] ?? 600)));
            $account->update(['pairing' => ['request_id' => $result['request_id'], 'request_secret' => $result['request_secret'], 'expires_at' => $expires->toIso8601String()], 'next_poll_at' => now()->addSeconds(5)]);

            return ['verification_url' => MufiseSettings::url().'/connect/warehouse?request='.rawurlencode($result['request_id']), 'expires_at' => $expires->toIso8601String()];
        });
    }

    public function poll(int $userId): array
    {
        $account = $this->account($userId);

        return Cache::lock('mufise-account-'.$account->id, 60)->block(1, function () use ($account, $userId) {
            $account->refresh();
            $pairing = $account->pairing;
            if (! $pairing) {
                return ['connected' => (bool) ($account->access_token && $account->expires_at?->isFuture())];
            }
            if (now()->gte($pairing['expires_at'])) {
                $account->update(['pairing' => null]);
                throw new MufiseException('link_expired', 'Pemasangan kedaluwarsa. Mulai kembali.', 410);
            }
            if ($account->next_poll_at?->isFuture()) {
                return ['connected' => false, 'pending' => true];
            }
            $account->update(['next_poll_at' => now()->addSeconds(5)]);
            try {
                $result = $this->client->request('POST', 'token', data: ['request_id' => $pairing['request_id'], 'request_secret' => $pairing['request_secret']]);
            } catch (MufiseException $e) {
                if ($e->status === 410) {
                    $account->update(['pairing' => null]);
                }
                if ($e->status === 429) {
                    $account->update(['next_poll_at' => now()->addSeconds($e->retryAfter)]);
                }
                if ($e->errorCode === 'authorization_pending') {
                    return ['connected' => false, 'pending' => true];
                }
                throw $e;
            }
            if (($result['external_user_id'] ?? null) !== (string) $userId || empty($result['access_token']) || empty($result['user_id'])) {
                $account->update(['pairing' => null]);
                throw new MufiseException('invalid_response', 'Identitas pemasangan tidak sesuai. Mulai pemasangan kembali.', 503);
            }
            $account->update(['access_token' => $result['access_token'], 'mufise_user_id' => $result['user_id'], 'expires_at' => now()->addSeconds(min(90 * 86400, (int) ($result['expires_in'] ?? 0))), 'pairing' => null, 'next_poll_at' => null]);

            return ['connected' => true];
        });
    }

    public function disconnect(int $userId): void
    {
        $account = $this->account($userId);
        Cache::lock('mufise-account-'.$account->id, 60)->block(1, function () use ($account) {
            $account->refresh();
            if ($account->access_token) {
                $this->client->request('POST', 'disconnect', $account->access_token);
            }
            $account->update(['access_token' => null, 'expires_at' => null, 'pairing' => null]);
        });
    }
}
