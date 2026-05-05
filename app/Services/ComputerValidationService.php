<?php

namespace App\Services;

use App\Models\ComputerBooking;
use App\Models\ComputerBookingSlot;
use App\Models\ComputerMaintenanceLog;
use App\Models\Setting;
use Carbon\Carbon;

class ComputerValidationService
{
    public static function quotaHoursPerWeek(): int
    {
        return (int) (Setting::get('computer_quota_hours_per_week') ?? 6);
    }

    public static function quotaSlotsPerDay(): int
    {
        return (int) (Setting::get('computer_quota_slots_per_day') ?? 1);
    }

    public static function noShowGraceMinutes(): int
    {
        return (int) (Setting::get('computer_no_show_grace_minutes') ?? 30);
    }

    public static function tncText(): string
    {
        return (string) (Setting::get('computer_tnc_text') ?? 'Dengan melakukan booking, saya menyetujui aturan penggunaan lab komputer FTV UPI.');
    }

    /**
     * Validate booking date/time. Returns ['ok' => bool, 'error' => string|null].
     */
    public static function validateBookingTime(Carbon $date, string $start, string $end): array
    {
        if ($start >= $end) {
            return ['ok' => false, 'error' => 'Waktu mulai harus sebelum waktu selesai.'];
        }

        $startsAt = Carbon::parse($date->toDateString().' '.$start);
        if ($startsAt->isPast()) {
            return ['ok' => false, 'error' => 'Tidak bisa booking di waktu yang sudah lewat.'];
        }

        $matchesSlot = ComputerBookingSlot::active()
            ->forDay($date->dayOfWeek)
            ->where('start_time', '<=', $start)
            ->where('end_time', '>=', $end)
            ->exists();

        if (! $matchesSlot) {
            return ['ok' => false, 'error' => 'Slot waktu tidak sesuai dengan jadwal operasional yang ditetapkan admin.'];
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * Cek bentrok terhadap booking lain + maintenance windows.
     */
    public static function checkComputerAvailability(int $computerId, Carbon $date, string $start, string $end, ?int $excludeBookingId = null): bool
    {
        $startsAt = Carbon::parse($date->toDateString().' '.$start);
        $endsAt = Carbon::parse($date->toDateString().' '.$end);

        $maintenance = ComputerMaintenanceLog::where('computer_id', $computerId)
            ->where(function ($q) use ($startsAt, $endsAt) {
                $q->where('started_at', '<', $endsAt)
                    ->where(function ($q2) use ($startsAt) {
                        $q2->whereNull('ended_at')->orWhere('ended_at', '>', $startsAt);
                    });
            })
            ->exists();

        if ($maintenance) {
            return false;
        }

        return ! ComputerBooking::where('computer_id', $computerId)
            ->whereIn('status', ComputerBooking::ACTIVE_STATUSES)
            ->whereDate('booking_date', $date->toDateString())
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->exists();
    }

    /**
     * Cek quota mingguan + harian.
     */
    public static function checkUserQuota(int $userId, Carbon $date, float $hoursToAdd, ?int $excludeBookingId = null): array
    {
        $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $weeklyQuery = ComputerBooking::where('user_id', $userId)
            ->whereIn('status', ComputerBooking::ACTIVE_STATUSES)
            ->whereBetween('booking_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId));

        $weeklyHours = $weeklyQuery->get()->sum(fn (ComputerBooking $b) => $b->getDurationHours());

        $weeklyMax = self::quotaHoursPerWeek();
        if ($weeklyMax > 0 && ($weeklyHours + $hoursToAdd) > $weeklyMax) {
            return [
                'ok' => false,
                'reason' => "Melebihi kuota mingguan ({$weeklyMax} jam/minggu). Sudah terpakai {$weeklyHours} jam.",
            ];
        }

        $dailyCount = ComputerBooking::where('user_id', $userId)
            ->whereIn('status', ComputerBooking::ACTIVE_STATUSES)
            ->whereDate('booking_date', $date->toDateString())
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->count();

        $dailyMax = self::quotaSlotsPerDay();
        if ($dailyMax > 0 && ($dailyCount + 1) > $dailyMax) {
            return [
                'ok' => false,
                'reason' => "Melebihi kuota harian ({$dailyMax} slot/hari).",
            ];
        }

        return ['ok' => true, 'reason' => null];
    }

    /**
     * Return list of available slots for a computer on a date.
     * Each entry: ['start' => 'HH:MM', 'end' => 'HH:MM', 'available' => bool].
     */
    public static function getAvailableSlots(int $computerId, Carbon $date): array
    {
        $slots = ComputerBookingSlot::active()
            ->forDay($date->dayOfWeek)
            ->orderBy('start_time')
            ->get();

        $result = [];
        foreach ($slots as $slot) {
            $start = $slot->start_time;
            $end = $slot->end_time;
            $available = self::checkComputerAvailability($computerId, $date, $start, $end);

            $startsAt = Carbon::parse($date->toDateString().' '.$start);
            if ($startsAt->isPast()) {
                $available = false;
            }

            $result[] = [
                'start' => $start,
                'end' => $end,
                'available' => $available,
            ];
        }

        return $result;
    }
}
