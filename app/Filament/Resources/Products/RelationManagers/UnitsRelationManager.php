<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductUnit;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';

    protected static ?string $title = 'Product Units';

    protected static ?string $recordTitleAttribute = 'serial_number';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Unit Information')
                    ->columns(2)
                    ->schema([
                        Select::make('product_variation_id')
                            ->relationship('variation', 'name', fn ($query, $livewire) => $query->where('product_id', $livewire->getOwnerRecord()->id))
                            ->label('Variation')
                            ->visible(fn ($livewire) => $livewire->getOwnerRecord()->variations()->exists())
                            ->required(fn ($livewire) => $livewire->getOwnerRecord()->variations()->exists())
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('daily_rate')
                                    ->label('Override Price (Optional)')
                                    ->numeric()
                                    ->prefix('Rp'),
                            ])
                            ->createOptionUsing(function (array $data, $livewire) {
                                return $livewire->getOwnerRecord()->variations()->create($data)->id;
                            })
                            ->columnSpanFull(),

                        TextInput::make('serial_number')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('SN-A7IV-001'),

                        Select::make('condition')
                            ->options(ProductUnit::getConditionOptions())
                            ->required()
                            ->default('excellent'),

                        Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Warehouse')
                            ->placeholder('Select Warehouse')
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required(),
                                TextInput::make('location'),
                                \Filament\Forms\Components\Toggle::make('is_active')
                                    ->default(true),
                                \Filament\Forms\Components\Toggle::make('is_available_for_rental')
                                    ->default(true),
                            ]),

                        Select::make('status')
                            ->options(ProductUnit::getStatusOptions())
                            ->required()
                            ->default('available'),

                        DatePicker::make('purchase_date')
                            ->label('Purchase Date'),

                        TextInput::make('purchase_price')
                            ->label('Purchase Price')
                            ->numeric()
                            ->prefix('Rp'),

                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Kits / Accessories')
                    ->description('Add accessories or link other product units to create a Bundle.')
                    ->schema([
                        Repeater::make('kits')
                            ->relationship('kits')
                            ->schema([
                                Toggle::make('track_by_serial')
                                    ->label('Track by Serial Number')
                                    ->default(true)
                                    ->live()
                                    ->columnSpanFull(),

                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('serial_number')
                                    ->maxLength(255)
                                    ->required(fn (Get $get): bool => (bool) $get('track_by_serial'))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if (filled($state)) {
                                            $existingUnit = \App\Models\ProductUnit::where('serial_number', $state)->first();
                                            if ($existingUnit) {
                                                $set('name', $existingUnit->product->name);
                                                $set('condition', $existingUnit->condition);
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Existing Unit Found')
                                                    ->body("Found unit '{$existingUnit->product->name}' with serial '{$state}'. It will be linked automatically.")
                                                    ->success()
                                                    ->send();
                                            }
                                        }
                                    })
                                    ->hidden(fn (Get $get): bool => ! (bool) $get('track_by_serial')),

                                Select::make('condition')
                                    ->options(\App\Models\UnitKit::getConditionOptions())
                                    ->required()
                                    ->default('excellent'),

                                Textarea::make('notes')
                                    ->rows(1)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('serial_number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('variation.name')
                    ->label('Variation')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('kits_count')
                    ->counts('kits')
                    ->label('Kits')
                    ->badge()
                    ->color('info'),

                TextColumn::make('kits.name')
                    ->label('Kit List')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('condition')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'excellent' => 'success',
                        'good' => 'info',
                        'fair' => 'warning',
                        'poor' => 'danger',
                        'broken' => 'danger',
                        'lost' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'scheduled' => 'primary',
                        'rented' => 'warning',
                        'maintenance' => 'info',
                        'retired' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('purchase_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('purchase_price')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()->label('+ New Unit'),
            ])
            ->recordActions([
                EditAction::make(),
                \Filament\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->modalHeading('Duplicate Product Unit')
                    ->modalDescription('Please enter a new serial number for the duplicated unit and its kits.')
                    ->fillForm(function (ProductUnit $record) {
                        $data = [
                            'serial_number' => $record->serial_number . ' (Copy)',
                        ];
                        
                        if ($record->kits()->exists()) {
                            $data['duplicate_kits_data'] = [];
                            foreach ($record->kits as $kit) {
                                $data['duplicate_kits_data'][$kit->id] = [
                                    'serial_number' => $kit->serial_number,
                                    'original_id' => $kit->id,
                                ];
                            }
                        }
                        
                        return $data;
                    })
                    ->form(function (ProductUnit $record) {
                        $schema = [
                            TextInput::make('serial_number')
                                ->required()
                                ->maxLength(255)
                                ->unique('product_units', 'serial_number') // Check unique against table
                                ->label('New Unit Serial Number')
                                ->placeholder('Enter new serial number'),
                        ];

                        // Add fields for kits if they exist
                        if ($record->kits()->exists()) {
                            $kitFields = [];
                            foreach ($record->kits as $kit) {
                                // Use a simple unique key structure
                                $kitFields[] = Section::make("Kit: {$kit->name}")
                                    ->schema([
                                        TextInput::make("duplicate_kits_data.{$kit->id}.serial_number")
                                            ->label("New Serial Number for {$kit->name}")
                                            ->placeholder("Enter new serial number (Current: {$kit->serial_number})")
                                            ->required(),
                                        \Filament\Forms\Components\Hidden::make("duplicate_kits_data.{$kit->id}.original_id"),
                                    ])
                                    ->columns(1)
                                    ->compact();
                            }
                            
                            $schema[] = Section::make('Duplicate Kits')
                                ->description('Enter new serial numbers for the duplicated kits')
                                ->schema($kitFields);
                        }

                        return $schema;
                    })
                    ->action(function (ProductUnit $record, array $data): void {
                        // 1. Replicate ProductUnit
                        $newUnit = $record->replicate(['kits_count']); 
                        $newUnit->serial_number = $data['serial_number'];
                        $newUnit->status = 'available';
                        $newUnit->save();

                        // 2. Replicate Kits
                        // Check for our new data structure
                        if (isset($data['duplicate_kits_data']) && is_array($data['duplicate_kits_data'])) {
                            foreach ($data['duplicate_kits_data'] as $kitId => $kitData) {
                                $originalKitId = $kitData['original_id'] ?? $kitId; // Fallback to key if needed
                                
                                if ($originalKitId) {
                                    $originalKit = \App\Models\UnitKit::find($originalKitId);
                                    if ($originalKit) {
                                        $newKit = $originalKit->replicate(['unit_id']);
                                        $newKit->unit_id = $newUnit->id;
                                        $newKit->serial_number = $kitData['serial_number'];
                                        $newKit->save();
                                    }
                                }
                            }
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Unit Duplicated')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('assign_warehouse')
                        ->label('Assign Warehouse')
                        ->icon('heroicon-o-building-storefront')
                        ->form([
                            Select::make('warehouse_id')
                                ->label('Warehouse')
                                ->options(\App\Models\Warehouse::pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            foreach ($records as $record) {
                                $record->update(['warehouse_id' => $data['warehouse_id']]);
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Warehouses Updated')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
