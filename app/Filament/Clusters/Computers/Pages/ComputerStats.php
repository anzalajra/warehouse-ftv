<?php

namespace App\Filament\Clusters\Computers\Pages;

use App\Filament\Clusters\Computers\ComputersCluster;
use App\Models\ComputerBooking;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ComputerStats extends Page
{
    protected static ?string $cluster = ComputersCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Statistik';

    protected static ?string $title = 'Statistik Penggunaan Komputer';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.computer-stats';

    public string $period = '30d';

    protected function periodStart(): Carbon
    {
        return match ($this->period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            'all' => Carbon::create(2000, 1, 1),
            default => now()->subDays(30),
        };
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }

    public function getViewData(): array
    {
        $start = $this->periodStart();

        $base = ComputerBooking::query()
            ->whereNotNull('actual_duration_seconds')
            ->where('booking_date', '>=', $start->toDateString());

        $totalSeconds = (int) (clone $base)->sum('actual_duration_seconds');
        $sessionCount = (clone $base)->count();
        $uniqueUsers = (clone $base)->whereNotNull('user_id')->distinct('user_id')->count('user_id');

        $topComputers = (clone $base)
            ->select('computer_id', DB::raw('SUM(actual_duration_seconds) as total_seconds'), DB::raw('COUNT(*) as session_count'))
            ->with('computer:id,name')
            ->groupBy('computer_id')
            ->orderByDesc('total_seconds')
            ->limit(10)
            ->get();

        $topUsers = (clone $base)
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('SUM(actual_duration_seconds) as total_seconds'), DB::raw('COUNT(*) as session_count'))
            ->with('user:id,name,email')
            ->groupBy('user_id')
            ->orderByDesc('total_seconds')
            ->limit(20)
            ->get();

        $statusBreakdown = ComputerBooking::query()
            ->where('booking_date', '>=', $start->toDateString())
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $dailyUsage = (clone $base)
            ->select(DB::raw('DATE(booking_date) as day'), DB::raw('SUM(actual_duration_seconds) as total_seconds'))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return [
            'period' => $this->period,
            'totalHours' => round($totalSeconds / 3600, 1),
            'sessionCount' => $sessionCount,
            'uniqueUsers' => $uniqueUsers,
            'topComputers' => $topComputers,
            'topUsers' => $topUsers,
            'statusBreakdown' => $statusBreakdown,
            'dailyUsage' => $dailyUsage,
        ];
    }
}
