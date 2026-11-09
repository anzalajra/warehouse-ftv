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

    public function assertReady(array $bundle): void
    {
        $ready = ($bundle['readiness']['can_submit'] ?? false) === true;
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
            $missing = array_map(fn ($code) => $messages[$code] ?? 'Lengkapi persyaratan proyek di Mufise.', $bundle['readiness']['missing_by_stage']['submit'] ?? $bundle['readiness']['missing'] ?? []);
            $roles = $bundle['readiness']['missing_crew_roles']['submit'] ?? [];
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
