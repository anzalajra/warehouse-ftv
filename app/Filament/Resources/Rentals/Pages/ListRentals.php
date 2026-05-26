<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Actions\ReminderPickupReturnAction;
use App\Filament\Resources\Rentals\RentalResource;
use App\Filament\Resources\Rentals\Widgets\RentalStatsOverview;
use App\Models\Rental;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class ListRentals extends ListRecords
{
    protected static string $resource = RentalResource::class;

    protected string $view = 'filament.resources.rentals.pages.list-rentals';

    public string $currentView = 'kanban';

    #[On('filter-rentals')]
    public function applyRentalScope(string $scope): void
    {
        $this->currentView = 'list';

        $pickupStatuses = [
            \App\Models\Rental::STATUS_CONFIRMED,
            \App\Models\Rental::STATUS_QUOTATION,
            \App\Models\Rental::STATUS_LATE_PICKUP,
        ];

        $this->resetTableFiltersForm();

        match ($scope) {
            'today_pickup' => $this->tableFilters = [
                'status' => ['values' => $pickupStatuses],
                'start_date' => [
                    'from' => now()->startOfDay()->toDateString(),
                    'until' => now()->endOfDay()->toDateString(),
                ],
            ],
            'tomorrow_pickup' => $this->tableFilters = [
                'status' => ['values' => $pickupStatuses],
                'start_date' => [
                    'from' => now()->addDay()->startOfDay()->toDateString(),
                    'until' => now()->addDay()->endOfDay()->toDateString(),
                ],
            ],
            'confirmed' => $this->tableFilters = [
                'status' => ['values' => [\App\Models\Rental::STATUS_CONFIRMED]],
            ],
            default => null,
        };
    }

    public function mount(): void
    {
        $this->updateLateStatuses();
        
        parent::mount();
    }

    public function setView(string $view): void
    {
        $this->currentView = $view;
    }

    public function getStatuses(): array
    {
        return [
            Rental::STATUS_QUOTATION => 'Quotation',
            Rental::STATUS_CONFIRMED => 'Confirmed',
            Rental::STATUS_LATE_PICKUP => 'Late Pickup',
            Rental::STATUS_ACTIVE => 'Active',
            Rental::STATUS_LATE_RETURN => 'Late Return',
            Rental::STATUS_COMPLETED => 'Completed',
            Rental::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public function getKanbanRecords(): Collection
    {
        return Rental::query()
            ->with(['customer', 'items'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('status');
    }

    protected function updateLateStatuses(): void
    {
        $now = now();

        // Update late pickups - gunakan DB::table untuk bypass model events
        DB::table('rentals')
            ->whereIn('status', ['quotation', 'confirmed'])
            ->where('start_date', '<', $now)
            ->update(['status' => 'late_pickup', 'updated_at' => $now]);

        // Update late returns
        DB::table('rentals')
            ->where('status', 'active')
            ->where('end_date', '<', $now)
            ->update(['status' => 'late_return', 'updated_at' => $now]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ReminderPickupReturnAction::make(),
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RentalStatsOverview::class,
        ];
    }
}