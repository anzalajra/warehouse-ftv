<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Resources\Rentals\RentalResource;
use App\Models\Rental;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class RentalKanbanBoard extends Page
{
    protected static string $resource = RentalResource::class;

    protected string $view = 'filament.resources.rentals.pages.rental-kanban-board';

    protected static ?string $title = 'Rental Kanban Board';

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

    public function getRecords(): Collection
    {
        return Rental::query()
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('status');
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('list_view')
                ->label('List View')
                ->icon('heroicon-o-list-bullet')
                ->url(RentalResource::getUrl('index')),
        ];
    }
}
