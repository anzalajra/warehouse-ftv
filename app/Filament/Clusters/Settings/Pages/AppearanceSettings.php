<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class AppearanceSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationLabel = 'Appearance';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.clusters.settings.pages.appearance-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                ToggleButtons::make('theme_preset')
                    ->label('Theme Preset')
                    ->options([
                        'default' => new HtmlString('<div class="w-6 h-6 rounded-full bg-gray-900 border border-gray-200" title="Default"></div>'),
                        'slate' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #64748b;" title="Slate"></div>'),
                        'gray' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #6b7280;" title="Gray"></div>'),
                        'zinc' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #71717a;" title="Zinc"></div>'),
                        'neutral' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #737373;" title="Neutral"></div>'),
                        'stone' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #78716c;" title="Stone"></div>'),
                        'red' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #ef4444;" title="Red"></div>'),
                        'orange' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #f97316;" title="Orange"></div>'),
                        'amber' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #f59e0b;" title="Amber"></div>'),
                        'yellow' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #eab308;" title="Yellow"></div>'),
                        'lime' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #84cc16;" title="Lime"></div>'),
                        'green' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #22c55e;" title="Green"></div>'),
                        'emerald' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #10b981;" title="Emerald"></div>'),
                        'teal' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #14b8a6;" title="Teal"></div>'),
                        'cyan' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #06b6d4;" title="Cyan"></div>'),
                        'sky' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #0ea5e9;" title="Sky"></div>'),
                        'blue' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #3b82f6;" title="Blue"></div>'),
                        'indigo' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #6366f1;" title="Indigo"></div>'),
                        'violet' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #8b5cf6;" title="Violet"></div>'),
                        'purple' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #a855f7;" title="Purple"></div>'),
                        'fuchsia' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #d946ef;" title="Fuchsia"></div>'),
                        'pink' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #ec4899;" title="Pink"></div>'),
                        'rose' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background-color: #f43f5e;" title="Rose"></div>'),
                        'custom' => new HtmlString('<div class="w-6 h-6 rounded-full" style="background: conic-gradient(red, yellow, lime, aqua, blue, magenta, red);" title="Custom"></div>'),
                    ])
                    ->inline()
                    ->default('default')
                    ->live()
                    ->required(),
                ColorPicker::make('theme_color')
                    ->label('Custom Color')
                    ->helperText('Select a custom primary color for the admin panel.')
                    ->visible(fn ($get) => $get('theme_preset') === 'custom')
                    ->required(fn ($get) => $get('theme_preset') === 'custom')
                    ->columnSpanFull(),
                ToggleButtons::make('navigation_layout')
                    ->label('Navigation Layout')
                    ->options([
                        'sidebar' => 'Sidebar',
                        'top' => 'Top Navigation',
                    ])
                    ->icons([
                        'sidebar' => 'heroicon-o-bars-3-bottom-left',
                        'top' => 'heroicon-o-bars-3',
                    ])
                    ->helperText('Pilihan ini untuk tampilan desktop. Pada perangkat mobile, navigasi akan otomatis berubah ke Top Navigation.')
                    ->default('sidebar')
                    ->inline()
                    ->required(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();

        // Redirect to force full page reload so panel re-boots with new settings
        $this->redirect(static::getUrl());
    }
}
