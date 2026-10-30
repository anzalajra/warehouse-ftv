<?php

namespace App\Services\Mufise;

use App\Models\MufiseAccount;
use App\Models\Rental;
use App\Services\MufiseClient;

class MufiseRentalService
{
    public function __construct(private MufiseClient $client, private MufiseConnectionService $connections) {}

    public function project(MufiseAccount $account, string $projectId): array
    {
        $bundle = $this->client->request('GET', 'projects/'.rawurlencode($projectId), $this->connections->token($account));
        if (($bundle['project']['id'] ?? '') !== $projectId) {
            throw new MufiseException('invalid_response', 'Respons proyek Mufise tidak sesuai.', 503);
        }

        return $bundle;
    }

    public function assertReady(array $bundle, bool $pickup = false): void
    {
        $ready = ($bundle['readiness'][$pickup ? 'can_collect' : 'can_submit'] ?? false) === true;
        if ($pickup) {
            $day = collect($bundle['call_sheets'] ?? [])->firstWhere('day_number', 1);
            $ready = $ready && ($day['exists'] ?? false) === true && ($day['locked'] ?? false) === true;
        }
        if (! $ready || empty($bundle['project']['id']) || empty($bundle['checked_at'])) {
            $messages = [
                'project_inactive' => 'Proyek harus aktif.',
                'producer_or_director_required' => 'Tambahkan minimal satu produser atau sutradara.',
                'location_required' => 'Lengkapi nama dan alamat minimal satu lokasi.',
                'plan_access_required' => 'Izinkan akses Plan pada proyek Mufise.',
                'day_1_locked_call_sheet_required' => 'Call sheet Day 1 harus tersedia dan terkunci.',
            ];
            $missing = array_map(fn ($code) => $messages[$code] ?? 'Lengkapi persyaratan proyek di Mufise.', $bundle['readiness']['missing'] ?? []);
            throw new MufiseException('project_not_ready', implode(' ', $missing) ?: 'Proyek Mufise belum memenuhi persyaratan peminjaman.');
        }
    }

    public function accountFor(Rental $rental): MufiseAccount
    {
        $account = MufiseAccount::find($rental->mufise_account_id);
        if (! $account || (int) $account->warehouse_user_id !== (int) $rental->user_id || ! $rental->mufise_project_id) {
            throw new MufiseException('reconnect_required', 'Hubungan proyek dengan akun peminjam tidak sesuai.');
        }

        return $account;
    }

    public function verifyPickup(Rental $rental): void
    {
        if (! $rental->mufise_required) {
            return;
        }
        $account = $this->accountFor($rental);
        $bundle = $this->project($account, $rental->mufise_project_id);
        $this->assertReady($bundle, true);
        $day = collect($bundle['call_sheets'])->firstWhere('day_number', 1);
        $document = $this->client->request('GET', 'projects/'.rawurlencode($rental->mufise_project_id).'/call-sheets/'.rawurlencode($day['day_id']), $this->connections->token($account));
        if (($document['locked'] ?? false) !== true || ($document['day_number'] ?? null) !== 1 || ($document['day_id'] ?? '') !== $day['day_id'] || ($document['schedule_revision'] ?? null) !== ($bundle['schedule_revision'] ?? null)) {
            throw new MufiseException('revision_conflict', 'Call sheet berubah selama pemeriksaan. Coba validasi pengambilan kembali.');
        }
        $html = $this->client->request('GET', 'projects/'.rawurlencode($rental->mufise_project_id).'/call-sheets/'.rawurlencode($day['day_id']), $this->connections->token($account), ['format' => 'html']);
        $latest = $this->project($account, $rental->mufise_project_id);
        $this->assertReady($latest, true);
        $latestDay = collect($latest['call_sheets'])->firstWhere('day_number', 1);
        if (($latest['schedule_revision'] ?? null) !== ($bundle['schedule_revision'] ?? null) || ($latestDay['day_id'] ?? '') !== $day['day_id']) {
            throw new MufiseException('revision_conflict', 'Call sheet berubah selama pemeriksaan. Coba validasi pengambilan kembali.');
        }
        $rental->mufise_pickup_snapshot = ['checked_at' => $latest['checked_at'], 'schedule_revision' => $latest['schedule_revision'] ?? null, 'day_id' => $day['day_id'], 'data' => $latest, 'call_sheet' => $document];
        $rental->mufise_call_sheet_html = $html;
    }
}
