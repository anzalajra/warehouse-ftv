<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('Semua'),
        ];

        $categories = Category::query()
            ->where('slug', '!=', 'accessories-kits')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($categories as $category) {
            $tabs[(string) $category->id] = Tab::make($category->name)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category_id', $category->id));
        }

        return $tabs;
    }
}
