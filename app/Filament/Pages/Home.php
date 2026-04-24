<?php

namespace App\Filament\Pages;

use App\Models\Rental;
use BackedEnum;
use Filament\Pages\Dashboard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class Home extends Dashboard
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Home';
    protected static ?string $title = 'Home';
    protected static ?int $navigationSort = -1;

    protected static string $routePath = '/home';

    protected string $view = 'filament.pages.home';

    public function getStats(): array
    {
        return Cache::remember('home_dashboard_stats_v1', 60, function () {
            $today = Carbon::today();
            $monthStart = Carbon::now()->startOfMonth();
            $monthEnd = Carbon::now()->endOfMonth();

            $active = Rental::whereIn('status', [
                Rental::STATUS_ACTIVE,
                Rental::STATUS_PARTIAL_RETURN,
            ])->count();

            $quotations = Rental::where('status', Rental::STATUS_QUOTATION)->count();

            $pickupsToday = Rental::whereDate('start_date', $today)
                ->whereIn('status', [
                    Rental::STATUS_CONFIRMED,
                    Rental::STATUS_ACTIVE,
                    Rental::STATUS_LATE_PICKUP,
                ])->count();

            $returnsToday = Rental::whereDate('end_date', $today)
                ->whereIn('status', [
                    Rental::STATUS_ACTIVE,
                    Rental::STATUS_PARTIAL_RETURN,
                    Rental::STATUS_LATE_RETURN,
                ])->count();

            $overdue = Rental::whereIn('status', [
                Rental::STATUS_LATE_PICKUP,
                Rental::STATUS_LATE_RETURN,
            ])->count();

            $revenue = (float) Rental::where('status', Rental::STATUS_COMPLETED)
                ->whereBetween('end_date', [$monthStart, $monthEnd])
                ->sum('total');

            return [
                'active' => $active,
                'quotations' => $quotations,
                'pickups' => $pickupsToday,
                'returns' => $returnsToday,
                'overdue' => $overdue,
                'revenue' => $revenue,
            ];
        });
    }

    public function getTodaysSchedule(): array
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        return Rental::query()
            ->with(['customer:id,name'])
            ->where(function ($q) use ($today, $tomorrow) {
                $q->whereBetween('start_date', [$today, $tomorrow])
                  ->orWhereBetween('end_date', [$today, $tomorrow]);
            })
            ->whereNotIn('status', [Rental::STATUS_CANCELLED])
            ->orderBy('start_date')
            ->limit(6)
            ->get()
            ->map(function ($r) use ($today) {
                $isPickup = $r->start_date->isSameDay($today);
                return [
                    'id' => $r->id,
                    'code' => $r->rental_code,
                    'customer' => $r->customer?->name ?? '—',
                    'start_time' => $r->start_date->format('H:i'),
                    'end_time' => $r->end_date->format('H:i'),
                    'kind' => $isPickup ? 'Pickup' : 'Return',
                    'status' => $r->status,
                ];
            })
            ->toArray();
    }

    public function getRecentBookings(): array
    {
        return Rental::query()
            ->with(['customer:id,name'])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'code' => $r->rental_code,
                    'customer' => $r->customer?->name ?? '—',
                    'status' => $r->status,
                    'start' => $r->start_date?->format('j M'),
                    'total' => 'Rp ' . number_format((float) $r->total, 0, ',', '.'),
                ];
            })
            ->toArray();
    }

    public function getMenuItems(): array
    {
        $stats = $this->getStats();
        return [
            ['id' => 'schedule',   'label' => 'Schedule',   'icon' => 'calendar',  'desc' => 'Rental calendar',        'url' => url('/admin/schedule'),   'badge' => $stats['pickups'] + $stats['returns']],
            ['id' => 'bookings',   'label' => 'Bookings',   'icon' => 'bookings',  'desc' => 'Active rentals & quotes','url' => url('/admin/rentals'),    'badge' => $stats['active']],
            ['id' => 'inventory',  'label' => 'Inventory',  'icon' => 'box',       'desc' => 'Products & units',       'url' => url('/admin/products'),   'badge' => null],
            ['id' => 'deliveries', 'label' => 'Deliveries', 'icon' => 'truck',     'desc' => 'Pickup & return',        'url' => url('/admin/deliveries'), 'badge' => null],
            ['id' => 'customers',  'label' => 'Customers',  'icon' => 'users',     'desc' => 'Customers directory',    'url' => url('/admin/customers'),  'badge' => null],
            ['id' => 'invoices',   'label' => 'Invoices',   'icon' => 'invoice',   'desc' => 'Payments & billing',     'url' => url('/admin/invoices'),   'badge' => null],
            ['id' => 'quotations', 'label' => 'Quotations', 'icon' => 'document',  'desc' => 'Pending quotes',         'url' => url('/admin/quotations'), 'badge' => $stats['quotations']],
            ['id' => 'overdue',    'label' => 'Overdue',    'icon' => 'bell',      'desc' => 'Late pickups & returns', 'url' => url('/admin/rentals?tableFilters[status][value]=late_pickup'), 'badge' => $stats['overdue']],
        ];
    }

    public function getGreeting(): string
    {
        $hour = (int) now()->format('H');
        $name = Auth::user()?->name ?? '';
        if ($hour < 11) return "Selamat pagi, {$name} 👋";
        if ($hour < 15) return "Selamat siang, {$name} 👋";
        if ($hour < 18) return "Selamat sore, {$name} 👋";
        return "Selamat malam, {$name} 👋";
    }
}
