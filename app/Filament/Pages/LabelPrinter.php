<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use UnitEnum;

/**
 * Bluetooth label print studio. Hosts the standalone LuckPrinter WYSIWYG editor
 * (public/vendor/luckprinter/editor.html + editor-app.js) inside a same-origin
 * iframe so the editor runs exactly 1:1 as designed — free-form canvas with
 * text/QR/barcode/image/line/rect, drag-resize-rotate, templates, undo/redo and
 * Web Bluetooth printing (Web Bluetooth is permitted in same-origin frames).
 *
 * Two bridges into the warehouse system:
 *   - `?dataUrl=` points the editor at `admin.label-printer.units` (JSON feed) so
 *     it can import unit serials + closed-system payloads (PREFIX:serial) without
 *     manual typing — used by the QR/Barcode "Ambil dari sistem" buttons.
 *   - `?unit={id}` / `?units=1,2,3` deep-link prefills a unit label on open
 *     (from the product-unit Label modal and the "Print Labels" bulk action).
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

    /** Fully-built `editor.html` URL (with dataUrl + optional unit/units forwarded). */
    public string $editorUrl = '';

    public function mount(): void
    {
        // Relative path (no host) so the iframe fetch stays same-origin and carries
        // the session cookie regardless of forced HTTPS / proxy host.
        $params = ['dataUrl' => route('admin.label-printer.units', [], false)];

        foreach (['unit', 'units'] as $key) {
            if (filled($value = request()->query($key))) {
                $params[$key] = is_array($value) ? Arr::first($value) : $value;
            }
        }

        $this->editorUrl = '/vendor/luckprinter/editor.html?'.http_build_query($params);
    }
}
