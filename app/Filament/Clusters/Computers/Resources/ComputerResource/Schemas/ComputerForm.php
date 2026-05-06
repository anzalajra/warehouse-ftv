<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerResource\Schemas;

use App\Models\Computer;
use App\Models\ComputerRoom;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ComputerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('room_id')
                    ->label('Room')
                    ->options(ComputerRoom::active()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')->required()->maxLength(255),
                    ])
                    ->createOptionUsing(fn (array $data) => ComputerRoom::create($data)->id),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('brand')
                    ->maxLength(255),
                Select::make('status')
                    ->options([
                        Computer::STATUS_AVAILABLE => 'Available',
                        Computer::STATUS_MAINTENANCE => 'Maintenance',
                        Computer::STATUS_RETIRED => 'Retired',
                    ])
                    ->default(Computer::STATUS_AVAILABLE)
                    ->required(),
                FileUpload::make('image_path')
                    ->image()
                    ->disk('public')
                    ->visibility('public')
                    ->directory('computers')
                    ->columnSpanFull(),
                KeyValue::make('specs')
                    ->keyLabel('Spec')
                    ->valueLabel('Value')
                    ->reorderable()
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
