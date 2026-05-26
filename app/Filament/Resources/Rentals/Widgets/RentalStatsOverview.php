<?php

namespace App\Filament\Resources\Rentals\Widgets;

use App\Models\Rental;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RentalStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $tomorrow = now()->addDay()->startOfDay();
        $endOfToday = now()->endOfDay();
        $endOfTomorrow = now()->addDay()->endOfDay();

        $pickupStatuses = [
            Rental::STATUS_CONFIRMED,
            Rental::STATUS_QUOTATION,
            Rental::STATUS_LATE_PICKUP,
        ];

        $todayPickup = Rental::whereIn('status', $pickupStatuses)
            ->whereBetween('start_date', [$today, $endOfToday])
            ->count();

        $tomorrowPickup = Rental::whereIn('status', $pickupStatuses)
            ->whereBetween('start_date', [$tomorrow, $endOfTomorrow])
            ->count();

        $confirmed = Rental::where('status', Rental::STATUS_CONFIRMED)->count();

        $clickable = ['style' => 'cursor: pointer;'];

        return [
            Stat::make('Today Pickup', $todayPickup)
                ->description('Scheduled to pickup today')
                ->descriptionIcon('heroicon-m-truck')
                ->color('warning')
                ->extraAttributes(array_merge($clickable, [
                    'wire:click' => "\$dispatch('filter-rentals', { scope: 'today_pickup' })",
                ])),

            Stat::make('Tomorrow Pickup', $tomorrowPickup)
                ->description('Scheduled to pickup tomorrow')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info')
                ->extraAttributes(array_merge($clickable, [
                    'wire:click' => "\$dispatch('filter-rentals', { scope: 'tomorrow_pickup' })",
                ])),

            Stat::make('Confirmed', $confirmed)
                ->description('Confirmed rentals')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->extraAttributes(array_merge($clickable, [
                    'wire:click' => "\$dispatch('filter-rentals', { scope: 'confirmed' })",
                ])),
        ];
    }
}
