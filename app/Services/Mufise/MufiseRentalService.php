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
        if ($pickup && $this->callSheetRequirement($bundle) !== 'none') {
            $day = collect($bundle['call_sheets'] ?? [])->firstWhere('day_number', 1);
            $ready = $ready && ($day['exists'] ?? false) === true && ($this->callSheetRequirement($bundle) !== 'locked' || ($day['locked'] ?? false) === true);
        }
        $localMissing = $this->localMissing($bundle);
        if (! $ready || $localMissing || empty($bundle['project']['id']) || empty($bundle['checked_at'])) {
            $messages = [
                'crew_required' => 'Tambahkan minimal satu kru bernama.',
                'required_crew_roles_missing' => 'Lengkapi seluruh jabatan kru yang diwajibkan.',
                'day_1_call_sheet_required' => 'Call sheet Day 1 harus memiliki kegiatan.',
                'project_inactive' => 'Proyek harus aktif.',
                'producer_or_director_required' => 'Tambahkan minimal satu produser atau sutradara.',
                'location_required' => 'Lengkapi nama dan alamat minimal satu lokasi.',
                'plan_access_required' => 'Izinkan akses Plan pada proyek Mufise.',
                'day_1_locked_call_sheet_required' => 'Call sheet Day 1 harus tersedia dan terkunci.',
            ];
            $missing = array_map(fn ($code) => $messages[$code] ?? 'Lengkapi persyaratan proyek di Mufise.', $bundle['readiness']['missing_by_stage'][$pickup ? 'collect' : 'submit'] ?? $bundle['readiness']['missing'] ?? []);
            $roles = $bundle['readiness']['missing_crew_roles'][$pickup ? 'collect' : 'submit'] ?? [];
            if ($roles) {
                $missing[] = 'Jabatan belum terisi: '.implode(', ', array_filter($roles, 'is_string')).'.';
            }
            $missing = array_merge($missing, $localMissing);
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
        $requirement = $this->callSheetRequirement($bundle);
        $day = collect($bundle['call_sheets'] ?? [])->firstWhere('day_number', 1);
        $document = $html = null;
        if ($requirement !== 'none') {
            $path = 'projects/'.rawurlencode($rental->mufise_project_id).'/call-sheets/'.rawurlencode($day['day_id']);
            $document = $this->client->request('GET', $path, $this->connections->token($account));
            if (($requirement === 'locked' && ($document['locked'] ?? false) !== true)
                || ($document['exists'] ?? true) !== true
                || ($document['day_number'] ?? null) !== 1
                || ($document['day_id'] ?? '') !== $day['day_id']
                || ($document['schedule_revision'] ?? null) !== ($bundle['schedule_revision'] ?? null)) {
                throw new MufiseException('revision_conflict', 'Call sheet berubah selama pemeriksaan. Coba validasi pengambilan kembali.');
            }
            $html = $this->client->request('GET', $path, $this->connections->token($account), ['format' => 'html']);
        }
        $latest = $this->project($account, $rental->mufise_project_id);
        $this->assertReady($latest, true);
        foreach (['policy_revision', 'sync_version', 'schedule_revision'] as $field) {
            if (($latest[$field] ?? null) !== ($bundle[$field] ?? null)) {
                throw new MufiseException('revision_conflict', 'Data atau kebijakan Mufise berubah selama pemeriksaan. Verifikasi pengambilan kembali.');
            }
        }
        $latestDay = collect($latest['call_sheets'] ?? [])->firstWhere('day_number', 1);
        if ($requirement !== 'none' && ($latestDay['day_id'] ?? null) !== ($day['day_id'] ?? null)) {
            throw new MufiseException('revision_conflict', 'Day 1 berubah selama pemeriksaan. Verifikasi pengambilan kembali.');
        }
        $rental->mufise_pickup_snapshot = [
            'checked_at' => $latest['checked_at'], 'requirements' => $latest['requirements'] ?? null,
            'warehouse_requirements' => MufiseSettings::bookingRequirements(),
            'policy_revision' => $latest['policy_revision'] ?? null, 'sync_version' => $latest['sync_version'] ?? null,
            'schedule_revision' => $latest['schedule_revision'] ?? null,
            'day_id' => $requirement === 'none' ? null : $day['day_id'], 'data' => $latest, 'call_sheet' => $document,
        ];
        $rental->mufise_call_sheet_html = $html;
    }

    public function callSheetRequirement(array $bundle): string
    {
        // Legacy servers retain their original collection policy.
        $requirements = $bundle['requirements'] ?? ['submit' => ['call_sheet' => 'none'], 'collect' => ['call_sheet' => 'locked']];
        $rules = [$requirements['submit']['call_sheet'] ?? 'none', $requirements['collect']['call_sheet'] ?? 'none', MufiseSettings::bookingRequirements()['call_sheet']];

        return in_array('locked', $rules, true) ? 'locked' : (in_array('exists', $rules, true) ? 'exists' : 'none');
    }

    public function localMissing(array $bundle): array
    {
        $rules = MufiseSettings::bookingRequirements();
        $crew = collect($bundle['crew'] ?? [])->filter(fn ($person) => trim($person['name'] ?? '') !== '');
        $missing = [];
        if ($rules['crew'] === 'any' && $crew->isEmpty()) {
            $missing[] = 'Warehouse mewajibkan minimal satu kru.';
        }
        if ($rules['crew'] === 'producer_or_director' && ! $crew->contains(fn ($person) => in_array(strtolower(trim($person['role'] ?? '')), ['producer', 'produser', 'director', 'sutradara'], true))) {
            $missing[] = 'Warehouse mewajibkan produser atau sutradara.';
        }
        if ($rules['location'] && ! collect($bundle['locations'] ?? [])->contains(fn ($location) => trim($location['name'] ?? '') !== '' && trim($location['address'] ?? '') !== '')) {
            $missing[] = 'Warehouse mewajibkan lokasi beserta alamat.';
        }
        $day = collect($bundle['call_sheets'] ?? [])->firstWhere('day_number', 1);
        if ($rules['call_sheet'] !== 'none' && (! ($day['exists'] ?? false) || ($rules['call_sheet'] === 'locked' && ! ($day['locked'] ?? false)))) {
            $missing[] = $rules['call_sheet'] === 'locked' ? 'Warehouse mewajibkan call sheet Day 1 terkunci sebelum booking.' : 'Warehouse mewajibkan call sheet Day 1 sebelum booking.';
        }

        return $missing;
    }
}
