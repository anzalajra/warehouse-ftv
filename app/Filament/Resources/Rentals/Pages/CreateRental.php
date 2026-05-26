<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Resources\Rentals\RentalResource;
use App\Models\Rental;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class CreateRental extends Page
{
    protected static string $resource = RentalResource::class;

    protected string $view = 'filament.rentals.editor';

    public ?Rental $record = null;

    public function getTitle(): string|Htmlable
    {
        return 'Buat Rental Baru';
    }

    public function getBreadcrumbs(): array
    {
        return [
            RentalResource::getUrl('index') => 'Rentals',
            'Baru',
        ];
    }
}
