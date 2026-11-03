<?php

namespace App\Console\Commands;

use App\Models\MufiseAccount;
use App\Services\Mufise\MufiseConnectionService;
use App\Services\Mufise\MufiseException;
use App\Services\Mufise\MufiseSettings;
use App\Services\MufiseClient;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class RepairMufiseTokenExpiry extends Command
{
    protected $signature = 'mufise:repair-token-expiry
        {--apply : Kosongkan expiry hanya setelah /me berhasil}
        {--confirmed-no-expiry : Konfirmasi server Mufise sudah memakai migrasi 051/token tanpa batas waktu}';

    protected $description = 'Periksa dan pulihkan expiry lokal token Mufise pada server/koneksi saat ini';

    public function handle(MufiseClient $client, MufiseConnectionService $connections): int
    {
        if ($this->option('apply') && ! $this->option('confirmed-no-expiry')) {
            $this->error('Pastikan server Mufise sudah memakai migrasi 051, lalu sertakan --confirmed-no-expiry. Tidak ada data diubah.');

            return self::FAILURE;
        }
        if (! MufiseSettings::enabled() || ! MufiseSettings::url() || ! MufiseSettings::connectionId()) {
            $this->error('Konfigurasi Mufise belum aktif/lengkap. Tidak ada data diubah.');

            return self::FAILURE;
        }
        $verified = $failed = $skipped = 0;
        MufiseAccount::where('server_url', MufiseSettings::url())->where('connection_id', MufiseSettings::connectionId())
            ->whereNotNull('access_token')->whereNotNull('expires_at')->chunkById(50, function ($accounts) use ($client, $connections, &$verified, &$failed, &$skipped) {
                foreach ($accounts as $account) {
                    try {
                        Cache::lock('mufise-account-'.$account->id, 60)->block(1, function () use ($account, $client, $connections, &$verified, &$skipped) {
                            $account->refresh();
                            if ($connections->pairing($account) !== null || $account->expires_at === null) {
                                $skipped++;

                                return;
                            }
                            // Bypass local expiry only for this explicit recovery check.
                            $token = $connections->storedToken($account);
                            if (! $token) {
                                $skipped++;

                                return;
                            }
                            $status = $client->request('GET', 'me', $token);
                            if (! in_array($status['next_action'] ?? null, ['ready', 'join_organization', 'create_project', 'select_project'], true)) {
                                throw new MufiseException('invalid_response', 'Respons status akun Mufise tidak valid.', 502);
                            }
                            if ($this->option('apply')) {
                                $account->update(['expires_at' => null]);
                            }
                            $verified++;
                        });
                    } catch (MufiseException $e) {
                        $failed++;
                        $this->warn('Gagal: '.($e->reason ?? $e->errorCode).' | Referensi: '.($e->requestId ?? $e->warehouseRequestId ?? '-'));
                    } catch (LockTimeoutException) {
                        $skipped++;
                    }
                }
            });
        $this->info("Terverifikasi: {$verified}; gagal: {$failed}; dilewati: {$skipped}.");
        $this->info($this->option('apply') ? 'Expiry dikosongkan hanya untuk token yang terverifikasi.' : 'Mode pemeriksaan: tidak ada data diubah.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
