@php
    $path = '/' . trim(request()->path(), '/');
    $isHome = $path === '/admin' || $path === '/admin/';
    $isSchedule = str_starts_with($path, '/admin/schedule');
    $isBookings = str_starts_with($path, '/admin/rentals');
    $isCustomers = str_starts_with($path, '/admin/customers');
@endphp

<nav class="gr-bottombar" aria-label="Mobile navigation">
    <a href="{{ url('/admin') }}" class="gr-bb-item {{ $isHome ? 'active' : '' }}">
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
        </div>
    </div>
</nav>

<style>
.gr-bottombar { display: none; }

@media (max-width: 767px) {
    .gr-bottombar {
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

    .dark .gr-bottombar {
        background: rgb(17 24 39);
        border-top-color: rgb(255 255 255 / 0.1);
    }

    .gr-bb-item {
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

    .gr-bb-item svg {
        width: 22px;
        height: 22px;
    }

    .gr-bb-item.active {
        color: var(--primary-600, #0284c7);
    }

    .gr-bb-more-btn {
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

    .gr-bb-more-btn svg {
        width: 22px;
        height: 22px;
    }

    .gr-bb-more-btn.active {
        color: var(--primary-600, #0284c7);
    }

    .gr-bb-fab {
        flex: 0 0 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        margin-top: -20px;
        border-radius: 50%;
        background: var(--primary-600, #0284c7);
        color: #fff;
        box-shadow: 0 4px 14px rgba(2, 132, 199, 0.4);
        text-decoration: none;
    }

    .gr-bb-fab svg {
        width: 26px;
        height: 26px;
    }

    .gr-bb-more-menu {
        position: absolute;
        bottom: 72px;
        right: 8px;
        min-width: 180px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        padding: 6px;
        z-index: 50;
    }

    .dark .gr-bb-more-menu {
        background: rgb(17 24 39);
        border-color: rgb(255 255 255 / 0.1);
    }

    .gr-bb-more-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 8px;
        color: #374151;
        font-size: 13px;
        text-decoration: none;
    }

    .dark .gr-bb-more-link {
        color: #e5e7eb;
    }

    .gr-bb-more-link:hover {
        background: #f3f4f6;
    }

    .dark .gr-bb-more-link:hover {
        background: rgb(255 255 255 / 0.05);
    }

    .gr-bb-more-link svg {
        width: 18px;
        height: 18px;
        color: #6b7280;
    }

    /* Give page content room for the bar */
    .fi-main,
    .fi-page,
    .fi-body {
        padding-bottom: calc(72px + env(safe-area-inset-bottom, 0)) !important;
    }
}
</style>

<script>
(function() {
    'use strict';
    function updateMobileClass() {
        if (window.innerWidth < 768) {
            document.body.classList.add('is-mobile-view');
        } else {
            document.body.classList.remove('is-mobile-view');
        }
    }
    document.addEventListener('DOMContentLoaded', updateMobileClass);
    window.addEventListener('resize', updateMobileClass);
    if (document.readyState !== 'loading') {
        updateMobileClass();
    }
})();
</script>
