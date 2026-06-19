<?php

namespace App\Providers\Filament;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Home;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();
    }

    public function panel(Panel $panel): Panel
    {
        $primaryColor = Color::Amber;
        $navigationLayout = 'sidebar';
        $brandName = config('app.name');
        $brandLogo = null;
        $favicon = null;

        try {
            if (Schema::hasTable('settings')) {
                $siteName = Setting::get('site_name');
                if ($siteName) {
                    $brandName = $siteName;
                }
                $logo = Setting::get('logo');
                if ($logo) {
                    $brandLogo = asset('storage/' . $logo);
                    $favicon = asset('storage/' . $logo);
                }

                $navigationLayout = Setting::get('navigation_layout', 'sidebar');
                
                // Use the centralized ThemeService to ensure consistency between Admin and Frontend
                $primaryColor = \App\Services\ThemeService::getPrimaryColor();
            }
        } catch (\Exception $e) {
            // Fallback to default
        }

        // Configure the palette plugin to use the calculated primary color
        // This ensures compatibility with the plugin's global theme application
        // and supports all colors, not just those defined in the plugin's config file.
        Config::set('filament-palette.palette.dynamic_theme', [
            'primary' => $primaryColor,
            'warning' => Color::Amber,
            'danger'  => Color::Red,
            'success' => Color::Green,
            'info'    => Color::Blue,
        ]);
        Config::set('filament-palette.default', 'dynamic_theme');

        $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->profile()
            ->maxContentWidth(Width::Full)
            ->brandName($brandName)
            ->brandLogo($brandLogo)
            ->favicon($favicon)
            ->colors([
                'primary' => $primaryColor,
                'danger' => Color::Red,
                'gray' => Color::Gray,
                'info' => Color::Blue,
                'success' => Color::Green,
                'warning' => Color::Orange,
                'purple' => Color::Purple,
            ]);
            
        // Navigation DOM selection. The default 'sidebar' renders the sidebar layout,
        // which the responsive system (responsive-navigation.blade.php + theme.css
        // .gr-compact) collapses into a bottom bar + floating profile capsule on
        // portrait/narrow viewports — INCLUDING phones. So phones now get the same
        // compact experience as portrait tablets (the capsule replaces the Filament
        // top bar), flipping instantly on rotation with no reload. Only an explicit
        // 'top' navigation_layout setting forces top navigation (a desktop choice).
        $useTopNav = ($navigationLayout === 'top');
        
        $panel
            ->renderHook(
                'panels::head.end',
                fn () => view('filament.hooks.admin-pwa')
            )
            // Global QR scanner. In explicit top-nav mode it lives in the topbar with
            // its own trigger button. In sidebar mode (the default, incl. phones/tablets)
            // the trigger is provided by the floating profile capsule (which dispatches
            // `zw:scanner-open`), so the scanner renders free-floating at body.end (NOT
            // inside the now-hidden sidebar footer) with its own trigger hidden.
            ->renderHook(
                $useTopNav
                    ? 'panels::global-search.after'
                    : 'panels::body.end',
                fn () => view('filament.hooks.qr-scanner', ['hideTrigger' => ! $useTopNav])
            )
            ->renderHook(
                'panels::content.end',
                fn () => view('filament.hooks.footer')
            )
            ->renderHook(
                'panels::body.end',
                fn () => view('filament.hooks.responsive-navigation')
            )
            ->renderHook(
                'panels::body.end',
                fn () => view('filament.hooks.loading-indicator')
            )
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Home::class,
            ])
            ->homeUrl(fn () => url('/admin/home'))
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
                \App\Filament\Widgets\StatsOverview::class,
                \App\Filament\Widgets\LatestRentals::class,
                \App\Filament\Widgets\RentalChart::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

        // Top navigation only when explicitly chosen via the navigation_layout setting.
        // Otherwise the sidebar layout is used for every device; the responsive system
        // collapses it to a bottom bar + floating capsule on phones/portrait tablets.
        if ($useTopNav) {
            $panel->topNavigation();
        } else {
            // Sidebar mode: hide the topbar entirely
            // Search, notifications, and user menu automatically move to sidebar
            $panel->topbar(false);

            // Replace Filament's built-in sidebar-footer "island" (user menu +
            // notifications) with the custom floating profile capsule. Used on all
            // devices in sidebar mode: full pill (desktop/landscape) or a compact
            // bottom-left pill sitting above the bottom nav bar on phones/portrait
            // tablets (see the blade's gr-compact styles; immersive pages with their
            // own bottom action bar re-pin it top-right). The capsule hides the old
            // .fi-sidebar-footer and provides the QR-scanner trigger.
            $panel->renderHook(
                'panels::body.end',
                fn () => view('filament.hooks.floating-profile-capsule')
            );
        }

        return $panel
            ->bootUsing(function () {
                \LaraZeus\Sky\SkyPlugin::get()
                    ->itemType(
                        'Post',
                        [
                            \Filament\Forms\Components\Select::make('post_id')
                                ->label(__('zeus-sky::cms.post.select_post'))
                                ->searchable()
                                ->options(function () {
                                    return \LaraZeus\Sky\SkyPlugin::get()->getModel('Post')::published()->pluck('title', 'id');
                                }),
                        ],
                        'post_link'
                    )
                    ->itemType(
                        'Page',
                        [
                            \Filament\Forms\Components\Select::make('page_id')
                                ->label(__('zeus-sky::cms.page.select_page'))
                                ->searchable()
                                ->options(function () {
                                    return \LaraZeus\Sky\SkyPlugin::get()->getModel('Post')::query()
                                        ->page()
                                        ->whereDate('published_at', '<=', now())
                                        ->pluck('title', 'id');
                                }),
                        ],
                        'page_link'
                    );
            })
            ->plugins([
                FilamentFullCalendarPlugin::make(),
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
                \Octopy\Filament\Palette\PaletteSwitcherPlugin::make()
                    ->applyThemeGlobally(true)
                    ->hidden(fn () => true),
                \LaraZeus\Sky\SkyPlugin::make()
                    ->navigationGroupLabel('Page & Post')
                    ->hideResources([
                        \LaraZeus\Sky\Filament\Resources\PageResource::class,
                        \LaraZeus\Sky\Filament\Resources\FaqResource::class,
                        \LaraZeus\Sky\Filament\Resources\LibraryResource::class,
                        \LaraZeus\Sky\Filament\Resources\PostResource::class,
                        \LaraZeus\Sky\Filament\Resources\NavigationResource::class,
                        \LaraZeus\Sky\Filament\Resources\TagResource::class,
                    ]),
                \LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin::make()
                    ->defaultLocales(['en', 'id']),
            ])
            ->databaseNotifications()
            // Navigation Groups Order - mengatur urutan group di sidebar
            ->navigationGroups([
                'Rentals',
                'Sales',
                'Inventory',
                'Page & Post',
                'Setting',
                'System',
            ])
            // Sidebar collapsible (opsional - bisa dihapus jika tidak perlu)
            ->sidebarCollapsibleOnDesktop();
    }
}