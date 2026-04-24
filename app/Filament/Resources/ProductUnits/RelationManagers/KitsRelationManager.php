<?php

namespace App\Filament\Resources\ProductUnits\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KitsRelationManager extends RelationManager
{
    protected static string $relationship = 'kits';

    protected static ?string $title = 'Unit Kits';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('track_by_serial')
                    ->label('Track by Serial Number')
                    ->default(true)
                    ->live()
                    ->columnSpanFull(),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Battery, Charger, Strap, Lens Cap'),

                TextInput::make('serial_number')
                    ->maxLength(255)
                    ->placeholder('Optional serial number')
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
                    ->default('excellent')
                    ->required(),

                Textarea::make('notes')
                    ->rows(3)
                    ->placeholder('Additional notes about this kit item')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('serial_number')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('condition')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'excellent' => 'success',
                        'good'      => 'info',
                        'fair'      => 'warning',
                        'poor', 'broken', 'lost' => 'danger',
                        default     => 'gray',
                    }),

                TextColumn::make('notes')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->notes)
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
