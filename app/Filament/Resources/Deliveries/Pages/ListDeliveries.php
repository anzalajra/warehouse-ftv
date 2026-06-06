<?php

namespace App\Filament\Resources\Deliveries\Pages;

use App\Filament\Resources\Deliveries\DeliveryResource;
use App\Models\Rental;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDeliveries extends ListRecords
{
    protected static string $resource = DeliveryResource::class;

    public function getTitle(): string
    {
        return 'Deliveries';
    }

    /**
     * The deliveries board is a movement-centric view of rentals: one row per
     * rental that has (or needs) a surat jalan, rather than one row per Delivery.
     * Clicking a row opens that rental's delivery hub.
     */
    protected function getTableQuery(): ?Builder
    {
        return Rental::query()
            ->whereIn('status', [
                Rental::STATUS_CONFIRMED,
                Rental::STATUS_LATE_PICKUP,
                Rental::STATUS_ACTIVE,
                Rental::STATUS_LATE_RETURN,
                Rental::STATUS_PARTIAL_RETURN,
                Rental::STATUS_COMPLETED,
            ])
            ->with(['customer', 'outDelivery', 'inDelivery']);
    }

    public function getTabs(): array
    {
        $pickupStatuses = [Rental::STATUS_CONFIRMED, Rental::STATUS_LATE_PICKUP];
        $returnStatuses = [Rental::STATUS_ACTIVE, Rental::STATUS_LATE_RETURN, Rental::STATUS_PARTIAL_RETURN];
        $lateStatuses = [Rental::STATUS_LATE_PICKUP, Rental::STATUS_LATE_RETURN];

        return [
            'pickup' => Tab::make('Perlu Keluar')
                ->icon('heroicon-o-truck')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $pickupStatuses))
                ->badge(Rental::whereIn('status', $pickupStatuses)->count())
                ->badgeColor('warning'),

            'return' => Tab::make('Perlu Masuk')
                ->icon('heroicon-o-arrow-uturn-left')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $returnStatuses))
                ->badge(Rental::whereIn('status', $returnStatuses)->count())
                ->badgeColor('info'),

            'late' => Tab::make('Telat')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $lateStatuses))
                ->badge(Rental::whereIn('status', $lateStatuses)->count())
                ->badgeColor('danger'),

            'done' => Tab::make('Selesai')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Rental::STATUS_COMPLETED)),

            'all' => Tab::make('Semua')
                ->icon('heroicon-o-queue-list'),
        ];
    }
}
