<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\CustomerCategory;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['customer_category_id'])) {
            $data['customer_category_id'] = CustomerCategory::where('is_active', true)
                ->orderBy('id')
                ->value('id');
        }

        return $data;
    }
}
