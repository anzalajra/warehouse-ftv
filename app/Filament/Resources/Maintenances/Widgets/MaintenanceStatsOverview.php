<?php

namespace App\Filament\Resources\Maintenances\Widgets;

use App\Models\MaintenanceRecord;
use App\Models\ProductUnit;
use App\Models\Setting;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MaintenanceStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $inMaintenance = ProductUnit::where('status', ProductUnit::STATUS_MAINTENANCE)->count();

        $brokenLost = ProductUnit::whereIn('condition', ['broken', 'lost'])->count();

        $costThisMonth = (float) MaintenanceRecord::query()
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('cost');

        $qcInterval = max(1, (int) Setting::get('maintenance_qc_interval_days', 90));
        $qcDue = ProductUnit::where('status', ProductUnit::STATUS_AVAILABLE)
            ->where(fn ($q) => $q
                ->whereNull('last_checked_at')
                ->orWhere('last_checked_at', '<', now()->subDays($qcInterval)))
            ->count();

        $completed = MaintenanceRecord::query()
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDays(30))
            ->get(['started_at', 'completed_at']);

        $avgTurnaround = $completed->isEmpty()
            ? '—'
            : round($completed->avg(fn ($r) => abs($r->started_at->diffInDays($r->completed_at))), 1).' hari';

        return [
            Stat::make('Sedang Maintenance', $inMaintenance)
                ->description('Unit berstatus maintenance')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('warning'),

            Stat::make('Broken / Lost', $brokenLost)
                ->description('Kondisi unit rusak/hilang')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($brokenLost > 0 ? 'danger' : 'gray'),

            Stat::make('Biaya Bulan Ini', 'Rp '.number_format($costThisMonth, 0, ',', '.'))
                ->description('Total biaya maintenance')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('QC Jatuh Tempo', $qcDue)
                ->description("Belum di-QC > {$qcInterval} hari")
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($qcDue > 0 ? 'warning' : 'success'),

            Stat::make('Rata-rata Turnaround', $avgTurnaround)
                ->description('Selesai dalam 30 hari terakhir')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
        ];
    }
}
