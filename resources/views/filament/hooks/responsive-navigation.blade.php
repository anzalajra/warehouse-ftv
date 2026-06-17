@php
    $path = '/' . trim(request()->path(), '/');
    $isHome = $path === '/admin' || $path === '/admin/' || $path === '/admin/home';
    $isSchedule = str_starts_with($path, '/admin/schedule');
    $isBookings = str_starts_with($path, '/admin/rentals');
    $isCustomers = str_starts_with($path, '/admin/customers');
@endphp

<nav class="gr-bottombar" aria-label="Mobile navigation">
    <a href="{{ url('/admin/home') }}" class="gr-bb-item {{ $isHome ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10"/></svg>
        <span>Home</span>
    </a>
    <a href="{{ url('/admin/schedule') }}" class="gr-bb-item {{ $isSchedule ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        <span>Schedule</span>
    </a>
    <a href="{{ url('/admin/rentals/create') }}" class="gr-bb-fab" aria-label="New Booking">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
    </a>
    <a href="{{ url('/admin/rentals') }}" class="gr-bb-item {{ $isBookings ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16l7-3 7 3z"/></svg>
        <span>Bookings</span>
    </a>
    <div class="gr-bb-item" x-data="{ open: false }" @click.outside="open = false">
        <button type="button" class="gr-bb-more-btn {{ $isCustomers ? 'active' : '' }}" @click="open = !open">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/></svg>
            <span>More</span>
        </button>
        <div class="gr-bb-more-menu" x-show="open" x-transition style="display:none;">
            <a href="{{ url('/admin/customers') }}" class="gr-bb-more-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Customers
            </a>
            <a href="{{ url('/admin/products') }}" class="gr-bb-more-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></svg>
                Inventory
            </a>
            <a href="{{ url('/admin/deliveries') }}" class="gr-bb-more-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                Deliveries
            </a>
            <a href="{{ url('/admin/invoices') }}" class="gr-bb-more-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
                Invoices
            </a>
            <a href="{{ url('/admin/quotations') }}" class="gr-bb-more-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                Quotations
            </a>
            <a href="{{ url('/admin/finance') }}" class="gr-bb-more-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Finance
            </a>
            <a href="{{ url('/admin/computers') }}" class="gr-bb-more-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                Computers
            </a>
            <a href="{{ url('/admin/promotions') }}" class="gr-bb-more-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                Promotions
            </a>
            <a href="{{ url('/admin/maintenances') }}" class="gr-bb-more-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                Maintenance
            </a>
            <a href="{{ url('/admin/settings') }}" class="gr-bb-more-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Settings
            </a>
        </div>
    </div>
</nav>

<style>
/*
 * Bottom navigation bar — shown only in COMPACT mode (body.gr-compact), which is set by
 * the script below for portrait tablets (any width) and narrow viewports / phones.
 * In expanded mode (landscape tablet / wide desktop) it stays hidden and the sidebar shows.
 */
.gr-bottombar { display: none; }

body.gr-compact .gr-bottombar {
    display: flex;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 64px;
    padding-bottom: env(safe-area-inset-bottom, 0);
    background: #ffffff;
    border-top: 1px solid #e5e7eb;
    box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.06);
    z-index: 40;
    align-items: stretch;
    justify-content: space-around;
}

.dark body.gr-compact .gr-bottombar {
    background: rgb(17 24 39);
    border-top-color: rgb(255 255 255 / 0.1);
}

body.gr-compact .gr-bb-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    text-decoration: none;
    color: #6b7280;
    font-size: 11px;
    font-weight: 500;
    min-width: 0;
    position: relative;
}

body.gr-compact .gr-bb-item svg {
    width: 22px;
    height: 22px;
}

body.gr-compact .gr-bb-item.active {
    color: var(--primary-600);
}

body.gr-compact .gr-bb-more-btn {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    background: transparent;
    border: 0;
    color: #6b7280;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    padding: 0;
    width: 100%;
    height: 100%;
}

body.gr-compact .gr-bb-more-btn svg {
    width: 22px;
    height: 22px;
}

body.gr-compact .gr-bb-more-btn.active {
    color: var(--primary-600);
}

body.gr-compact .gr-bb-fab {
    flex: 0 0 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    margin-top: -20px;
    border-radius: 50%;
    background: var(--primary-600);
    color: #fff;
    box-shadow: 0 4px 14px color-mix(in srgb, var(--primary-600) 40%, transparent);
    text-decoration: none;
}

body.gr-compact .gr-bb-fab svg {
    width: 26px;
    height: 26px;
}

body.gr-compact .gr-bb-more-menu {
    position: absolute;
    bottom: 72px;
    right: 8px;
    min-width: 200px;
    max-height: 60vh;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    padding: 6px;
    z-index: 50;
}

.dark body.gr-compact .gr-bb-more-menu {
    background: rgb(17 24 39);
    border-color: rgb(255 255 255 / 0.1);
}

body.gr-compact .gr-bb-more-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    color: #374151;
    font-size: 13px;
    text-decoration: none;
}

.dark body.gr-compact .gr-bb-more-link {
    color: #e5e7eb;
}

body.gr-compact .gr-bb-more-link:hover {
    background: #f3f4f6;
}

.dark body.gr-compact .gr-bb-more-link:hover {
    background: rgb(255 255 255 / 0.05);
}

body.gr-compact .gr-bb-more-link svg {
    width: 18px;
    height: 18px;
    color: #6b7280;
}

/* Give page content room for the bar */
body.gr-compact .fi-main,
body.gr-compact .fi-page,
body.gr-compact.fi-body {
    padding-bottom: calc(72px + env(safe-area-inset-bottom, 0)) !important;
}

/*
 * Tablet (iPad portrait) gets a slightly larger bar than phones. Compact chrome at
 * >= 768px wide is a portrait tablet — phones are < 768px, and landscape tablets
 * (>= 1024px) aren't compact at all — so this only bumps the iPad without touching
 * the phone bar above. Bigger tap targets, icons and labels for the larger screen.
 */
@media (min-width: 768px) {
    body.gr-compact .gr-bottombar { height: 76px; }
    body.gr-compact .gr-bb-item { font-size: 13px; gap: 4px; }
    body.gr-compact .gr-bb-item svg { width: 26px; height: 26px; }
    body.gr-compact .gr-bb-more-btn { font-size: 13px; gap: 4px; }
    body.gr-compact .gr-bb-more-btn svg { width: 26px; height: 26px; }
    body.gr-compact .gr-bb-fab { flex: 0 0 64px; width: 64px; height: 64px; margin-top: -22px; }
    body.gr-compact .gr-bb-fab svg { width: 30px; height: 30px; }
    body.gr-compact .gr-bb-more-menu { bottom: 84px; min-width: 230px; }
    body.gr-compact .gr-bb-more-link { font-size: 14px; padding: 11px 13px; }
    body.gr-compact .gr-bb-more-link svg { width: 20px; height: 20px; }
    /* Match content padding to the taller bar (pages that hide the bar reclaim this
       with higher-specificity doubled-class rules, so they're unaffected). */
    body.gr-compact .fi-main,
    body.gr-compact .fi-page,
    body.gr-compact.fi-body {
        padding-bottom: calc(84px + env(safe-area-inset-bottom, 0)) !important;
    }
}
</style>

<script>
(function() {
    'use strict';

    var portraitMq = window.matchMedia('(orientation: portrait)');
    var wideMq = window.matchMedia('(min-width: 1024px)');

    /*
     * Compact (mobile-style) when the viewport is PORTRAIT, or narrower than 1024px.
     * Expanded (desktop sidebar) only when LANDSCAPE *and* width >= 1024px.
     *
     * Why orientation must take priority over width: the 12.9"/13" iPad Pro in portrait is
     * exactly 1024px wide — a width-only rule would wrongly treat it as a desktop. Driving
     * this from JS (rather than CSS media queries alone) lets one class, `gr-compact`, gate
     * both the bottom bar (here) and the sidebar collapse (theme.css) consistently, and it
     * flips instantly on rotation with no page reload.
     */
    function applyMode() {
        var isCompact = portraitMq.matches || window.innerWidth < 1024;
        document.body.classList.toggle('gr-compact', isCompact);
        // Kept for backwards-compatibility with any width-based consumers.
        document.body.classList.toggle('is-mobile-view', window.innerWidth < 768);
    }

    // Rotation + resize. matchMedia 'change' fires the instant orientation flips.
    window.addEventListener('resize', applyMode);
    window.addEventListener('orientationchange', applyMode);
    if (portraitMq.addEventListener) {
        portraitMq.addEventListener('change', applyMode);
        wideMq.addEventListener('change', applyMode);
    } else if (portraitMq.addListener) {
        // Safari < 14 fallback
        portraitMq.addListener(applyMode);
        wideMq.addListener(applyMode);
    }
    document.addEventListener('DOMContentLoaded', applyMode);
    // Re-apply after Filament/Livewire SPA navigation, which can morph the <body>.
    document.addEventListener('livewire:navigated', applyMode);
    if (document.readyState !== 'loading') {
        applyMode();
    }
})();
</script>
