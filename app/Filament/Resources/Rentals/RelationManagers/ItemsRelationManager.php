<?php

namespace App\Filament\Resources\Rentals\RelationManagers;

use App\Models\RentalItem;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Rental Items & Kits';

    protected static ?string $recordTitleAttribute = 'product_unit_id';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('productUnit.product.name')
                    ->label('Product')
                    ->description(fn (RentalItem $record) => $record->productUnit->variation->name ?? null)
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('productUnit.serial_number')
                    ->label('Serial Number')
                    ->searchable()
                    ->toggleable()
                    ->visibleFrom('sm'),

                TextColumn::make('daily_rate')
                    ->label('Daily Rate')
                    ->money('IDR')
                    ->toggleable()
                    ->visibleFrom('md'),

                TextColumn::make('days')
                    ->label('Days')
                    ->toggleable()
                    ->visibleFrom('md'),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('IDR')
                    ->toggleable(),

                TextColumn::make('rentalItemKits_count')
                    ->label('Kits')
                    ->counts('rentalItemKits')
                    ->badge()
                    ->color('info')
                    ->toggleable()
                    ->visibleFrom('lg'),

                IconColumn::make('all_kits_returned')
                    ->label('Kits Returned')
                    ->boolean()
                    ->getStateUsing(fn (RentalItem $record): bool => $record->allKitsReturned())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('manage_kits')
                    ->label('Manage Kits')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->modalHeading(fn (RentalItem $record) => 'Manage Kits - ' . $record->productUnit->serial_number)
                    ->modalWidth('xl')
                    ->form(function (RentalItem $record) {
                        return [
                            Placeholder::make('unit_info')
                                ->label('Unit')
                                ->content($record->productUnit->product->name . ' - ' . $record->productUnit->serial_number),

                            Repeater::make('kits')
                                ->label('Kit Items')
                                ->schema([
                                    TextInput::make('kit_name')
                                        ->label('Kit Name')
                                        ->disabled()
                                        ->dehydrated(false),

                                    TextInput::make('serial_number')
                                        ->label('Serial Number')
                                        ->disabled()
                                        ->dehydrated(false),

                                    Select::make('condition_out')
                                        ->label('Condition Out')
                                        ->options([
                                            'excellent' => 'Excellent',
                                            'good' => 'Good',
                                            'fair' => 'Fair',
                                            'poor' => 'Poor',
                                        ])
                                        ->disabled()
                                        ->dehydrated(false),

                                    Select::make('condition_in')
                                        ->label('Condition In')
                                        ->options([
                                            'excellent' => 'Excellent',
                                            'good' => 'Good',
                                            'fair' => 'Fair',
                                            'poor' => 'Poor',
                                        ])
                                        ->placeholder('Not returned yet'),

                                    Checkbox::make('is_returned')
                                        ->label('Returned'),

                                    Textarea::make('notes')
                                        ->label('Notes')
                                        ->rows(2)
                                        ->placeholder('e.g., damaged, missing'),
                                ])
                                ->columns(3)
                                ->default(function () use ($record) {
                                    return $record->rentalItemKits->map(function ($rentalItemKit) {
                                        return [
                                            'id' => $rentalItemKit->id,
                                            'kit_name' => $rentalItemKit->unitKit->name,
                                            'serial_number' => $rentalItemKit->unitKit->serial_number ?? '-',
                                            'condition_out' => $rentalItemKit->condition_out,
                                            'condition_in' => $rentalItemKit->condition_in,
                                            'is_returned' => $rentalItemKit->is_returned,
                                            'notes' => $rentalItemKit->notes,
                                        ];
                                    })->toArray();
                                })
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false),
                        ];
                    })
                    ->action(function (RentalItem $record, array $data) {
                        foreach ($data['kits'] as $kitData) {
                            $record->rentalItemKits()
                                ->where('id', $kitData['id'])
                                ->update([
                                    'condition_in' => $kitData['condition_in'],
                                    'is_returned' => $kitData['is_returned'] ?? false,
                                    'notes' => $kitData['notes'],
                                ]);
                        }

                        // Update unit kit condition if returned
                        foreach ($data['kits'] as $kitData) {
                            if ($kitData['is_returned'] && $kitData['condition_in']) {
                                $rentalItemKit = $record->rentalItemKits()->find($kitData['id']);
                                $rentalItemKit?->unitKit->update(['condition' => $kitData['condition_in']]);
                            }
                        }

                        Notification::make()
                            ->title('Kits updated successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (RentalItem $record) => $record->rentalItemKits()->count() > 0),

                Action::make('attach_kits')
                    ->label('Attach Kits')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Attach Kits from Unit')
                    ->modalDescription(fn (RentalItem $record) => "This will attach all kits from {$record->productUnit->serial_number} to this rental item.")
                    ->action(function (RentalItem $record) {
                        $record->attachKitsFromUnit();

                        Notification::make()
                            ->title('Kits attached successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (RentalItem $record) => 
                        $record->rentalItemKits()->count() === 0 && 
                        $record->productUnit->kits()->count() > 0
                    ),

                ViewAction::make(),
            ])
            ->headerActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}