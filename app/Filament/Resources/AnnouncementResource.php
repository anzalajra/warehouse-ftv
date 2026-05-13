<?php

namespace App\Filament\Resources;

use App\Models\Announcement;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Announcement';

    protected static ?string $pluralModelLabel = 'Announcements';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('General')
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Internal title, shown to admins.'),

                    Select::make('type')
                        ->required()
                        ->live()
                        ->options([
                            'popup' => 'Popup (with image)',
                            'banner' => 'Banner (text + link on top of storefront)',
                        ])
                        ->default('banner'),

                    Toggle::make('is_active')
                        ->default(true),

                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower number shows first.'),
                ])->columns(2),

            Section::make('Popup Content')
                ->schema([
                    FileUpload::make('image_path')
                        ->image()
                        ->disk('public')
                        ->visibility('public')
                        ->directory('announcements')
                        ->imageEditor()
                        ->columnSpanFull(),

                    TextInput::make('link_url')
                        ->label('Link URL (optional)')
                        ->url()
                        ->placeholder('https://...'),

                    TextInput::make('link_label')
                        ->label('Link Label (optional)')
                        ->placeholder('Pelajari lebih lanjut'),
                ])
                ->columns(2)
                ->visible(fn ($get) => $get('type') === 'popup'),

            Section::make('Banner Content')
                ->schema([
                    Textarea::make('content')
                        ->label('Banner Text')
                        ->required(fn ($get) => $get('type') === 'banner')
                        ->rows(2)
                        ->columnSpanFull(),

                    TextInput::make('link_url')
                        ->label('Link URL (optional)')
                        ->url()
                        ->placeholder('https://...'),

                    TextInput::make('link_label')
                        ->label('Link Label (optional)')
                        ->placeholder('Lihat detail'),

                    ColorPicker::make('banner_bg_color')
                        ->label('Background Color')
                        ->default('#0ea5e9'),

                    ColorPicker::make('banner_text_color')
                        ->label('Text Color')
                        ->default('#ffffff'),
                ])
                ->columns(2)
                ->visible(fn ($get) => $get('type') === 'banner'),

            Section::make('Schedule (optional)')
                ->schema([
                    DateTimePicker::make('starts_at')
                        ->helperText('Leave empty to start immediately.'),
                    DateTimePicker::make('ends_at')
                        ->helperText('Leave empty for no end date.'),
                ])
                ->columns(2)
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk('public')
                    ->square()
                    ->toggleable(),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'info' => 'banner',
                        'warning' => 'popup',
                    ]),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('starts_at')
                    ->dateTime()
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('ends_at')
                    ->dateTime()
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('type')->options([
                    'popup' => 'Popup',
                    'banner' => 'Banner',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\AnnouncementResource\Pages\ListAnnouncements::route('/'),
            'create' => \App\Filament\Resources\AnnouncementResource\Pages\CreateAnnouncement::route('/create'),
            'edit' => \App\Filament\Resources\AnnouncementResource\Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
