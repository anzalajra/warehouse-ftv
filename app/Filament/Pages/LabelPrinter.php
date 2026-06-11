<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Navigation entry for the Bluetooth label printer.
 *
 * The actual editor is a standalone full-screen page served at the route
 * `admin.print-label` (the LuckPrinter WYSIWYG editor ships its own global
 * CSS/layout, so it cannot live inside the Filament panel chrome). This page
 * exists only to place a menu item under Inventory and to redirect any hit on
 * its own URL (e.g. old `getUrl()` links) to that dedicated page.
 */
class LabelPrinter extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Print Label';

    protected static ?string $title = 'Print Label';

    protected static ?string $slug = 'label-printer';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.label-printer';

    /** Point the sidebar item straight at the dedicated full-screen editor. */
    public static function getNavigationUrl(): string
    {
        return route('admin.print-label');
    }

    /** Anything that lands on this page's own URL is forwarded to the editor. */
    public function mount(): void
    {
        $this->redirect(route('admin.print-label', request()->query()));
    }
}
