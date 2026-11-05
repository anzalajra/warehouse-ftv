<?php

namespace App\Services;

use App\Services\Mufise\MufiseException;
use App\Services\Mufise\MufiseSettings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MufiseClient
{
    public function request(string $method, string $path, ?string $token = null, array $data = [], ?string $operationId = null, ?array $photo = null, bool $diagnostic = false): array|string
    {
        $localId = (string) Str::uuid();
        $started = microtime(true);
        $method = strtoupper($method);
        $context = ['warehouse_request_id' => $localId, 'method' => in_array($method, ['GET', 'POST', 'PATCH', 'DELETE'], true) ? $method : 'other', 'endpoint' => $this->endpoint($path)];
        try {
            $key = MufiseSettings::key();
        } catch (DecryptException) {
            Log::warning('mufise.configuration_failed', $context + ['reason' => 'key_decryption_failed', 'action' => 'check_configuration']);
            throw new MufiseException('key_decryption_failed', 'Kunci koneksi Mufise tidak dapat dibaca. Hubungi admin Warehouse.', 503, action: 'check_configuration', warehouseRequestId: $localId);
        }
        if ((! $diagnostic && ! MufiseSettings::enabled()) || ! MufiseSettings::url() || ! MufiseSettings::connectionId() || ! $key) {
            Log::warning('mufise.configuration_failed', $context + ['reason' => 'not_configured', 'action' => 'check_configuration']);
            throw new MufiseException('not_configured', 'Koneksi Mufise belum diaktifkan atau dikonfigurasi admin.', 503, action: 'check_configuration', warehouseRequestId: $localId);
        }
        try {
            MufiseSettings::validateUrl(MufiseSettings::url());
        } catch (ValidationException) {
            Log::warning('mufise.configuration_failed', $context + ['reason' => 'invalid_configuration', 'action' => 'check_configuration']);
            throw new MufiseException('invalid_configuration', 'URL koneksi Mufise tidak valid. Hubungi admin Warehouse.', 503, action: 'check_configuration', warehouseRequestId: $localId);
        }
        $limitKey = 'mufise-requests:'.hash('sha256', MufiseSettings::url().'|'.MufiseSettings::connectionId());
        if (RateLimiter::tooManyAttempts($limitKey, 110)) {
            throw new MufiseException('rate_limited', 'Koneksi Mufise sedang sibuk. Tunggu sebelum mencoba lagi.', 429, max(5, RateLimiter::availableIn($limitKey)), action: 'retry_later', retryable: true, warehouseRequestId: $localId);
        }
        RateLimiter::hit($limitKey, 60);
        $client = Http::baseUrl(MufiseSettings::url().'/api/external/v1/')
            ->acceptJson()->connectTimeout(5)->timeout(20)->withoutRedirecting()
            ->withHeaders(['X-Mufise-Connection' => MufiseSettings::connectionId(), 'X-API-Key' => $key]);
        if ($token) {
            $client = $client->withToken($token);
        }
        if ($operationId) {
            $client = $client->withHeaders(['Idempotency-Key' => $operationId]);
        }
        if ($photo) {
            $client = $client->asMultipart();
            $parts = [];
            foreach ($data as $name => $value) {
                foreach (is_array($value) ? $value : [$value] as $entry) {
                    $parts[] = ['name' => is_array($value) ? $name.'[]' : $name, 'contents' => is_bool($entry) ? ($entry ? 'true' : 'false') : (string) $entry];
                }
            }
            $parts[] = ['name' => 'photo', 'contents' => base64_decode($photo['contents']), 'filename' => $photo['name'], 'headers' => ['Content-Type' => $photo['mime']]];
        } else {
            $client = $client->asJson();
        }
        try {
            $response = $photo ? $client->send($method, $path, ['multipart' => $parts]) : $client->send($method, $path, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        } catch (ConnectionException) {
            Log::warning('mufise.transport_failed', $context + ['reason' => 'transport_error', 'action' => 'retry_later', 'duration_ms' => (int) round((microtime(true) - $started) * 1000)]);
            // Do not chain transport exceptions: they may contain credentials or bodies.
            throw new MufiseException('unavailable', 'Mufise tidak dapat dijangkau. Silakan coba lagi.', 503, 30, 'transport_error', 'retry_later', retryable: true, warehouseRequestId: $localId);
        }
        $format = $data['format'] ?? (str_ends_with($path, '/photo') ? 'photo' : 'json');
        $html = $format !== 'json';
        $problem = $this->problem($response, $html, $localId);
        Log::log($problem === null || $problem->errorCode === 'authorization_pending' ? 'info' : ($response->status() >= 500 ? 'error' : 'warning'), 'mufise.request', $context + [
            'status' => $response->status(), 'request_id' => $this->reference($response),
            'code' => $problem?->errorCode ?? 'ok', 'reason' => $problem?->reason ?? 'ok', 'action' => $problem?->action ?? 'none',
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
        if ($problem) {
            throw $problem;
        }

        if ($html) {
            $mime = strtolower(trim(explode(';', $response->header('Content-Type') ?? '')[0]));
            $allowed = ['html' => ['text/html'], 'pdf' => ['application/pdf'], 'photo' => ['image/png', 'image/jpeg', 'image/webp']];
            if (! in_array($mime, $allowed[$format] ?? [], true)) {
                throw new MufiseException('invalid_response', 'Jenis dokumen Mufise tidak sesuai. Coba kembali atau hubungi admin.', 502, warehouseRequestId: $localId);
            }

            // Photo proxy needs its validated MIME; keep request(): array|string intact.
            return $format === 'photo' ? ['bytes' => $response->body(), 'mime' => $mime] : $response->body();
        }

        return $response->json();
    }

    private function endpoint(string $path): string
    {
        if (in_array($path, ['me', 'projects', 'crew-positions', 'link-requests', 'token', 'disconnect'], true)) {
            return $path;
        }
        $segments = explode('/', $path);
        if ($segments[0] === 'projects') {
            $safe = ['projects', 'crew', 'locations', 'call-sheets', 'editor', 'sync', 'organization-crew', 'preview', 'photo', 'availability', 'points', 'readiness', 'shoot', 'parking', 'hospital', 'check', 'recce', 'lock', 'geocode', 'call-sheet-editor'];

            return 'projects/:project'.(count($segments) > 2 ? '/'.implode('/', array_map(fn ($segment) => in_array($segment, $safe, true) ? $segment : ':resource', array_slice($segments, 2))) : '');
        }

        return 'other';
    }

    private function reference(Response $response): ?string
    {
        $reference = $response->header('X-Request-ID');
        if (! is_string($reference) || ! Str::isUuid($reference)) {
            $reference = $response->json('error.request_id');
        }

        return is_string($reference) && Str::isUuid($reference) ? $reference : null;
    }

    private function fields(Response $response): array
    {
        $fields = $response->json('error.fields');
        $safe = [];
        foreach (is_array($fields) ? $fields : [] as $key => $value) {
            $key = is_array($value) ? ($value['path'] ?? '') : $key;
            if (is_string($key) && preg_match('/^[a-z_]+(?:\.[a-z_0-9]+)*$/', $key) && strlen($key) < 150) {
                $safe[$key] = 'Periksa nilai isian ini.';
            }
        }

        return $safe;
    }

    private function problem(Response $response, bool $html, string $localId): ?MufiseException
    {
        if ($response->successful() && ($html || is_array($response->json()))) {
            return null;
        }
        $code = $response->redirect() ? null : $response->json('error.code');
        $reason = $response->json('error.reason');
        $auth = [
            'connection_credentials_invalid' => ['Konfigurasi koneksi Mufise bermasalah. Hubungi admin Warehouse.', 'check_configuration'],
            'user_token_missing' => ['Token akun belum tersedia. Hubungkan akun jika belum pernah terhubung; jika sudah, hubungi admin Warehouse.', 'check_token_storage'],
            'user_token_invalid' => ['Token akun Mufise tidak cocok. Hubungkan ulang akun Mufise.', 'reconnect_account'],
            'user_token_revoked' => ['Akses akun telah dicabut atau diganti. Hubungkan ulang akun Mufise.', 'reconnect_account'],
            'user_token_expired' => ['Token lama telah kedaluwarsa. Hubungkan ulang akun Mufise.', 'reconnect_account'],
            'email_verification_required' => ['Verifikasi email di Mufise, lalu coba lagi.', 'verify_email'],
            'account_suspended' => ['Akun Mufise ditangguhkan. Hubungi admin Mufise.', 'contact_admin'],
        ];
        $known = [
            'write_scope_denied' => ['Lengkapi izin koneksi dan pasangkan ulang akun untuk persetujuan baru.', 'review_permissions'],
            'editor_permission_required' => ['Fitur editor belum disetujui. Admin perlu meninjau koneksi, lalu pasangkan ulang akun.', 'review_permissions'],
            'connection_policy_changed' => ['Kebijakan koneksi berubah. Muat ulang dan tinjau kembali.', 'reload_data'],
            'duplicate_person' => ['Personel sudah ada. Pilih orang yang cocok dari direktori.', 'review_resource'],
            'duplicate_assignment' => ['Jabatan ini sudah ditugaskan. Perbarui daftar kru.', 'reload_data'],
            'linked_identity' => ['Email dan telepon akun tertaut hanya dapat diubah pemilik di Mufise.', 'correct_request'],
            'day_locked' => ['Hari terkunci. Buka kunci secara eksplisit sebelum mengedit.', 'unlock_in_mufise'],
            'locked_schedule' => ['Kru dipakai pada hari terkunci. Tinjau jadwal sebelum melepas.', 'review_schedule'],
            'same_date_confirmation_required' => ['Tanggal sudah dipakai. Tinjau tanggal dan konfirmasi hari tambahan.', 'review_schedule'],
            'schedule_conflict' => ['Jadwal bentrok. Tinjau peringatan sebelum mengunci.', 'review_schedule'],
            'readiness_order' => ['Selesaikan tahap kesiapan sebelumnya terlebih dahulu.', 'correct_request'],
            'readiness_date' => ['Isi tanggal selesai tahap kesiapan.', 'correct_request'],
            'geocode_unconfigured' => ['Pencarian alamat belum tersedia. Isi koordinat secara manual.', 'correct_request'],
            'geocode_config' => ['Pencarian alamat belum tersedia. Isi koordinat secara manual.', 'correct_request'],
            'geocode_failed' => ['Pencarian alamat gagal. Isi koordinat secara manual.', 'retry_later'],
            'pdf_busy' => ['PDF sedang diproses. Tunggu lalu coba lagi.', 'retry_later'],
            'invalid_credentials' => ['Kredensial Mufise bermasalah. Admin perlu memeriksa konfigurasi dan token akun.', 'check_credentials'],
            'organization_membership_required' => ['Bergabung kembali ke organisasi Mufise melalui pemasangan akun.', 'join_organization'],
            'read_only' => ['Koneksi Mufise hanya mengizinkan pembacaan data. Periksa persetujuan dan izin pengguna.', 'review_permissions'],
            'restricted_area' => ['Bagian proyek ini belum diizinkan di Mufise. Periksa akses Production/Plan.', 'review_permissions'],
            'forbidden' => ['Tindakan ini memerlukan admin organisasi pemilik koneksi Mufise.', 'contact_admin'],
            'server_only' => ['Konfigurasi integrasi perlu diperbaiki agar permintaan dikirim dari server Warehouse.', 'use_server'],
            'not_found' => ['Proyek atau data tidak tersedia. Periksa pilihan proyek dan akses akun.', 'review_resource'],
            'revision_conflict' => ['Data berubah di Mufise. Muat ulang dan tinjau sebelum menyimpan operasi baru.', 'reload_data'],
            'stale_profile' => ['Profil berubah di Mufise. Muat ulang dan tinjau sebelum menyimpan operasi baru.', 'reload_data'],
            'similar_person' => ['Ada personel dengan nama serupa. Konfirmasi hanya jika orangnya berbeda.', 'correct_request'],
            'idempotency_conflict' => ['Operasi sudah dipakai untuk data berbeda. Tinjau operasi sebelum mengirim ulang.', 'review_operation'],
            'link_expired' => ['Pemasangan kedaluwarsa atau telah digunakan. Mulai pemasangan baru.', 'restart_pairing'],
            'authorization_pending' => ['Menunggu persetujuan di Mufise.', 'wait_for_approval'],
            'rate_limited' => ['Batas permintaan Mufise tercapai. Tunggu sebelum mencoba kembali.', 'retry_later'],
            'maintenance' => ['Mufise sedang dalam pemeliharaan. Coba lagi nanti.', 'retry_later'],
            'internal' => ['Mufise belum dapat memproses permintaan. Coba lagi nanti.', 'retry_later'],
        ];
        $validation = ['validation', 'json_required', 'invalid_json', 'too_large', 'idempotency_key_required', 'not_crew', 'consent_required'];
        if ($code === 'invalid_credentials' && is_string($reason) && isset($auth[$reason])) {
            [$message, $action] = $auth[$reason];
        } elseif (is_string($code) && isset($known[$code])) {
            [$message, $action] = $known[$code];
            $reason = $code;
        } elseif (in_array($code, $validation, true)) {
            $reason = $code;
            $message = 'Data belum valid. Periksa kolom wajib, format, ukuran, persetujuan dan ID operasi.';
            $action = 'correct_request';
        } else {
            $code = $reason = 'invalid_response';
            $message = 'Respons Mufise tidak sesuai. Admin perlu memeriksa URL aplikasi dan layanan Mufise.';
            $action = $response->status() >= 500 ? 'retry_later' : 'check_configuration';
        }
        $retryable = in_array($code, ['authorization_pending', 'rate_limited', 'pdf_busy'], true) || $response->status() >= 500;
        $wait = $code === 'authorization_pending' ? 5 : ($code === 'rate_limited' ? 60 : ($retryable ? 30 : 5));
        if ($retryable && ctype_digit((string) $response->header('Retry-After'))) {
            $wait = max($wait, min(3600, (int) $response->header('Retry-After')));
        }
        $status = $response->status() >= 400 ? $response->status() : 502;

        return new MufiseException($code, $message, $status, $wait, $reason, $action, $this->reference($response), $retryable, $localId, $this->fields($response));
    }
}
