<?php

namespace App\Services\Mufise;

use App\Models\MufiseAccount;
use App\Models\User;
use App\Services\MufiseClient;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        if ($account->connection_id !== MufiseSettings::connectionId() || $account->server_url !== MufiseSettings::url()) {
            throw $this->localProblem('connection_changed', 'Tujuan koneksi Mufise berubah. Periksa konfigurasi admin sebelum memasangkan akun ke tujuan baru.', 'check_configuration');
        }
        $token = $this->storedToken($account);
        if (! $token) {
            throw $this->localProblem('local_token_missing', 'Token koneksi belum tersedia. Hubungkan akun jika belum pernah terhubung; jika sudah, hubungi admin Warehouse.', 'check_token_storage');
        }
        if ($account->expires_at !== null && ! $account->expires_at->isFuture()) {
            throw $this->localProblem('local_token_expired', 'Masa berlaku koneksi yang disimpan Warehouse sudah berakhir. Admin perlu memeriksa status token Mufise atau peminjam memulai pemasangan baru.', 'check_token_storage');
        }

        return $token;
    }

    public function storedToken(MufiseAccount $account): ?string
    {
        try {
            return $account->access_token;
        } catch (DecryptException) {
            throw $this->localProblem('token_decryption_failed', 'Token koneksi tidak dapat dibaca. Hubungi admin Warehouse.', 'check_token_storage');
        }
    }

    private function localProblem(string $reason, string $message, string $action): MufiseException
    {
        $reference = (string) Str::uuid();
        Log::warning('mufise.local_problem', ['warehouse_request_id' => $reference, 'reason' => $reason, 'action' => $action]);

        return new MufiseException($reason, $message, 409, reason: $reason, action: $action, warehouseRequestId: $reference);
    }

    public function pairing(MufiseAccount $account): ?array
    {
        try {
            return $account->pairing;
        } catch (DecryptException) {
            throw $this->localProblem('pairing_decryption_failed', 'Sesi pemasangan tidak dapat dibaca. Hubungi admin Warehouse.', 'check_token_storage');
        }
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
            $pairing = $this->pairing($account);
            if (! $pairing) {
                return ['connected' => (bool) ($this->storedToken($account) && ($account->expires_at === null || $account->expires_at->isFuture()))];
            }
            if (now()->gte($pairing['expires_at'])) {
                $account->update(['pairing' => null]);
                throw new MufiseException('link_expired', 'Pemasangan kedaluwarsa. Mulai kembali.', 410, action: 'restart_pairing');
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
            if (($result['external_user_id'] ?? null) !== (string) $userId || ! is_string($result['access_token'] ?? null) || $result['access_token'] === '' || ! is_string($result['user_id'] ?? null) || $result['user_id'] === '') {
                $account->update(['pairing' => null]);
                throw new MufiseException('invalid_response', 'Identitas pemasangan tidak sesuai. Mulai pemasangan kembali.', 502, action: 'restart_pairing');
            }
            if (! array_key_exists('expires_in', $result) || ($result['expires_in'] !== null && (! is_int($result['expires_in']) || $result['expires_in'] <= 0))) {
                $account->update(['pairing' => null, 'next_poll_at' => null]);
                throw new MufiseException('invalid_response', 'Masa berlaku token Mufise tidak valid. Mulai pemasangan baru.', 502, action: 'restart_pairing');
            }
            $expiresAt = $result['expires_in'] === null ? null : now()->addSeconds(min(90 * 86400, $result['expires_in']));
            $account->update(['access_token' => $result['access_token'], 'mufise_user_id' => $result['user_id'], 'expires_at' => $expiresAt, 'pairing' => null, 'next_poll_at' => null]);

            return ['connected' => true];
        });
    }

    public function disconnect(int $userId): void
    {
        $account = $this->account($userId);
        Cache::lock('mufise-account-'.$account->id, 60)->block(1, function () use ($account) {
            $account->refresh();
            if ($token = $this->storedToken($account)) {
                $this->client->request('POST', 'disconnect', $token);
            }
            $account->update(['access_token' => null, 'expires_at' => null, 'pairing' => null]);
        });
    }
}
