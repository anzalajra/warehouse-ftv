<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Resources\Rentals\RentalResource;
use App\Models\Rental;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class CreateRental extends Page
{
    protected static string $resource = RentalResource::class;

    public ?Rental $record = null;

    public function getView(): string
    {
        return 'filament.rentals.editor';
    }

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
