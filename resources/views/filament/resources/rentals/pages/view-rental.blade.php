@use('Illuminate\Support\Facades\Storage')
@php
    $customer = $rental->user;

    // Customer admin view URL (CustomerResource view page → /{record})
    $customerUrl = null;
    if ($customer) {
        try {
            $customerUrl = \App\Filament\Resources\Customers\CustomerResource::getUrl('view', ['record' => $customer->getKey()]);
        } catch (\Throwable $e) {
            $customerUrl = null;
        }
    }

    // WhatsApp click-to-chat link for the phone number
    $waLink = null;
    if ($customer && !empty($customer->phone)) {
        $digits = preg_replace('/\D+/', '', (string) $customer->phone);
        if ($digits !== '') {
            if (str_starts_with($digits, '0')) {
                $digits = '62' . substr($digits, 1);
            } elseif (!str_starts_with($digits, '62')) {
                $digits = '62' . $digits;
            }
            $waLink = 'https://wa.me/' . $digits;
        }
    }

    $realStatus  = $rental->getRealTimeStatus();
    $statusLabel = ucfirst(str_replace('_', ' ', $realStatus));
    $toneMap = [
        'quotation'      => 'amber',
        'confirmed'      => 'blue',
        'active'         => 'green',
        'completed'      => 'purple',
        'cancelled'      => 'gray',
        'late_pickup'    => 'red',
        'late_return'    => 'red',
        'partial_return' => 'orange',
    ];
    $statusTone = $toneMap[$realStatus] ?? 'gray';

    $durationDays = (int) $rental->start_date->diffInDays($rental->end_date);
    $totalKits = $rental->items->sum(fn ($it) => $it->rentalItemKits->count());

    $rp = fn ($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');

    // --- Action visibility (mirrors the original getHeaderActions gates) ---
    $R = \App\Models\Rental::class;
    $rawStatus = $rental->status;

    $canConfirm = $rawStatus === \App\Models\Rental::STATUS_QUOTATION;
    $canPickup  = in_array($rawStatus, [\App\Models\Rental::STATUS_CONFIRMED, \App\Models\Rental::STATUS_LATE_PICKUP]);
    $canReturn  = in_array($rawStatus, [\App\Models\Rental::STATUS_ACTIVE, \App\Models\Rental::STATUS_LATE_RETURN, \App\Models\Rental::STATUS_PARTIAL_RETURN]);
    $canRevert  = $rawStatus === \App\Models\Rental::STATUS_CONFIRMED;
    $canCancel  = in_array($realStatus, [\App\Models\Rental::STATUS_QUOTATION, \App\Models\Rental::STATUS_LATE_PICKUP]);
    $canDelete  = in_array($rawStatus, [\App\Models\Rental::STATUS_CANCELLED, \App\Models\Rental::STATUS_COMPLETED]);

    $editUrl     = $this->getEditUrl();
    $deliveryUrl = $this->getDeliveryUrl();
    $waUrl            = ($this->isWhatsappEnabled() && !empty($customer?->phone)) ? $this->getWhatsappUrl() : null;
    $waEnabled        = $this->isWhatsappEnabled();
    $orderConfirmUrl  = !empty($customer?->phone) ? $this->getOrderConfirmedUrl() : null;
    $canDlQuotation   = $this->canDownloadQuotation();
    $canDlInvoice     = $this->canDownloadInvoice();

    $hasOverflow = $canRevert || $canCancel || $canDelete;
    $rentalsIndexUrl = \App\Filament\Resources\Rentals\RentalResource::getUrl('index');

    // --- Customer profile popup (clickable customer name) ---
    $custInitials = strtoupper(collect(explode(' ', $customer?->name ?? '?'))
        ->filter()
        ->map(fn ($w) => mb_substr($w, 0, 1))
        ->take(2)
        ->implode('')) ?: '?';
    $custHistory  = $this->customerHistory();
    $custStats    = $this->customerStats();
    $custCategory = $customer?->category?->name;
    $custOpenUrl  = $this->getCustomerUrl();
    $custBlocked  = $customer && method_exists($customer, 'isBlocked') && $customer->isBlocked();
    $custRedNotice = $customer && method_exists($customer, 'isRedNotice') && $customer->isRedNotice();
@endphp

<x-filament-panels::page>
    <div class="rent-app rent-view" x-data="{ sendSheet:false, actSheet:false, custProfile:false }">
        <style>
            .rent-app {
                --danger-50:  var(--primary-50,  #f0f9ff);
                --danger-100: var(--primary-100, #e0f2fe);
                --danger-500: var(--primary-500, #0ea5e9);
                --danger-600: var(--primary-600, #0284c7);
                --danger-700: var(--primary-700, #0369a1);
                --primary-400: var(--primary-400, #38bdf8);

                --gray-50:#f9fafb; --gray-100:#f3f4f6; --gray-200:#e5e7eb; --gray-300:#d1d5db;
                --gray-400:#9ca3af; --gray-500:#6b7280; --gray-600:#4b5563; --gray-700:#374151;
                --gray-800:#1f2937; --gray-900:#111827;

                --success-100:#dcfce7; --success-700:#15803d;
                --warning-100:#fef9c3; --warning-800:#854d0e;

                --bg-surface:#fff; --bg-page:#f9fafb;
                --fg-1:var(--gray-900); --fg-2:var(--gray-700); --fg-3:var(--gray-500); --fg-4:var(--gray-400);
                --border-1: var(--gray-200);
                --font-sans: inherit;
                --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                --text-base: 1rem;
                --radius-md:6px; --radius-lg:8px; --radius-full:9999px;
                --dur:150ms; --dur-fast:75ms; --ease: cubic-bezier(0.4, 0, 0.2, 1);
            }
            .dark .rent-app {
                --bg-surface:#18181b;
                --gray-50:#1f1f23; --gray-100:#27272a; --gray-200:#3f3f46; --gray-300:#52525b;
                --fg-1:#fafafa; --fg-2:#e4e4e7; --fg-3:#a1a1aa; --fg-4:#71717a;
                --border-1:#27272a;
            }

            .rent-app.rent-view * { box-sizing: border-box; }
            .rent-app.rent-view { color: var(--fg-1); line-height: 1.5; display:flex; flex-direction:column; gap:20px; }

            /* Cards */
            .rent-app .card { background: var(--bg-surface); border:1px solid var(--border-1); border-radius: var(--radius-lg); overflow:hidden; }
            .rent-app .card-head { padding:14px 20px; border-bottom:1px solid var(--border-1); display:flex; align-items:center; justify-content:space-between; gap:12px; }
            .rent-app .card-head h3 { margin:0; font-size: var(--text-base); font-weight:600; white-space:nowrap; }

            /* Pills */
            .rent-app .pill { display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:var(--radius-full); font-size:12px; font-weight:600; line-height:1.4; }
            .rent-app .pill::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
            .rent-app .pill-blue   { background:#e0f2fe; color:#075985; }
            .rent-app .pill-green  { background: var(--success-100); color: var(--success-700); }
            .rent-app .pill-amber  { background: var(--warning-100); color: var(--warning-800); }
            .rent-app .pill-red    { background:#fee2e2; color:#b91c1c; }
            .rent-app .pill-gray   { background: var(--gray-100); color: var(--fg-2); }
            .rent-app .pill-purple { background:#f3e8ff; color:#7e22ce; }
            .rent-app .pill-orange { background:#ffedd5; color:#c2410c; }

            .rent-app [x-cloak] { display:none !important; }

            /* === Sticky design topbar (replaces Filament header) === */
            .rent-app .topbar { position:sticky; top:0; z-index:30; background:rgba(255,255,255,0.92); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); border-bottom:1px solid var(--border-1); margin:-1.5rem -1.5rem 0; }
            .dark .rent-app .topbar { background:rgba(24,24,27,0.92); }
            .rent-app .topbar-inner { padding:12px 24px; display:flex; align-items:center; gap:16px; }
            .rent-app .crumbs { display:flex; align-items:center; gap:6px; min-width:0; font-size:12.5px; color:var(--fg-3); }
            .rent-app .crumbs a { color:var(--fg-3); text-decoration:none; }
            .rent-app .crumbs a:hover { color:var(--fg-1); }
            .rent-app .crumbs .sep { color:var(--gray-300); }
            .rent-app .topbar h1 { margin:0; font-size:18px; font-weight:700; color:var(--fg-1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-variant-numeric:tabular-nums; }
            .rent-app .topbar-actions { margin-left:auto; display:flex; align-items:center; gap:8px; }

            /* Buttons */
            .rent-app .btn { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border-radius:var(--radius-lg); font-size:0.875rem; font-weight:600; line-height:1; border:1px solid transparent; cursor:pointer; transition:background var(--dur) var(--ease), border-color var(--dur) var(--ease); white-space:nowrap; text-decoration:none; }
            .rent-app .btn-secondary { background:var(--bg-surface); color:var(--fg-1); border-color:var(--border-1); }
            .rent-app .btn-secondary:hover { background:var(--gray-50); }
            .rent-app .btn-info { background:#2563eb; color:#fff; }
            .rent-app .btn-info:hover { background:#1d4ed8; }
            .rent-app .btn-success { background:#16a34a; color:#fff; }
            .rent-app .btn-success:hover { background:#15803d; }
            .rent-app .btn.is-disabled { opacity:0.45; cursor:not-allowed; pointer-events:none; }
            .rent-app .btn .caret { margin-left:-2px; color:currentColor; opacity:0.7; display:inline-flex; }

            .rent-app .btn-iconsq { width:36px; height:36px; padding:0; justify-content:center; gap:0; }
            .rent-app .btn-iconsq.with-caret { width:auto; padding:0 7px 0 9px; gap:1px; }

            /* Tooltip for icon buttons */
            .rent-app .has-tip { position:relative; }
            .rent-app .has-tip::after { content:attr(data-tip); position:absolute; top:calc(100% + 7px); left:50%; transform:translateX(-50%) translateY(-3px); background:var(--gray-900); color:#fff; font-size:11.5px; font-weight:500; white-space:nowrap; padding:4px 8px; border-radius:6px; opacity:0; pointer-events:none; transition:opacity var(--dur) var(--ease), transform var(--dur) var(--ease); z-index:60; }
            .dark .rent-app .has-tip::after { background:#000; }
            .rent-app .has-tip:hover::after { opacity:1; transform:translateX(-50%) translateY(0); }

            .rent-app .tb-sep { width:1px; align-self:stretch; background:var(--border-1); margin:2px 4px; }

            /* Dropdown menus */
            .rent-app .menu-wrap { position:relative; display:inline-flex; }
            .rent-app .menu { position:absolute; top:calc(100% + 6px); right:0; min-width:240px; background:var(--bg-surface); border:1px solid var(--border-1); border-radius:var(--radius-lg); box-shadow:0 4px 20px -4px rgb(0 0 0 / 0.15); padding:6px; z-index:50; }
            .rent-app .menu.align-left { left:0; right:auto; }
            .rent-app .menu-item { display:flex; align-items:center; gap:10px; width:100%; text-align:left; white-space:nowrap; padding:9px 10px; border:0; background:transparent; border-radius:var(--radius-md); cursor:pointer; font-size:13.5px; font-weight:500; color:var(--fg-1); text-decoration:none; }
            .rent-app .menu-item:hover { background:var(--gray-50); }
            .rent-app .menu-item .mi-icon { color:var(--fg-3); display:flex; flex:0 0 18px; }
            .rent-app .menu-item.disabled { color:var(--fg-4); cursor:not-allowed; pointer-events:none; }
            .rent-app .menu-item.disabled .mi-icon { color:var(--fg-4); }
            .rent-app .menu-item.danger { color:#dc2626; }
            .rent-app .menu-item.danger:hover { background:#fef2f2; }
            .rent-app .menu-item.danger .mi-icon { color:#ef4444; }
            .dark .rent-app .menu-item.danger:hover { background:rgba(220,38,38,0.15); }
            .rent-app .menu-item .mi-tag { margin-left:auto; font-size:10px; font-weight:600; color:var(--fg-4); text-transform:uppercase; letter-spacing:0.04em; }
            .rent-app .menu-sep { height:1px; background:var(--gray-100); margin:5px 2px; }

            @media (max-width: 1023px), (orientation: portrait) {
                .rent-app .topbar { margin:-1rem -1rem 0; }
                .rent-app .topbar-inner { padding:10px 12px; gap:8px; }
                .rent-app .crumbs { display:none; }
                .rent-app .topbar h1 { font-size:15px; }
                .rent-app .topbar-actions .btn .text { display:none; }
            }

            /* Count chip */
            .rent-app .count-chip { display:inline-flex; align-items:center; gap:6px; background:var(--bg-surface); border:1px solid var(--border-1); padding:6px 10px; border-radius:var(--radius-full); font-size:12.5px; color:var(--fg-2); white-space:nowrap; }
            .rent-app .count-chip strong { color:var(--fg-1); font-weight:700; }

            /* === Rental information: read-only definition grid === */
            .rent-app .info-view { display:grid; grid-template-columns:repeat(4,1fr); gap:1px; background:var(--border-1); }
            .rent-app .info-cell { background:var(--bg-surface); padding:14px 18px; display:flex; flex-direction:column; gap:5px; min-width:0; }
            .rent-app .info-cell.span2 { grid-column: span 2; }
            .rent-app .info-cell .il { font-size:11px; font-weight:600; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.05em; }
            .rent-app .info-cell .iv { font-size:15px; font-weight:600; color:var(--fg-1); line-height:1.35; word-break:break-word; }
            .rent-app .info-cell .iv.muted { color:var(--fg-4); font-weight:500; }
            .rent-app .info-cell .iv.mono { font-family:var(--font-mono); font-size:14px; letter-spacing:-0.01em; }
            .rent-app .info-cell .iv.total { font-size:18px; font-weight:700; color:var(--fg-1); font-variant-numeric:tabular-nums; }
            .rent-app .info-cell .iv.danger { color:#dc2626; font-weight:500; font-size:13.5px; }
            .rent-app .info-cell .isub { font-size:12px; color:var(--fg-3); display:flex; align-items:center; gap:6px; }
            .rent-app .info-cell .isub .mono { font-family:var(--font-mono); }
            .rent-app a.iv-link { color:var(--danger-600); text-decoration:none; cursor:pointer; }
            .rent-app a.iv-link:hover { text-decoration:underline; }
            .rent-app a.wa-link { color:var(--success-700); text-decoration:none; cursor:pointer; }
            .rent-app a.wa-link:hover { text-decoration:underline; }
            .dark .rent-app a.wa-link { color:#4ade80; }

            /* === Read-only items table === */
            .rent-app .view-table { width:100%; }
            .rent-app .view-head, .rent-app .view-row {
                display:grid;
                grid-template-columns:34px minmax(0,2.7fr) minmax(0,1.15fr) 88px 64px 124px;
                align-items:center; gap:14px; padding:12px 20px;
            }
            .rent-app .view-head { background:var(--gray-50); border-bottom:1px solid var(--border-1); font-size:11px; font-weight:600; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.05em; }
            .rent-app .view-head .right { text-align:right; }
            .rent-app .view-head .center { text-align:center; }
            .rent-app .view-row { border-bottom:1px solid var(--gray-100); font-size:13.5px; transition:background var(--dur-fast) var(--ease); }
            .rent-app .view-row:last-child { border-bottom:0; }
            .rent-app .view-row:hover { background:var(--gray-50); }
            .rent-app .view-row .rownum { color:var(--fg-4); font-size:12px; font-variant-numeric:tabular-nums; text-align:right; }
            .rent-app .view-row .prod { display:flex; align-items:center; gap:11px; min-width:0; }
            .rent-app .view-row .prod-thumb {
                width:34px; height:34px; border-radius:7px; flex:0 0 34px; overflow:hidden;
                background:var(--gray-100); display:flex; align-items:center; justify-content:center; color:var(--fg-4);
            }
            .rent-app .view-row .prod-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
            .rent-app .view-row .prod-name { font-size:13.5px; font-weight:500; color:var(--fg-1); line-height:1.3; white-space:normal; overflow-wrap:anywhere; }
            .rent-app .view-row .prod-cat { font-size:11.5px; color:var(--fg-3); }
            .rent-app .view-row .serial { font-family:var(--font-mono); font-size:12.5px; color:var(--fg-2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .rent-app .view-row .serial.unassigned { color:var(--fg-4); font-style:italic; font-family:var(--font-sans); }
            .rent-app .view-row .kits { display:inline-flex; align-items:center; gap:5px; font-size:12.5px; color:var(--fg-2); font-variant-numeric:tabular-nums; }
            .rent-app .view-row .kits .kdot { width:6px; height:6px; border-radius:50%; background:var(--primary-400); }
            .rent-app .view-row .kits.zero { color:var(--fg-4); }
            .rent-app .view-row .kits.zero .kdot { background:var(--gray-300); }
            .rent-app .view-row .days { font-size:13px; color:var(--fg-2); text-align:center; font-variant-numeric:tabular-nums; }
            .rent-app .view-row .sub { font-size:13.5px; font-weight:600; text-align:right; font-variant-numeric:tabular-nums; color:var(--fg-1); }

            .rent-app .view-empty { padding:32px 20px; text-align:center; color:var(--fg-3); font-size:13.5px; }

            /* Totals footer */
            .rent-app .view-foot { padding:6px 20px 14px; display:flex; flex-direction:column; align-items:flex-end; }
            .rent-app .view-foot .frow { display:grid; grid-template-columns:auto 170px; gap:24px; align-items:center; padding:8px 0; min-width:340px; font-size:13.5px; }
            .rent-app .view-foot .frow .fl { text-align:right; color:var(--fg-2); }
            .rent-app .view-foot .frow .fv { text-align:right; font-weight:600; font-variant-numeric:tabular-nums; }
            .rent-app .view-foot .frow.disc .fv { color:#dc2626; font-weight:500; }
            .rent-app .view-foot .frow.grand { border-top:2px solid var(--gray-200); margin-top:4px; padding-top:13px; }
            .rent-app .view-foot .frow.grand .fl { font-weight:700; color:var(--fg-1); font-size:15px; }
            .rent-app .view-foot .frow.grand .fv { font-size:18px; font-weight:700; color:var(--fg-1); }

            @media (max-width: 1023px), (orientation: portrait) {
                .rent-app .info-view { grid-template-columns:1fr 1fr; }
                .rent-app .view-head { display:none; }
                .rent-app .view-row {
                    grid-template-columns:34px 1fr auto;
                    grid-template-areas:'num prod kits' '.   serial serial' '.   meta   sub';
                    gap:4px 11px; padding:12px 16px;
                }
                .rent-app .view-row .rownum { grid-area:num; }
                .rent-app .view-row .prod { grid-area:prod; }
                .rent-app .view-row .kits { grid-area:kits; justify-self:end; }
                .rent-app .view-row .serial { grid-area:serial; }
                .rent-app .view-row .days { grid-area:meta; text-align:left; }
                .rent-app .view-row .days::before { content:'Days: '; color:var(--fg-4); }
                .rent-app .view-row .sub { grid-area:sub; }
                .rent-app .view-foot .frow { min-width:0; grid-template-columns:auto 130px; gap:16px; }
            }

            /* ===================================================================
               MOBILE: sticky bottom action bar (Send + contextual primary) +
               Send / Actions bottom sheets. Colors follow the Filament theme
               (var(--primary-*)); the global app bottom nav is hidden here.
               =================================================================== */
            .rent-app .rv-kebab { display:none; }            /* desktop: hidden */
            .rent-app .rv-back { display:none; }             /* desktop: hidden (crumbs handle back) */
            .rent-app .rv-mobilebar,
            .rent-app .rv-sheet-root { display:none; }       /* desktop: hidden */

            /* Hide the global app bottom nav whenever the COMPACT chrome is active
               (body.gr-compact = phone + PORTRAIT tablet up to 1024px), not just at
               <768px. On portrait iPad this page renders its desktop layout but the
               global bottom bar still shows, so it must be hidden here too. The nav
               hook renders at body.end with `body.gr-compact … !important`, so we
               double the page classes (.fi-page.fi-page) to outrank it and reclaim
               the bottom padding it reserved. */
            body.gr-compact .gr-bottombar { display:none !important; }
            body.gr-compact.fi-body.fi-body { padding-bottom:0 !important; }
            body.gr-compact .fi-main.fi-main, body.gr-compact .fi-page.fi-page { padding-bottom:0 !important; }

            /* Keep the floating profile capsule pinned TOP-RIGHT on this immersive page —
               its own sticky bottom action bar owns the bottom-left zone (where the capsule
               sits on normal pages). Doubled class outranks the capsule's own body.end
               compact rule (equal base specificity, later in source order). */
            body.gr-compact .zw-capsule-root.zw-capsule-root {
                top:calc(0.6rem + env(safe-area-inset-top, 0px));
                right:0.75rem; bottom:auto; left:auto;
                flex-direction:column-reverse; align-items:flex-end;
            }

            @media (max-width: 1023px), (orientation: portrait) {
                /* Hide the global app bottom nav on this page — the rental action
                   bar replaces it (same approach as the Rental editor, Opsi B). */
                .gr-bottombar { display:none !important; }
                /* Reclaim the bottom padding the global nav reserved. The nav hook
                   renders at body.end (its <style> wins on equal specificity), so we
                   prefix with `body` to outrank it and zero the reserved space… */
                body.fi-body { padding-bottom:0 !important; }
                body .fi-main, body .fi-page { padding-bottom:0 !important; }
                /* …then give content room for our own fixed bar instead. */
                .rent-app.rent-view { padding-bottom:calc(84px + env(safe-area-inset-bottom, 0px)); }

                /* The topbar icon-button row moves to the bottom bar + Actions sheet. */
                .rent-app .topbar-actions { display:none; }
                .rent-app .rv-back { display:inline-flex; flex:none; }
                .rent-app .rv-kebab { display:inline-flex; flex:none; margin-left:auto; }

                /* ---- Sticky bottom action bar ---- */
                .rent-app .rv-mobilebar {
                    display:block; position:fixed; left:0; right:0; bottom:0; z-index:41;
                    background:var(--bg-surface); border-top:1px solid var(--border-1);
                    box-shadow:0 -2px 10px rgba(20,24,31,.05), 0 -10px 34px rgba(20,24,31,.06);
                    padding:12px 13px calc(16px + env(safe-area-inset-bottom, 0px));
                }
                .dark .rent-app .rv-mobilebar { box-shadow:0 -2px 10px rgba(0,0,0,.4), 0 -12px 34px rgba(0,0,0,.55); }
                .rent-app .rv-mobilebar .ab-row { display:flex; gap:10px; }
                .rent-app .rv-mobilebar .ab-btn {
                    display:flex; align-items:center; justify-content:center; gap:8px; height:51px;
                    border-radius:14px; font-family:inherit; font-weight:700; font-size:15.5px; cursor:pointer;
                    border:1.5px solid var(--border-1); background:var(--bg-surface); color:var(--fg-1);
                    text-decoration:none;
                }
                .rent-app .rv-mobilebar .ab-btn svg { width:19px; height:19px; flex:none; }
                .rent-app .rv-mobilebar .ab-btn.ghost { flex:1; }
                .rent-app .rv-mobilebar .ab-btn.ghost .cap { display:inline-flex; width:16px; height:16px; opacity:.8; margin-left:-3px; }
                .rent-app .rv-mobilebar .ab-btn.ghost:active { background:var(--gray-50); }
                .rent-app .rv-mobilebar .ab-btn.primary { flex:1.45; border:none; color:#fff; }
                .rent-app .rv-mobilebar .ab-btn.primary.brand { background:var(--primary-600); box-shadow:0 5px 14px color-mix(in srgb, var(--primary-600) 34%, transparent); }
                .rent-app .rv-mobilebar .ab-btn.primary.brand:active { background:var(--primary-700); }
                .rent-app .rv-mobilebar .ab-btn.primary.go { background:#16a34a; box-shadow:0 5px 14px rgba(22,163,74,.32); }
                .rent-app .rv-mobilebar .ab-btn.primary.go:active { background:#15803d; }

                /* ---- Bottom sheets (Send / Actions) ---- */
                .rent-app .rv-sheet-root { display:block; }
                .rent-app .rv-scrim { position:fixed; inset:0; background:rgba(8,11,16,.5); z-index:60; animation:rv-fade .18s ease; }
                @keyframes rv-fade { from{opacity:0} to{opacity:1} }
                @keyframes rv-slideup { from{transform:translateY(100%)} to{transform:translateY(0)} }
                .rent-app .rv-sheet {
                    position:fixed; left:0; right:0; bottom:0; z-index:61; background:var(--bg-surface);
                    border-radius:22px 22px 0 0; box-shadow:0 -12px 44px rgba(0,0,0,.3);
                    max-height:84%; display:flex; flex-direction:column;
                    padding-bottom:calc(10px + env(safe-area-inset-bottom, 0px));
                    animation:rv-slideup .26s cubic-bezier(.2,.7,.3,1);
                }
                .rent-app .rv-grip { width:40px; height:4px; border-radius:99px; background:var(--gray-200); margin:10px auto 2px; flex:none; }
                .rent-app .rv-sheet-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px 18px 10px; flex:none; }
                .rent-app .rv-sheet-head h3 { margin:0; font-size:17px; font-weight:800; letter-spacing:-.01em; color:var(--fg-1); }
                .rent-app .rv-sheet-head .sub { font-size:12.5px; color:var(--fg-3); font-weight:600; margin-top:2px; font-variant-numeric:tabular-nums; }
                .rent-app .rv-sheet-x { width:34px; height:34px; border-radius:10px; background:var(--gray-100); border:none; display:grid; place-items:center; color:var(--fg-2); cursor:pointer; flex:none; }
                .rent-app .rv-sheet-list { overflow:auto; padding:4px 12px 8px; }
                .rent-app .rv-sheet-group { font-size:10.5px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--fg-4); padding:12px 10px 6px; }
                .rent-app .rv-act { display:flex; align-items:center; gap:13px; width:100%; padding:13px 12px; border:none; background:transparent; border-radius:13px; cursor:pointer; font-family:inherit; text-align:left; text-decoration:none; }
                .rent-app .rv-act:active { background:var(--gray-50); }
                .rent-app .rv-act .ic { width:40px; height:40px; border-radius:11px; background:var(--gray-100); display:grid; place-items:center; color:var(--fg-1); flex:none; }
                .rent-app .rv-act .ic svg { width:19px; height:19px; }
                .rent-app .rv-act .lbl { flex:1; min-width:0; }
                .rent-app .rv-act .lbl .a { font-size:15px; font-weight:700; color:var(--fg-1); }
                .rent-app .rv-act .lbl .b { font-size:12.5px; color:var(--fg-3); font-weight:600; margin-top:1px; }
                .rent-app .rv-act .chev { color:var(--fg-4); flex:none; display:flex; }
                .rent-app .rv-act.danger .ic { background:#fee2e2; color:#dc2626; }
                .dark .rent-app .rv-act.danger .ic { background:rgba(220,38,38,.18); color:#f87171; }
                .rent-app .rv-act.danger .lbl .a { color:#dc2626; }
                .dark .rent-app .rv-act.danger .lbl .a { color:#f87171; }
                .rent-app .rv-act.disabled { opacity:.5; pointer-events:none; }
                .rent-app .rv-act .tag { margin-left:auto; font-size:10px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--fg-4); background:var(--gray-100); padding:3px 8px; border-radius:999px; }
            }

            /* ===================================================================
               Customer profile: clickable name trigger + detail popup
               =================================================================== */
            .rent-app .cust-trigger { display:inline-flex; align-items:center; gap:5px; background:none; border:none; padding:0; margin:0; font:inherit; cursor:pointer; color:var(--danger-600); font-weight:600; font-size:15px; line-height:1.35; text-align:left; }
            .rent-app .cust-trigger:hover .cust-trigger-name { text-decoration:underline; }
            .rent-app .cust-trigger-name { overflow-wrap:anywhere; }
            .rent-app .cust-trigger-chev { flex:none; opacity:.65; margin-top:1px; }

            .rent-app .cust-modal-root { position:fixed; inset:0; z-index:80; display:flex; align-items:center; justify-content:center; padding:20px; }
            .rent-app .cust-scrim { position:absolute; inset:0; background:rgba(8,11,16,.5); animation:rv-fade .18s ease; }
            @keyframes rv-fade { from{opacity:0} to{opacity:1} }
            @keyframes cust-pop { from{opacity:0; transform:translateY(8px) scale(.98)} to{opacity:1; transform:none} }
            .rent-app .cust-modal { position:relative; z-index:1; width:100%; max-width:480px; max-height:88vh; display:flex; flex-direction:column; background:var(--bg-surface); border:1px solid var(--border-1); border-radius:18px; box-shadow:0 24px 64px -12px rgba(0,0,0,.4); overflow:hidden; animation:cust-pop .2s cubic-bezier(.2,.7,.3,1); }

            .rent-app .cust-modal-head { display:flex; align-items:flex-start; gap:13px; padding:18px 18px 16px; border-bottom:1px solid var(--border-1); }
            .rent-app .cust-avatar { width:46px; height:46px; border-radius:13px; flex:none; background:var(--primary-600,#0284c7); color:#fff; display:grid; place-items:center; font-size:17px; font-weight:800; letter-spacing:-.02em; }
            .rent-app .cust-id { flex:1; min-width:0; }
            .rent-app .cust-id .cust-name { font-size:17px; font-weight:800; letter-spacing:-.01em; color:var(--fg-1); line-height:1.25; word-break:break-word; }
            .rent-app .cust-tags { display:flex; flex-wrap:wrap; gap:5px; margin-top:6px; }
            .rent-app .cust-chip { font-size:10.5px; font-weight:700; letter-spacing:.02em; padding:2.5px 8px; border-radius:999px; background:var(--gray-100); color:var(--fg-2); text-transform:uppercase; }
            .rent-app .cust-chip.ok { background:var(--success-100); color:var(--success-700); }
            .rent-app .cust-chip.bad { background:#fee2e2; color:#b91c1c; }
            .dark .rent-app .cust-chip.bad { background:rgba(220,38,38,.2); color:#f87171; }
            .rent-app .cust-chip.muted { background:transparent; color:var(--fg-4); font-family:var(--font-mono); letter-spacing:0; padding-left:2px; }
            .rent-app .cust-x { width:32px; height:32px; flex:none; border-radius:9px; background:var(--gray-100); border:none; display:grid; place-items:center; color:var(--fg-2); cursor:pointer; }
            .rent-app .cust-x:hover { background:var(--gray-200); }

            .rent-app .cust-modal-body { overflow:auto; padding:16px 18px; display:flex; flex-direction:column; gap:16px; }

            .rent-app .cust-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:1px; background:var(--border-1); border:1px solid var(--border-1); border-radius:12px; overflow:hidden; }
            .rent-app .cust-stats .cs-cell { background:var(--bg-surface); padding:11px 8px; text-align:center; min-width:0; }
            .rent-app .cust-stats .cs-v { font-size:18px; font-weight:800; color:var(--fg-1); font-variant-numeric:tabular-nums; line-height:1.1; }
            .rent-app .cust-stats .cs-v.sm { font-size:12px; font-weight:700; overflow:hidden; text-overflow:ellipsis; }
            .rent-app .cust-stats .cs-k { font-size:9.5px; font-weight:600; color:var(--fg-3); text-transform:uppercase; letter-spacing:.04em; margin-top:4px; }

            .rent-app .cust-contact { display:flex; flex-direction:column; }
            .rent-app .cust-contact .cc-row { display:flex; align-items:flex-start; gap:10px; padding:8px 2px; border-bottom:1px solid var(--gray-100); }
            .rent-app .cust-contact .cc-row:last-child { border-bottom:none; }
            .rent-app .cust-contact .cc-ic { color:var(--fg-4); flex:none; margin-top:1px; display:flex; }
            .rent-app .cust-contact .cc-k { font-size:12.5px; color:var(--fg-3); width:62px; flex:none; }
            .rent-app .cust-contact .cc-v { font-size:13.5px; color:var(--fg-1); font-weight:500; flex:1; min-width:0; word-break:break-word; }
            .rent-app .cust-contact .cc-v.muted { color:var(--fg-4); font-weight:400; }
            .rent-app .cust-contact .cc-v a { color:var(--success-700); text-decoration:none; }
            .dark .rent-app .cust-contact .cc-v a { color:#4ade80; }
            .rent-app .cust-contact .cc-v a:hover { text-decoration:underline; }

            .rent-app .cust-warn { display:flex; gap:9px; padding:11px 12px; border-radius:11px; background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; font-size:12.5px; line-height:1.45; }
            .dark .rent-app .cust-warn { background:rgba(220,38,38,.12); border-color:rgba(220,38,38,.35); color:#f87171; }
            .rent-app .cust-warn svg { flex:none; margin-top:1px; }

            .rent-app .cust-hist .ch-head { display:flex; align-items:center; gap:8px; font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:var(--fg-3); margin-bottom:9px; }
            .rent-app .cust-hist .ch-count { font-size:11px; font-weight:700; color:var(--fg-2); background:var(--gray-100); padding:1px 7px; border-radius:999px; }
            .rent-app .cust-hist .ch-list { display:flex; flex-direction:column; gap:6px; }
            .rent-app .cust-hist .ch-item { display:flex; align-items:center; gap:10px; padding:10px 11px; border:1px solid var(--border-1); border-radius:11px; text-decoration:none; transition:background var(--dur-fast) var(--ease); }
            .rent-app .cust-hist .ch-item:hover { background:var(--gray-50); }
            .rent-app .cust-hist .ch-item.current { border-color:var(--primary-400); background:var(--danger-50); }
            .rent-app .cust-hist .ch-main { flex:1; min-width:0; }
            .rent-app .cust-hist .ch-code { font-family:var(--font-mono); font-size:13px; font-weight:600; color:var(--fg-1); display:flex; align-items:center; gap:6px; }
            .rent-app .cust-hist .ch-now { font-family:var(--font-sans); font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--danger-700); background:var(--danger-100); padding:1.5px 6px; border-radius:999px; }
            .rent-app .cust-hist .ch-meta { font-size:11.5px; color:var(--fg-3); margin-top:3px; }
            .rent-app .cust-hist .ch-right { display:flex; flex-direction:column; align-items:flex-end; gap:5px; flex:none; }
            .rent-app .cust-hist .ch-amt { font-size:13px; font-weight:700; color:var(--fg-1); font-variant-numeric:tabular-nums; white-space:nowrap; }
            .rent-app .cust-hist .ch-right .pill { font-size:10px; padding:2px 7px; }
            .rent-app .cust-hist .ch-empty { padding:18px; text-align:center; color:var(--fg-4); font-size:13px; }

            .rent-app .cust-modal-foot { display:flex; gap:8px; padding:13px 18px; border-top:1px solid var(--border-1); }
            .rent-app .cust-modal-foot .btn { flex:1; justify-content:center; }

            @media (max-width: 1023px), (orientation: portrait) {
                .rent-app .cust-modal-root { padding:0; align-items:flex-end; }
                .rent-app .cust-modal { max-width:none; max-height:90vh; border-radius:20px 20px 0 0; border-bottom:none; animation:rv-slideup .26s cubic-bezier(.2,.7,.3,1); }
                .rent-app .cust-stats .cs-v { font-size:16px; }
            }
        </style>

        {{-- ===================== Sticky topbar ===================== --}}
        <div class="topbar">
            <div class="topbar-inner">
                {{-- Mobile-only back button (desktop uses the breadcrumbs below) --}}
                <a href="{{ $rentalsIndexUrl }}" class="btn btn-secondary btn-iconsq rv-back" aria-label="Kembali ke daftar rental">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                </a>
                <div style="min-width:0;flex:1;">
                    <div class="crumbs">
                        <a href="{{ $rentalsIndexUrl }}">Rentals</a>
                        <span class="sep">/</span>
                        <span style="color:var(--fg-1);">{{ $rental->rental_code }}</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;margin-top:3px;">
                        <h1>View Rental — {{ $rental->rental_code }}</h1>
                        <span class="pill pill-{{ $statusTone }}">{{ $statusLabel }}</span>
                        @if($realStatus !== 'partial_return' && $rental->hasPendingPartialReturn())
                            <span class="pill pill-orange" title="Some items returned, others still pending">Partial return</span>
                        @endif
                    </div>
                </div>

                <div class="topbar-actions">
                    {{-- Send dropdown --}}
                    <div class="menu-wrap" x-data="{ open:false }" @click.outside="open=false">
                        <button type="button" class="btn btn-secondary btn-iconsq with-caret has-tip" data-tip="Send" @click="open=!open">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4 20-7z"/><path d="M22 2 11 13"/></svg>
                            <span class="caret"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
                        </button>
                        <div class="menu align-left" x-show="open" x-cloak @click="open=false">
                            @if($waEnabled)
                                @if($waUrl)
                                    <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="menu-item">
                                        <span class="mi-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                                        <span>via WhatsApp</span>
                                    </a>
                                @else
                                    <span class="menu-item disabled" title="Customer phone number is missing">
                                        <span class="mi-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                                        <span>via WhatsApp</span>
                                    </span>
                                @endif
                            @endif
                            @if($orderConfirmUrl)
                                <a href="{{ $orderConfirmUrl }}" target="_blank" rel="noopener" class="menu-item">
                                    <span class="mi-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6l-8-3z"/></svg></span>
                                    <span>Order Confirmed</span>
                                </a>
                            @else
                                <span class="menu-item disabled" title="Customer phone number is missing">
                                    <span class="mi-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6l-8-3z"/></svg></span>
                                    <span>Order Confirmed</span>
                                </span>
                            @endif
                            <div class="menu-sep"></div>
                            <span class="menu-item disabled">
                                <span class="mi-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg></span>
                                <span>via Email</span>
                                <span class="mi-tag">Soon</span>
                            </span>
                        </div>
                    </div>

                    {{-- Print dropdown --}}
                    <div class="menu-wrap" x-data="{ open:false }" @click.outside="open=false">
                        <button type="button" class="btn btn-secondary btn-iconsq with-caret has-tip" data-tip="Print" @click="open=!open">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg>
                            <span class="caret"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
                        </button>
                        <div class="menu align-left" x-show="open" x-cloak @click="open=false">
                            <button type="button" class="menu-item" wire:click="mountAction('downloadChecklist')">
                                <span class="mi-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6M9 16h4"/></svg></span>
                                <span>Download Checklist Form</span>
                            </button>
                            @if($canDlQuotation)
                                <button type="button" class="menu-item" wire:click="mountAction('downloadQuotation')">
                                    <span class="mi-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg></span>
                                    <span>Download Quotation</span>
                                </button>
                            @endif
                            @if($canDlInvoice)
                                <button type="button" class="menu-item" wire:click="mountAction('downloadInvoice')">
                                    <span class="mi-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6M9 15h6"/></svg></span>
                                    <span>Download Invoice</span>
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Edit --}}
                    @if($editUrl)
                        <a href="{{ $editUrl }}" class="btn btn-secondary btn-iconsq has-tip" data-tip="Edit">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                    @else
                        <span class="btn btn-secondary btn-iconsq has-tip is-disabled" data-tip="Tidak bisa diedit">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </span>
                    @endif

                    {{-- Delivery --}}
                    <a href="{{ $deliveryUrl }}" class="btn btn-secondary btn-iconsq has-tip" data-tip="Delivery">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="13" height="14" rx="1"/><path d="M16 8h3l3 3v5a1 1 0 0 1-1 1h-1"/><circle cx="7.5" cy="18.5" r="1.8"/><circle cx="17.5" cy="18.5" r="1.8"/></svg>
                    </a>

                    {{-- More actions --}}
                    @if($hasOverflow)
                        <div class="menu-wrap" x-data="{ open:false }" @click.outside="open=false">
                            <button type="button" class="btn btn-secondary btn-iconsq with-caret has-tip" data-tip="More actions" @click="open=!open">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                                <span class="caret"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
                            </button>
                            <div class="menu align-left" x-show="open" x-cloak @click="open=false">
                                @if($canRevert)
                                    <button type="button" class="menu-item" wire:click="mountAction('revert')">
                                        <span class="mi-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 5 5v0a5 5 0 0 1-5 5H9"/></svg></span>
                                        <span>Revert to Quotation</span>
                                    </button>
                                @endif
                                @if($canCancel)
                                    @if($canRevert)<div class="menu-sep"></div>@endif
                                    <button type="button" class="menu-item danger" wire:click="mountAction('cancel')">
                                        <span class="mi-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/></svg></span>
                                        <span>Cancel Rental</span>
                                    </button>
                                @endif
                                @if($canDelete)
                                    <button type="button" class="menu-item danger" wire:click="mountAction('delete')">
                                        <span class="mi-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M6 6l1 14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-14"/></svg></span>
                                        <span>Delete Rental</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Contextual primary action (far right) --}}
                    @if($canConfirm)
                        <span class="tb-sep"></span>
                        <button type="button" class="btn btn-info" wire:click="mountAction('confirm')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
                            <span class="text">Confirm</span>
                        </button>
                    @elseif($canPickup)
                        <span class="tb-sep"></span>
                        <a href="{{ $this->getPickupUrl() }}" class="btn btn-success">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17h4V5a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h2"/><path d="M14 9h4l3 3v4a1 1 0 0 1-1 1h-1"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/></svg>
                            <span class="text">Process Pickup</span>
                        </a>
                    @elseif($canReturn)
                        <span class="tb-sep"></span>
                        <a href="{{ $this->getReturnUrl() }}" class="btn btn-success">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 5 5v0a5 5 0 0 1-5 5H9"/></svg>
                            <span class="text">Process Return</span>
                        </a>
                    @endif
                </div>

                {{-- Mobile-only kebab → opens the Actions bottom sheet --}}
                <button type="button" class="btn btn-secondary btn-iconsq rv-kebab" @click="actSheet=true" aria-label="Actions">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
                </button>
            </div>
        </div>

        {{-- ===================== Rental Information ===================== --}}
        <div class="card">
            <div class="card-head"><h3>Rental Information</h3></div>
            <div class="info-view">
                <div class="info-cell">
                    <span class="il">Rental Code</span>
                    <span class="iv mono">{{ $rental->rental_code }}</span>
                </div>

                <div class="info-cell">
                    <span class="il">Customer</span>
                    <span class="iv">
                        @if($customer)
                            <button type="button" class="cust-trigger" @click="custProfile=true" title="Lihat detail pelanggan">
                                <span class="cust-trigger-name">{{ $customer->name ?? '—' }}</span>
                                <svg class="cust-trigger-chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                        @else
                            —
                        @endif
                        @if($custRedNotice)
                            <span class="pill pill-red" style="margin-left:8px;font-size:10px;">Red Notice</span>
                        @endif
                    </span>
                    <span class="isub">
                        @if($waLink)
                            <a href="{{ $waLink }}" target="_blank" rel="noopener" class="wa-link mono">{{ $customer->phone }}</a>
                        @else
                            <span class="mono" style="color:var(--fg-4);">—</span>
                        @endif
                    </span>
                </div>

                <div class="info-cell">
                    <span class="il">Status</span>
                    <span class="iv"><span class="pill pill-{{ $statusTone }}">{{ $statusLabel }}</span></span>
                </div>

                <div class="info-cell">
                    <span class="il">Total</span>
                    <span class="iv total">{{ $rp($rental->total) }}</span>
                </div>

                <div class="info-cell">
                    <span class="il">Start Date</span>
                    <span class="iv">{{ $rental->start_date->format('d M Y H:i') }}</span>
                </div>

                <div class="info-cell">
                    <span class="il">End Date</span>
                    <span class="iv">{{ $rental->end_date->format('d M Y H:i') }}</span>
                    <span class="isub">Durasi <strong style="color:var(--fg-2);">{{ $durationDays }} hari</strong></span>
                </div>

                <div class="info-cell">
                    <span class="il">Returned Date</span>
                    <span class="iv {{ $rental->returned_date ? '' : 'muted' }}">{{ $rental->returned_date ? $rental->returned_date->format('d M Y H:i') : '—' }}</span>
                </div>

                <div class="info-cell">
                    <span class="il">Notes</span>
                    <span class="iv {{ $rental->notes ? '' : 'muted' }}">{{ $rental->notes ?: '—' }}</span>
                </div>

                @if($realStatus === 'cancelled' && $rental->cancel_reason)
                    <div class="info-cell span2">
                        <span class="il">Cancel Reason</span>
                        <span class="iv danger">{{ $rental->cancel_reason }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ===================== Rental Items ===================== --}}
        <div class="card">
            <div class="card-head">
                <h3>Rental Items</h3>
                <span class="count-chip"><strong>{{ $rental->items->count() }}</strong> produk · <strong>{{ $totalKits }}</strong> kits</span>
            </div>

            <div class="view-table">
                <div class="view-head">
                    <div class="right">#</div>
                    <div>Product</div>
                    <div>Serial Number</div>
                    <div>Kits</div>
                    <div class="center">Days</div>
                    <div class="right">Subtotal</div>
                </div>

                @forelse($rental->items as $i => $item)
                    @php
                        $product  = $item->productUnit?->product ?? $item->product;
                        $catName  = $product?->category?->name;
                        $image    = $product?->image;
                        $serial   = $item->productUnit?->serial_number;
                        $kitCount = $item->rentalItemKits->count();
                    @endphp
                    <div class="view-row">
                        <div class="rownum">{{ $i + 1 }}</div>
                        <div class="prod">
                            <div class="prod-thumb">
                                @if($image)
                                    <img src="{{ Storage::url($image) }}" alt="" loading="lazy">
                                @else
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.6-3.6a2 2 0 0 0-2.8 0L6 20"/></svg>
                                @endif
                            </div>
                            <div style="min-width:0;flex:1;">
                                <div class="prod-name">{{ $product?->name ?? '—' }}</div>
                                @if($catName)<div class="prod-cat">{{ $catName }}</div>@endif
                            </div>
                        </div>
                        <div class="serial {{ $serial ? '' : 'unassigned' }}">{{ $serial ?: '(belum di-assign)' }}</div>
                        <div class="kits {{ $kitCount === 0 ? 'zero' : '' }}">
                            <span class="kdot"></span>{{ $kitCount }} kits
                        </div>
                        <div class="days">{{ $item->days }}</div>
                        <div class="sub">{{ $rp($item->subtotal) }}</div>
                    </div>
                @empty
                    <div class="view-empty">Belum ada item pada rental ini.</div>
                @endforelse
            </div>

            <div class="view-foot">
                <div class="frow">
                    <span class="fl">Subtotal</span>
                    <span class="fv">{{ $rp($rental->subtotal) }}</span>
                </div>
                @foreach($rental->discountBreakdown() as $line)
                    <div class="frow disc">
                        <span class="fl">{{ $line['label'] }}</span>
                        <span class="fv">− {{ $rp($line['amount']) }}</span>
                    </div>
                @endforeach
                <div class="frow grand">
                    <span class="fl">Total</span>
                    <span class="fv">{{ $rp($rental->total) }}</span>
                </div>
            </div>
        </div>

        {{-- ===================== Activity Log ===================== --}}
        @include('filament.resources.rentals.partials.activity-log', ['rental' => $rental])

        {{-- ===================== Mobile sticky action bar ===================== --}}
        <div class="rv-mobilebar">
            <div class="ab-row">
                <button type="button" class="ab-btn ghost" @click="sendSheet=true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4 20-7z"/><path d="M22 2 11 13"/></svg>
                    <span>Send</span>
                    <span class="cap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
                </button>
                @if($canConfirm)
                    <button type="button" class="ab-btn primary brand" wire:click="mountAction('confirm')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
                        <span>Confirm</span>
                    </button>
                @elseif($canPickup)
                    <a href="{{ $this->getPickupUrl() }}" class="ab-btn primary go">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17h4V5a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h2"/><path d="M14 9h4l3 3v4a1 1 0 0 1-1 1h-1"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/></svg>
                        <span>Process Pickup</span>
                    </a>
                @elseif($canReturn)
                    <a href="{{ $this->getReturnUrl() }}" class="ab-btn primary go">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 5 5v0a5 5 0 0 1-5 5H9"/></svg>
                        <span>Process Return</span>
                    </a>
                @endif
            </div>
        </div>

        {{-- ===================== Send bottom sheet ===================== --}}
        <div class="rv-sheet-root" x-show="sendSheet" x-cloak>
            <div class="rv-scrim" @click="sendSheet=false"></div>
            <div class="rv-sheet">
                <div class="rv-grip"></div>
                <div class="rv-sheet-head">
                    <h3>Send</h3>
                    <button type="button" class="rv-sheet-x" @click="sendSheet=false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="rv-sheet-list">
                    @if($waEnabled)
                        @if($waUrl)
                            <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="rv-act" @click="sendSheet=false">
                                <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                                <span class="lbl"><div class="a">via WhatsApp</div>@if($customer?->phone)<div class="b">Kirim ke {{ $customer->phone }}</div>@endif</span>
                                <span class="chev"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
                            </a>
                        @else
                            <span class="rv-act disabled">
                                <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                                <span class="lbl"><div class="a">via WhatsApp</div><div class="b">Nomor telepon belum diisi</div></span>
                            </span>
                        @endif
                    @endif
                    @if($orderConfirmUrl)
                        <a href="{{ $orderConfirmUrl }}" target="_blank" rel="noopener" class="rv-act" @click="sendSheet=false">
                            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6l-8-3z"/></svg></span>
                            <span class="lbl"><div class="a">Order Confirmed</div></span>
                            <span class="chev"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
                        </a>
                    @else
                        <span class="rv-act disabled">
                            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6l-8-3z"/></svg></span>
                            <span class="lbl"><div class="a">Order Confirmed</div><div class="b">Nomor telepon belum diisi</div></span>
                        </span>
                    @endif
                    <span class="rv-act disabled">
                        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg></span>
                        <span class="lbl"><div class="a">via Email</div></span>
                        <span class="tag">Soon</span>
                    </span>
                </div>
            </div>
        </div>

        {{-- ===================== Actions bottom sheet ===================== --}}
        <div class="rv-sheet-root" x-show="actSheet" x-cloak>
            <div class="rv-scrim" @click="actSheet=false"></div>
            <div class="rv-sheet">
                <div class="rv-grip"></div>
                <div class="rv-sheet-head">
                    <div>
                        <h3>Actions</h3>
                        <div class="sub">{{ $rental->rental_code }}</div>
                    </div>
                    <button type="button" class="rv-sheet-x" @click="actSheet=false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="rv-sheet-list">
                    <div class="rv-sheet-group">Dokumen</div>
                    <button type="button" class="rv-act" @click="actSheet=false" wire:click="mountAction('downloadChecklist')">
                        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6M9 16h4"/></svg></span>
                        <span class="lbl"><div class="a">Download Checklist Form</div></span>
                        <span class="chev"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
                    </button>
                    @if($canDlQuotation)
                        <button type="button" class="rv-act" @click="actSheet=false" wire:click="mountAction('downloadQuotation')">
                            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg></span>
                            <span class="lbl"><div class="a">Download Quotation</div></span>
                            <span class="chev"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
                        </button>
                    @endif
                    @if($canDlInvoice)
                        <button type="button" class="rv-act" @click="actSheet=false" wire:click="mountAction('downloadInvoice')">
                            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6M9 15h6"/></svg></span>
                            <span class="lbl"><div class="a">Download Invoice</div></span>
                            <span class="chev"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
                        </button>
                    @endif

                    <div class="rv-sheet-group">Kelola Rental</div>
                    @if($editUrl)
                        <a href="{{ $editUrl }}" class="rv-act" @click="actSheet=false">
                            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></span>
                            <span class="lbl"><div class="a">Edit Rental</div></span>
                            <span class="chev"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
                        </a>
                    @endif
                    <a href="{{ $deliveryUrl }}" class="rv-act" @click="actSheet=false">
                        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="13" height="14" rx="1"/><path d="M16 8h3l3 3v5a1 1 0 0 1-1 1h-1"/><circle cx="7.5" cy="18.5" r="1.8"/><circle cx="17.5" cy="18.5" r="1.8"/></svg></span>
                        <span class="lbl"><div class="a">Delivery</div><div class="b">Atur pengiriman & kurir</div></span>
                        <span class="chev"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
                    </a>
                    @if($canRevert)
                        <button type="button" class="rv-act" @click="actSheet=false" wire:click="mountAction('revert')">
                            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 5 5v0a5 5 0 0 1-5 5H9"/></svg></span>
                            <span class="lbl"><div class="a">Revert to Quotation</div></span>
                            <span class="chev"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
                        </button>
                    @endif
                    @if($canCancel)
                        <button type="button" class="rv-act danger" @click="actSheet=false" wire:click="mountAction('cancel')">
                            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg></span>
                            <span class="lbl"><div class="a">Cancel Rental</div></span>
                        </button>
                    @endif
                    @if($canDelete)
                        <button type="button" class="rv-act danger" @click="actSheet=false" wire:click="mountAction('delete')">
                            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M6 6l1 14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-14"/></svg></span>
                            <span class="lbl"><div class="a">Delete Rental</div></span>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===================== Customer profile modal ===================== --}}
        @if($customer)
            <div class="cust-modal-root" x-show="custProfile" x-cloak @keydown.escape.window="custProfile=false" style="display:none;">
                <div class="cust-scrim" @click="custProfile=false"></div>
                <div class="cust-modal">
                    <div class="cust-modal-head">
                        <span class="cust-avatar">{{ $custInitials }}</span>
                        <div class="cust-id">
                            <div class="cust-name">{{ $customer->name ?? '—' }}</div>
                            <div class="cust-tags">
                                @if($custCategory)<span class="cust-chip">{{ $custCategory }}</span>@endif
                                @if($customer->is_verified)<span class="cust-chip ok">Terverifikasi</span>@endif
                                @if($custRedNotice)<span class="cust-chip bad">Red Notice</span>@endif
                                @if($custBlocked)<span class="cust-chip bad">Diblokir</span>@endif
                                <span class="cust-chip muted">#{{ $customer->id }}</span>
                            </div>
                        </div>
                        <button type="button" class="cust-x" @click="custProfile=false" aria-label="Tutup">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="cust-modal-body">
                        {{-- Aggregate stats --}}
                        <div class="cust-stats">
                            <div class="cs-cell"><div class="cs-v">{{ $custStats['total'] }}</div><div class="cs-k">Total</div></div>
                            <div class="cs-cell"><div class="cs-v">{{ $custStats['active'] }}</div><div class="cs-k">Aktif</div></div>
                            <div class="cs-cell"><div class="cs-v">{{ $custStats['completed'] }}</div><div class="cs-k">Selesai</div></div>
                            <div class="cs-cell"><div class="cs-v sm">{{ $rp($custStats['spent']) }}</div><div class="cs-k">Belanja</div></div>
                        </div>

                        {{-- Contact info --}}
                        <div class="cust-contact">
                            <div class="cc-row">
                                <span class="cc-ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
                                <span class="cc-k">Telepon</span>
                                <span class="cc-v {{ $customer->phone ? '' : 'muted' }}">
                                    @if($waLink)<a href="{{ $waLink }}" target="_blank" rel="noopener">{{ $customer->phone }}</a>@else{{ $customer->phone ?: '—' }}@endif
                                </span>
                            </div>
                            <div class="cc-row">
                                <span class="cc-ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg></span>
                                <span class="cc-k">Email</span>
                                <span class="cc-v {{ $customer->email ? '' : 'muted' }}">
                                    @if($customer->email)<a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>@else—@endif
                                </span>
                            </div>
                            <div class="cc-row">
                                <span class="cc-ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2M15 12h2M7 16h10"/></svg></span>
                                <span class="cc-k">NIK</span>
                                <span class="cc-v {{ $customer->nik ? 'mono' : 'muted' }}">{{ $customer->nik ?: '—' }}</span>
                            </div>
                            <div class="cc-row">
                                <span class="cc-ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                                <span class="cc-k">Alamat</span>
                                <span class="cc-v {{ $customer->address ? '' : 'muted' }}">{{ $customer->address ?: '—' }}</span>
                            </div>
                        </div>

                        {{-- Blocked / red-notice reason --}}
                        @if(($custBlocked || $custRedNotice) && !empty($customer->blocked_reason))
                            <div class="cust-warn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3z"/><path d="M12 9v4M12 17h.01"/></svg>
                                <span><strong>{{ $custRedNotice ? 'Red Notice' : 'Diblokir' }}:</strong> {{ $customer->blocked_reason }}</span>
                            </div>
                        @endif

                        {{-- Rental history --}}
                        <div class="cust-hist">
                            <div class="ch-head">Riwayat Rental <span class="ch-count">{{ $custStats['total'] }}</span></div>
                            <div class="ch-list">
                                @forelse($custHistory as $h)
                                    @php
                                        $hStatus = $h->status;
                                        $hTone   = $toneMap[$hStatus] ?? 'gray';
                                        $hUrl    = \App\Filament\Resources\Rentals\RentalResource::getUrl('view', ['record' => $h]);
                                        $isCurrent = $h->id === $rental->id;
                                    @endphp
                                    <a class="ch-item {{ $isCurrent ? 'current' : '' }}" href="{{ $hUrl }}">
                                        <div class="ch-main">
                                            <div class="ch-code">{{ $h->rental_code }}@if($isCurrent)<span class="ch-now">Ini</span>@endif</div>
                                            <div class="ch-meta">{{ $h->start_date?->format('d M Y') }} · {{ $h->items_count }} item</div>
                                        </div>
                                        <div class="ch-right">
                                            <span class="ch-amt">{{ $rp($h->total) }}</span>
                                            <span class="pill pill-{{ $hTone }}">{{ ucfirst(str_replace('_', ' ', $hStatus)) }}</span>
                                        </div>
                                    </a>
                                @empty
                                    <div class="ch-empty">Belum ada riwayat rental.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="cust-modal-foot">
                        @if($custOpenUrl)
                            <a class="btn btn-secondary" href="{{ $custOpenUrl }}">Buka Halaman Pelanggan</a>
                        @endif
                        <button type="button" class="btn btn-info" @click="custProfile=false">Tutup</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
