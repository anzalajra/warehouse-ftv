<?php

namespace App\Services;

use App\Services\Mufise\MufiseException;
use App\Services\Mufise\MufiseSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MufiseClient
{
    public function request(string $method, string $path, ?string $token = null, array $data = [], ?string $operationId = null): array|string
    {
        if (! MufiseSettings::enabled() || ! MufiseSettings::url() || ! MufiseSettings::connectionId() || ! MufiseSettings::key()) {
            throw new MufiseException('not_configured', 'Koneksi Mufise belum diaktifkan atau dikonfigurasi admin.', 503);
        }
        MufiseSettings::validateUrl(MufiseSettings::url());
        $client = Http::baseUrl(MufiseSettings::url().'/api/external/v1/')
            ->acceptJson()->asJson()->connectTimeout(5)->timeout(20)->withoutRedirecting()
            ->withHeaders(['X-Mufise-Connection' => MufiseSettings::connectionId(), 'X-API-Key' => MufiseSettings::key()]);
        if ($token) {
            $client = $client->withToken($token);
        }
        if ($operationId) {
            $client = $client->withHeaders(['Idempotency-Key' => $operationId]);
        }
        try {
            $response = $client->send($method, $path, strtoupper($method) === 'GET' ? ['query' => $data] : ['json' => $data]);
        } catch (ConnectionException) {
            throw new MufiseException('unavailable', 'Mufise tidak dapat dijangkau. Silakan coba lagi.', 503);
        }
        if (! $response->successful()) {
            $code = (string) $response->json('error.code', 'unavailable');
            $messages = [
                'authorization_pending' => 'Menunggu persetujuan di Mufise.',
                'invalid_credentials' => 'Kredensial Mufise tidak berlaku. Periksa konfigurasi atau hubungkan ulang akun.',
                'organization_membership_required' => 'Bergabung kembali ke organisasi Mufise melalui pemasangan akun.',
                'link_expired' => 'Pemasangan kedaluwarsa atau telah digunakan. Mulai pemasangan baru.',
                'revision_conflict' => 'Data berubah di Mufise. Muat ulang dan tinjau sebelum menyimpan operasi baru.',
                'stale_profile' => 'Profil berubah di Mufise. Muat ulang dan tinjau sebelum menyimpan operasi baru.',
                'similar_person' => 'Ada personel dengan nama serupa. Konfirmasi hanya jika orangnya berbeda.',
                'rate_limited' => 'Batas permintaan Mufise tercapai. Tunggu sebelum mencoba kembali.',
                'read_only' => 'Koneksi Mufise hanya mengizinkan pembacaan data.',
                'restricted_area' => 'Bagian proyek ini belum diizinkan di Mufise.',
            ];
            $requestId = $response->json('error.request_id');
            // Never propagate upstream request objects, credentials or arbitrary response bodies to logs/browser.
            throw new MufiseException($code, ($messages[$code] ?? 'Permintaan ditolak Mufise. Periksa data dan izin proyek.').($requestId ? ' Referensi: '.preg_replace('/[^a-zA-Z0-9_-]/', '', substr($requestId, 0, 100)) : ''), $response->status(), max(5, min(3600, (int) $response->header('Retry-After'))));
        }
        if (($data['format'] ?? '') === 'html') {
            return $response->body();
        }
        $json = $response->json();
        if (! is_array($json)) {
            throw new MufiseException('invalid_response', 'Respons Mufise tidak valid.', 503);
        }

        return $json;
    }
}
