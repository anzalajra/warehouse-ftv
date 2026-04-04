<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Rental;
use App\Models\ProductUnit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '60s';

    protected int | array | null $columns = [
        'default' => 1,
        'sm' => 2,
        'md' => 3,
        'xl' => 3,
    ];

    protected function getStats(): array
    {
        $stats = Cache::remember('dashboard_stats', 300, function () {
            return [
                'todayRentals' => Rental::whereDate('created_at', today())->count(),
                'todayRevenue' => Rental::whereDate('created_at', today())->sum('total'),
                'activeRentals' => Rental::whereIn('status', ['active', 'late_pickup', 'late_return'])->count(),
                'pendingRentals' => Rental::where('status', 'quotation')->count(),
                'availableUnits' => ProductUnit::where('status', 'available')->count(),
                'rentedUnits' => ProductUnit::where('status', 'rented')->count(),
                'totalCustomers' => User::whereDoesntHave('roles')->count(),
                'verifiedCustomers' => User::whereDoesntHave('roles')->where('is_verified', true)->count(),
                'pendingVerification' => User::whereDoesntHave('roles')->where('is_verified', false)->count(),
            ];
        });

        return [
            Stat::make('Today\'s Rentals', $stats['todayRentals'])
                ->description('New bookings today')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Today\'s Revenue', 'Rp ' . number_format($stats['todayRevenue'], 0, ',', '.'))
                ->description('Total revenue today')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Active Rentals', $stats['activeRentals'])
                ->description($stats['pendingRentals'] . ' quotations')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Equipment Status', $stats['availableUnits'] . ' / ' . ($stats['availableUnits'] + $stats['rentedUnits']))
                ->description($stats['rentedUnits'] . ' currently rented')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),

            Stat::make('Customers', $stats['totalCustomers'])
                ->description($stats['verifiedCustomers'] . ' verified')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Pending Verification', $stats['pendingVerification'])
                ->description('Awaiting document review')
                ->descriptionIcon('heroicon-m-document-check')
                ->color('danger'),
        ];
    }
}