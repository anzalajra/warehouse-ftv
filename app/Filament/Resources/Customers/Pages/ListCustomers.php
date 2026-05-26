<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Widgets\CustomerStatsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    #[On('filter-customers')]
    public function applyCustomerScope(string $scope): void
    {
        $this->resetTableFiltersForm();

        match ($scope) {
            'pending' => $this->tableFilters = [
                'verification_status' => ['value' => 'pending'],
            ],
            'verified' => $this->tableFilters = [
                'verification_status' => ['value' => 'verified'],
            ],
            'not_verified' => $this->tableFilters = [
                'verification_status' => ['value' => 'not_verified'],
            ],
            'all' => null,
            default => null,
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CustomerStatsOverview::class,
        ];
    }
}
