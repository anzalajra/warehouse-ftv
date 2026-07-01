<?php

namespace App\Filament\Clusters\Finance\Resources\CurrencyResource\Pages;

use App\Filament\Clusters\Finance\Resources\CurrencyResource;
use Filament\Resources\Pages\ListRecords;

class ListCurrencies extends ListRecords
{
    protected static string $resource = CurrencyResource::class;
}
