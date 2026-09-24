<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Actions\ReminderPickupReturnAction;
use App\Filament\Resources\Rentals\RentalResource;
use App\Filament\Resources\Rentals\Widgets\RentalStatsOverview;
use App\Models\Rental;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class ListRentals extends ListRecords
{
    protected static string $resource = RentalResource::class;

    protected string $view = 'filament.resources.rentals.pages.list-rentals';

    public string $currentView = 'list';

    public function mount(): void
    {
        // The scheduler performs this automatically; also reconcile overdue quotes
        // when admin opens the list so an unconfirmed order cannot be confirmed late.
        Rental::where('status', Rental::STATUS_QUOTATION)
            ->where('start_date', '<', now())
            ->chunkById(100, fn ($rentals) => $rentals->each->checkAndUpdateLateStatus());

        parent::mount();
    }

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
            Rental::STATUS_PARTIAL_RETURN => 'Partial Return',
            Rental::STATUS_COMPLETED => 'Completed',
            Rental::STATUS_CANCELLED => 'Cancelled',
            Rental::STATUS_EXPIRED => 'Expired',
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
