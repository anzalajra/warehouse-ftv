<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Brand;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use UnitEnum;

class ProductSetup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Product Setup';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.product-setup';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'brands' => Brand::all()->toArray(),
            'categories' => Category::all()->toArray(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Product Setup')
                    ->tabs([
                        Tabs\Tab::make('Brands')
                            ->icon('heroicon-o-tag')
                            ->schema([
                                Repeater::make('brands')
                                    ->schema([
                                        Hidden::make('id'),
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (?string $state, callable $set) {
                                                $set('slug', Str::slug($state ?? ''));
                                            }),
                                        TextInput::make('slug')
                                            ->required()
                                            ->maxLength(255)
                                            ->distinct()
                                            ->rules([
                                                fn ($get) => Rule::unique(Brand::class, 'slug')->ignore($get('id')),
                                            ]),
                                        FileUpload::make('logo')
                                            ->image()
                                            ->disk('public')
                                            ->visibility('public')
                                            ->directory('brands'),
                                        TextInput::make('website')
                                            ->url()
                                            ->default(null),
                                        Toggle::make('is_active')
                                            ->default(true),
                                    ])
                                    ->columns(2)
                                    ->grid([
                                        'default' => 1,
                                        'md' => 2,
                                        'xl' => 3,
                                    ])
                                    ->collapsible()
                                    ->collapsed()
                                    ->addActionLabel('Add New Brand')
                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                            ]),

                        Tabs\Tab::make('Categories')
                            ->icon('heroicon-o-folder')
                            ->schema([
                                Repeater::make('categories')
                                    ->schema([
                                        Hidden::make('id'),
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (?string $state, callable $set) {
                                                $set('slug', Str::slug($state ?? ''));
                                            }),
                                        TextInput::make('slug')
                                            ->required()
                                            ->maxLength(255)
                                            ->distinct()
                                            ->rules([
                                                fn ($get) => Rule::unique(Category::class, 'slug')->ignore($get('id')),
                                            ]),
                                        Textarea::make('description')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                        FileUpload::make('image')
                                            ->image()
                                            ->disk('public')
                                            ->visibility('public')
                                            ->directory('categories'),
                                        Toggle::make('is_active')
                                            ->default(true),
                                    ])
                                    ->columns(2)
                                    ->grid([
                                        'default' => 1,
                                        'md' => 2,
                                        'xl' => 3,
                                    ])
                                    ->collapsible()
                                    ->collapsed()
                                    ->addActionLabel('Add New Category')
                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                            ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Handle Brands
        if (isset($data['brands'])) {
            $brandIds = [];
            foreach ($data['brands'] as $item) {
                $brand = Brand::updateOrCreate(
                    ['id' => $item['id'] ?? null],
                    [
                        'name' => $item['name'],
                        'slug' => $item['slug'],
                        'logo' => $item['logo'] ?? null,
                        'website' => $item['website'] ?? null,
                        'is_active' => $item['is_active'] ?? true,
                    ]
                );
                $brandIds[] = $brand->id;
            }
            Brand::whereNotIn('id', $brandIds)->delete();
        }

        // Handle Categories
        if (isset($data['categories'])) {
            $categoryIds = [];
            foreach ($data['categories'] as $item) {
                $category = Category::updateOrCreate(
                    ['id' => $item['id'] ?? null],
                    [
                        'name' => $item['name'],
                        'slug' => $item['slug'],
                        'description' => $item['description'] ?? null,
                        'image' => $item['image'] ?? null,
                        'is_active' => $item['is_active'] ?? true,
                    ]
                );
                $categoryIds[] = $category->id;
            }
            Category::whereNotIn('id', $categoryIds)->delete();
        }

        Notification::make()
            ->title('Product setup saved successfully')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Setup')
                ->icon('heroicon-o-check')
                ->submit('save'),
        ];
    }
}
