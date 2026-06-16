@php
    $days = $this->days;
    $totals = $this->totals;
    $custInfo = $this->customerInfo;
    $waLink = null;
    if ($custInfo && !empty($custInfo['phone'])) {
        $digits = preg_replace('/\D+/', '', (string) $custInfo['phone']);
        if ($digits !== '') {
            if (str_starts_with($digits, '0')) {
                $digits = '62'.substr($digits, 1);
            } elseif (!str_starts_with($digits, '62')) {
                $digits = '62'.$digits;
            }
            $waLink = 'https://wa.me/'.$digits;
        }
    }
    $currentStatus = $this->currentStatus;
    // Back target: an existing rental returns to its view page; a brand-new draft
    // (not yet saved) has nothing to view, so fall back to the rentals list.
    $backUrl = ($record && $record->exists)
        ? \App\Filament\Resources\Rentals\RentalResource::getUrl('view', ['record' => $record->getKey()])
        : \App\Filament\Resources\Rentals\RentalResource::getUrl('index');
    $missingUnitsCount = 0;
    foreach ($items as $__it) {
        $missingUnitsCount += max(0, (int) $__it['quantity'] - count($__it['unit_ids']));
    }
    $totalUnits = collect($items)->sum('quantity');
    // Pre-fetch unit serials map (avoid N+1 in the loop)
    $allUnitIds = collect($items)->flatMap(fn($it) => $it['unit_ids'])->unique()->all();
    $serialMap = $allUnitIds
        ? \App\Models\ProductUnit::whereIn('id', $allUnitIds)->pluck('serial_number', 'id')
        : collect();
    // Pre-fetch product/variation names
    $prodIds = collect($items)->pluck('product_id')->unique()->all();
    $varIds = collect($items)->pluck('variation_id')->filter()->unique()->all();
    $prodMap = $prodIds ? \App\Models\Product::with('category')->whereIn('id', $prodIds)->get()->keyBy('id') : collect();
    $varMap = $varIds ? \App\Models\ProductVariation::whereIn('id', $varIds)->get()->keyBy('id') : collect();
@endphp

<div class="rent-app" wire:ignore.self>
    {{-- ====================================================
         Theme tokens — bind design "danger" (primary) to admin theme's --primary-* set,
         which Filament 4 exposes globally on the admin panel.
         ==================================================== --}}
    <style>
        .rent-app {
            --danger-50:  var(--primary-50,  #f0f9ff);
            --danger-100: var(--primary-100, #e0f2fe);
            --danger-200: var(--primary-200, #bae6fd);
            --danger-300: var(--primary-300, #7dd3fc);
            --danger-400: var(--primary-400, #38bdf8);
            --danger-500: var(--primary-500, #0ea5e9);
            --danger-600: var(--primary-600, #0284c7);
            --danger-700: var(--primary-700, #0369a1);

            /* Neutrals */
            --gray-50:#f9fafb; --gray-100:#f3f4f6; --gray-200:#e5e7eb; --gray-300:#d1d5db;
            --gray-400:#9ca3af; --gray-500:#6b7280; --gray-600:#4b5563; --gray-700:#374151;
            --gray-800:#1f2937; --gray-900:#111827;

            --success-50:#f0fdf4; --success-100:#dcfce7; --success-600:#16a34a; --success-700:#15803d;
            --warning-50:#fefce8; --warning-100:#fef9c3; --warning-300:#fcd34d; --warning-600:#ca8a04; --warning-700:#a16207; --warning-800:#854d0e;

            --bg-surface:#fff;
            --fg-1:var(--gray-900); --fg-2:var(--gray-700); --fg-3:var(--gray-500); --fg-4:var(--gray-400);
            --border-1: var(--gray-200);
            --font-sans: inherit;
            --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            --text-sm: 0.875rem; --text-base: 1rem;
            --radius-md:6px; --radius-lg:8px; --radius-xl:12px; --radius-full:9999px;
            --shadow-dropdown:0 4px 20px -4px rgb(0 0 0 / 0.1);
            --shadow-lg:0 10px 15px -3px rgb(0 0 0 / 0.1);
            --ease: cubic-bezier(0.4, 0, 0.2, 1);
            --dur:150ms; --dur-fast:75ms;
        }
        .dark .rent-app {
            --bg-surface: #18181b;
            --gray-50:#1f1f23; --gray-100:#27272a; --gray-200:#3f3f46; --gray-300:#52525b;
            --fg-1:#fafafa; --fg-2:#e4e4e7; --fg-3:#a1a1aa; --fg-4:#71717a;
            --border-1:#27272a;
        }

        .rent-app * { box-sizing: border-box; }
        .rent-app { font-family: var(--font-sans); font-size: var(--text-sm); color: var(--fg-1); line-height: 1.5; }

        /* ============== Show/hide for desktop vs mobile sections ============== */
        .rent-app .desktop-view { display: block; }
        .rent-app .mobile-view  { display: none; }
        @media (max-width: 768px) {
            .rent-app .desktop-view { display: none; }
            /* Desktop toast lives outside .desktop-view, so hide it explicitly on mobile
               (mobile has its own .mtoast) — otherwise both fire on rent-toast and stack. */
            .rent-app .toast { display: none !important; }
            .rent-app .mobile-view  { display: flex; flex-direction: column; min-height: 100vh; background: #f3f1ed; margin: 0 -1rem; }
            .dark .rent-app .mobile-view { background: #09090b; }
        }

        /* ====================================================
           DESKTOP V1
           ==================================================== */
        .rent-app .btn { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border-radius:var(--radius-lg); font-size:var(--text-sm); font-weight:600; line-height:1; border:1px solid transparent; cursor:pointer; transition: background var(--dur) var(--ease), border-color var(--dur) var(--ease); white-space:nowrap; }
        .rent-app .btn-icon { padding:8px; }
        .rent-app .btn-primary { background: var(--danger-600); color:#fff; }
        .rent-app .btn-primary:hover { background: var(--danger-700); }
        .rent-app .btn-secondary { background:#fff; color: var(--fg-1); border-color: var(--border-1); }
        .dark .rent-app .btn-secondary { background: var(--bg-surface); }
        .rent-app .btn-secondary:hover { background: var(--gray-50); }
        .rent-app .btn-ghost { background: transparent; color: var(--fg-2); }
        .rent-app .btn-ghost:hover { background: var(--gray-100); color: var(--fg-1); }
        .rent-app .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .rent-app .field { display:block; }
        .rent-app .label { display:block; font-size:12.5px; font-weight:600; color:var(--fg-2); margin-bottom:6px; }
        .rent-app .label .req { color:var(--danger-600); margin-left:2px; }
        .rent-app .input, .rent-app .select { width:100%; padding:8px 12px; border:1px solid var(--border-1); border-radius:var(--radius-lg); background:#fff; color:var(--fg-1); font:400 14px var(--font-sans); outline:none; transition: border-color var(--dur), box-shadow var(--dur); }
        .dark .rent-app .input, .dark .rent-app .select { background: var(--bg-surface); }
        .rent-app .input:focus, .rent-app .select:focus { border-color: var(--danger-500); box-shadow:0 0 0 3px color-mix(in srgb, var(--danger-500) 25%, transparent); }
        .rent-app .input[readonly] { background: var(--gray-50); color: var(--fg-3); }
        .rent-app .input-prefix-wrap { display:flex; align-items:stretch; border:1px solid var(--border-1); border-radius:var(--radius-lg); background:#fff; overflow:hidden; }
        .dark .rent-app .input-prefix-wrap { background: var(--bg-surface); }
        .rent-app .input-prefix-wrap:focus-within { border-color: var(--danger-500); box-shadow:0 0 0 3px color-mix(in srgb, var(--danger-500) 25%, transparent); }
        .rent-app .input-prefix-wrap .prefix { padding:8px 12px; background:var(--gray-50); color:var(--fg-3); border-right:1px solid var(--border-1); display:flex; align-items:center; font-size:14px; }
        .rent-app .input-prefix-wrap .input { border:0; border-radius:0; box-shadow:none; flex:1; }
        .rent-app .help { font-size:12.5px; color: var(--fg-3); margin-top:6px; }

        .rent-app .card { background: var(--bg-surface); border:1px solid var(--border-1); border-radius: var(--radius-lg); }
        .rent-app .card-head { padding:14px 20px; border-bottom:1px solid var(--border-1); display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .rent-app .card-head h3 { margin:0; font-size: var(--text-base); font-weight:600; }
        .rent-app .card-body { padding:20px; }

        .rent-app .pill { display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:var(--radius-full); font-size:12px; font-weight:600; line-height:1.4; }
        .rent-app .pill::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
        .rent-app .pill-blue { background:#e0f2fe; color:#075985; }
        .rent-app .pill-green { background: var(--success-100); color: var(--success-700); }
        .rent-app .pill-amber { background: var(--warning-100); color: var(--warning-800); }
        .rent-app .pill-red { background: var(--danger-100); color: var(--danger-700); }
        .rent-app .pill-gray { background: var(--gray-100); color: var(--fg-2); }

        .rent-app .topbar { position: sticky; top: 0; z-index: 30; background: rgba(255,255,255,0.92); backdrop-filter: blur(8px); border-bottom: 1px solid var(--border-1); margin: -1.5rem -1.5rem 0; }
        .dark .rent-app .topbar { background: rgba(24,24,27,0.92); }
        .rent-app .topbar-inner { padding:12px 24px; display:flex; align-items:center; gap:16px; }
        .rent-app .crumbs { display:flex; align-items:center; gap:6px; min-width:0; font-size:12.5px; color: var(--fg-3); }
        .rent-app .crumbs a { color: var(--fg-3); text-decoration:none; }
        .rent-app .crumbs a:hover { color: var(--fg-1); }
        .rent-app .crumbs .sep { color: var(--gray-300); }
        .rent-app .topbar h1 { margin:0; font-size:18px; font-weight:700; color: var(--fg-1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-variant-numeric: tabular-nums; }
        .rent-app .topbar-actions { margin-left: auto; display:flex; align-items:center; gap:8px; }

        /* Kebab dropdown (shared desktop + mobile) */
        .rent-app .kebab-wrap { position: relative; }
        .rent-app .kebab-btn { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; padding:0; border-radius: var(--radius-lg); border:1px solid var(--border-1); background:#fff; color: var(--fg-2); cursor:pointer; transition: background var(--dur), color var(--dur), border-color var(--dur); }
        .dark .rent-app .kebab-btn { background: var(--bg-surface); }
        .rent-app .kebab-btn:hover { background: var(--gray-50); color: var(--fg-1); }
        .rent-app .kebab-menu { position:absolute; top: calc(100% + 6px); right: 0; min-width: 220px; background:#fff; border:1px solid var(--border-1); border-radius: var(--radius-lg); box-shadow: var(--shadow-dropdown); z-index: 40; padding: 6px; display:flex; flex-direction: column; gap: 2px; }
        .dark .rent-app .kebab-menu { background: var(--bg-surface); }
        .rent-app .kebab-item { display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:6px; font-size:13.5px; color: var(--fg-1); background:transparent; border:0; cursor:pointer; text-align:left; width:100%; }
        .rent-app .kebab-item:hover { background: var(--gray-50); }
        .rent-app .kebab-item.danger { color: var(--danger-700); }
        .rent-app .kebab-item.danger:hover { background: var(--danger-50); }
        .rent-app .kebab-item[disabled] { opacity:.5; cursor: not-allowed; }
        .rent-app .kebab-divider { height:1px; background: var(--border-1); margin: 4px 0; }

        /* Mobile sub-header (back + title + kebab), pushed below Filament sticky top bar */
        .rent-app .mobile-subhead {
            display: none;
        }
        @media (max-width: 768px) {
            .rent-app .mobile-subhead {
                display: flex; align-items: center; gap: 8px;
                padding: 10px 12px;
                background: #fff; border-bottom: 1px solid var(--border-1);
                position: sticky; top: 0; z-index: 25;
            }
            .dark .rent-app .mobile-subhead { background: var(--bg-surface); }
            .rent-app .mobile-subhead .msh-back,
            .rent-app .mobile-subhead .msh-kebab {
                display:inline-flex; align-items:center; justify-content:center;
                width: 36px; height: 36px; border-radius: 10px;
                border:1px solid var(--border-1); background:#fff; color: var(--fg-1); cursor:pointer; padding:0;
            }
            .dark .rent-app .mobile-subhead .msh-back,
            .dark .rent-app .mobile-subhead .msh-kebab { background: var(--bg-surface); }
            .rent-app .mobile-subhead .msh-title {
                flex: 1; min-width: 0;
                font-size: 15px; font-weight: 700; color: var(--fg-1);
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            }
            .rent-app .mobile-subhead .msh-title .msh-sub {
                display: block; font-size: 11px; font-weight: 500; color: var(--fg-3);
            }
        }

        .rent-app .page { padding:20px 24px 64px; display:flex; flex-direction:column; gap:20px; }

        .rent-app .items-toolbar { display:flex; align-items:center; gap:8px; padding:12px 16px; background: var(--gray-50); border-bottom:1px solid var(--border-1); flex-wrap: wrap; }
        .rent-app .search-box { flex:1 1 320px; position: relative; display: flex; align-items: center; }
        .rent-app .search-box .icon { position:absolute; left:10px; color:var(--fg-3); pointer-events:none; display:flex; }
        .rent-app .search-box .input { padding-left: 36px; padding-right: 80px; background:#fff; }
        .dark .rent-app .search-box .input { background: var(--bg-surface); }
        .rent-app .search-box .kbd-hint { position:absolute; right:8px; display:flex; gap:4px; pointer-events:none; }
        .rent-app .kbd { font-family: var(--font-mono); font-size:11px; padding:2px 6px; border-radius:4px; background:#fff; color: var(--fg-3); border:1px solid var(--border-1); line-height:1; height:18px; display:inline-flex; align-items:center; }

        .rent-app .search-results { position: absolute; top: calc(100% + 6px); left: 0; right: 0; background:#fff; border:1px solid var(--border-1); border-radius: var(--radius-lg); box-shadow: var(--shadow-dropdown); max-height: 360px; overflow:auto; z-index: 20; }
        .dark .rent-app .search-results { background: var(--bg-surface); }
        .rent-app .search-result { display:grid; grid-template-columns: 36px 1fr auto auto; gap:12px; align-items:center; padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--gray-100); }
        .rent-app .search-result:last-child { border-bottom: 0; }
        .rent-app .search-result:hover { background: var(--danger-50); }
        .rent-app .search-result .thumb { width:36px; height:36px; border-radius:6px; background: var(--gray-100); display:flex; align-items:center; justify-content:center; font-size:16px; }
        .rent-app .search-result .name { font-size:13.5px; font-weight:500; color: var(--fg-1); }
        .rent-app .search-result .meta { font-size:11.5px; color: var(--fg-3); font-family: var(--font-mono); }
        .rent-app .search-result .stock { font-size:11.5px; font-weight:600; padding:2px 8px; border-radius: var(--radius-full); background: var(--success-50); color: var(--success-700); white-space: nowrap; }
        .rent-app .search-result .stock.low { background: var(--warning-50); color: var(--warning-800); }
        .rent-app .search-result .stock.out { background: var(--danger-50); color: var(--danger-700); }
        .rent-app .search-result .price { font-size:12px; color: var(--fg-2); font-variant-numeric: tabular-nums; }
        .rent-app .search-empty { padding:24px; text-align:center; color: var(--fg-3); font-size:13px; }

        .rent-app .items-table { width:100%; }
        .rent-app .items-head, .rent-app .item-row { display:grid; grid-template-columns: 24px 28px minmax(0, 2.2fr) 90px 130px 110px 64px 110px 36px; align-items:center; gap:12px; padding:8px 16px; }
        .rent-app .items-head { background: var(--gray-50); border-bottom: 1px solid var(--border-1); font-size:11.5px; font-weight:600; color: var(--fg-3); text-transform: uppercase; letter-spacing:.04em; }
        .rent-app .items-head .num-col, .rent-app .items-head .right { text-align: right; }
        .rent-app .items-head .qty-head { text-align: center; padding-right: 36px; }
        .rent-app .item-row { border-bottom: 1px solid var(--gray-100); background: var(--bg-surface); transition: background var(--dur-fast); }
        .rent-app .item-row:hover { background: var(--gray-50); }
        .rent-app .grip { cursor: grab; color: var(--fg-4); display:flex; align-items:center; justify-content:center; opacity:0; transition: opacity var(--dur); width:24px; height:24px; border-radius:4px; }
        .rent-app .grip:hover { background: var(--gray-200); color: var(--fg-2); }
        .rent-app .item-row:hover .grip { opacity:1; }
        .rent-app .item-row.dragging { opacity:.4; }
        .rent-app .row-num { color: var(--fg-3); font-size:12px; font-variant-numeric: tabular-nums; text-align:right; }
        .rent-app .prod-cell { display:flex; align-items:center; gap:10px; min-width:0; }
        .rent-app .prod-thumb { width:32px; height:32px; border-radius:6px; flex:0 0 32px; background: var(--gray-100); display:flex; align-items:center; justify-content:center; font-size:14px; overflow:hidden; }
        .rent-app .prod-info { min-width:0; }
        .rent-app .prod-name { font-size:13.5px; font-weight:500; color: var(--fg-1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rent-app .prod-meta { font-size:11.5px; color: var(--fg-3); display:flex; gap:8px; align-items:center; }
        .rent-app .prod-meta .sku { font-family: var(--font-mono); }
        .rent-app .stock-cell { font-size:12.5px; font-variant-numeric: tabular-nums; display:flex; align-items:center; gap:4px; }
        .rent-app .stock-cell .dot { width:6px; height:6px; border-radius:50%; background: var(--success-600); }
        .rent-app .stock-cell.low .dot { background: var(--warning-600); }
        .rent-app .stock-cell.out .dot { background: var(--danger-600); }
        .rent-app .stock-cell.low { color: var(--warning-800); }
        .rent-app .stock-cell.out { color: var(--danger-700); font-weight:600; }
        .rent-app .cell-input { width:100%; padding:6px 8px; border:1px solid transparent; border-radius:6px; background:transparent; font:500 13.5px var(--font-sans); color: var(--fg-1); text-align:right; font-variant-numeric: tabular-nums; outline:none; transition: background var(--dur), border-color var(--dur), box-shadow var(--dur); }
        .rent-app .cell-input:hover { background:#fff; border-color: var(--border-1); }
        .dark .rent-app .cell-input:hover { background: var(--bg-surface); }
        .rent-app .cell-input:focus { background:#fff; border-color: var(--danger-500); box-shadow: 0 0 0 3px color-mix(in srgb, var(--danger-500) 25%, transparent); }
        .dark .rent-app .cell-input:focus { background: var(--bg-surface); }
        .rent-app .cell-input-wrap { display:flex; align-items:center; gap:2px; border:1px solid transparent; border-radius:6px; }
        .rent-app .cell-input-wrap:hover { background:#fff; border-color: var(--border-1); }
        .dark .rent-app .cell-input-wrap:hover { background: var(--bg-surface); }
        .rent-app .cell-input-wrap:focus-within { background:#fff; border-color: var(--danger-500); box-shadow: 0 0 0 3px color-mix(in srgb, var(--danger-500) 25%, transparent); }
        .dark .rent-app .cell-input-wrap:focus-within { background: var(--bg-surface); }
        .rent-app .cell-input-wrap .unit { font-size:11px; color: var(--fg-3); padding-right:6px; }
        .rent-app .cell-input-wrap .cell-input { border:0; box-shadow:none; padding-right:2px; }
        .rent-app .subtotal-cell { font-size:13.5px; font-weight:600; text-align:right; font-variant-numeric: tabular-nums; padding-right:8px; }
        .rent-app .row-actions { display:flex; justify-content:center; }
        .rent-app .row-actions .btn-icon { color: var(--fg-3); padding:4px; border-radius:4px; opacity:0; background: transparent; border:0; cursor:pointer; transition: opacity var(--dur), color var(--dur), background var(--dur); }
        .rent-app .item-row:hover .row-actions .btn-icon { opacity:1; }
        .rent-app .row-actions .btn-icon:hover { color: var(--danger-600); background: var(--danger-50); }

        .rent-app .qty-cell-inner { display:flex; align-items:center; gap:6px; }
        .rent-app .qty-cell-inner .cell-input { flex:1; min-width:0; }
        .rent-app .unit-btn { position: relative; display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; flex:0 0 30px; background:#fff; border:1px solid var(--border-1); border-radius:6px; color: var(--fg-2); cursor: pointer; transition: background .12s ease, border-color .12s ease, color .12s ease; padding:0; }
        .dark .rent-app .unit-btn { background: var(--bg-surface); }
        .rent-app .unit-btn:hover { background: var(--gray-50); border-color: var(--gray-400); color: var(--fg-1); }
        .rent-app .unit-btn.sm { width:28px; height:28px; flex-basis:28px; }
        .rent-app .unit-btn.has-missing { border-color: var(--warning-300); background: var(--warning-50); color: var(--warning-800); }
        .rent-app .unit-btn-badge { position:absolute; top:-5px; right:-5px; min-width:16px; height:16px; padding:0 4px; border-radius:8px; background: var(--danger-600); color:#fff; font:700 10px/16px var(--font-sans); text-align:center; border: 1.5px solid var(--bg-surface); font-variant-numeric: tabular-nums; }
        .rent-app .unit-inline { display:inline-flex; align-items:center; gap:6px; min-width:0; max-width:100%; font-family: var(--font-mono); font-size:11px; color: var(--fg-3); }
        .rent-app .unit-inline-text { min-width:0; max-width:26ch; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:inline-block; }
        .rent-app .unit-inline-missing { display:inline-flex; align-items:center; padding:1px 6px; border-radius: var(--radius-full); background: var(--warning-50); color: var(--warning-800); font-family: var(--font-sans); font-size:10.5px; font-weight:600; white-space:nowrap; }
        .rent-app .unit-inline.empty { color: var(--danger-700); font-family: var(--font-sans); font-weight:600; font-size:11.5px; }

        .rent-app .totals-grid { display:grid; grid-template-columns: 1fr 360px; gap:20px; }
        .rent-app .totals-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom: 1px solid var(--gray-100); font-size:13.5px; }
        .rent-app .totals-row:last-child { border-bottom:0; }
        .rent-app .totals-row .lbl { color: var(--fg-2); }
        .rent-app .totals-row .val { font-weight:600; font-variant-numeric: tabular-nums; }
        .rent-app .totals-row.grand { border-top: 2px solid var(--gray-200); margin-top:4px; padding-top:14px; font-size:16px; }
        .rent-app .totals-row.grand .val { color: var(--danger-600); font-size:18px; font-weight:700; }
        .rent-app .toggle-type-btn {
            width: 28px; height: 28px;
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--gray-50); color: var(--fg-2);
            border: 1px solid var(--border-1); border-radius: 6px;
            cursor: pointer; padding: 0; flex: 0 0 28px;
            transition: background .12s ease, color .12s ease;
        }
        .rent-app .toggle-type-btn:hover { background: var(--gray-100); color: var(--fg-1); }
        .rent-app .toggle-type-btn.active { background: var(--danger-50); color: var(--danger-700); border-color: var(--danger-200); }
        .rent-app .totals-row .val.neg { color: var(--danger-700); }
        .rent-app .count-chip { display:inline-flex; align-items:center; gap:6px; background:#fff; border:1px solid var(--border-1); padding:6px 10px; border-radius:var(--radius-full); font-size:12.5px; color: var(--fg-2); }
        .dark .rent-app .count-chip { background: var(--bg-surface); }
        .rent-app .count-chip strong { color: var(--fg-1); font-weight:700; }

        /* === Modal === */
        .rent-app .modal-backdrop { position: fixed; inset: 0; background: rgba(17,24,39,0.5); display:flex; align-items:center; justify-content:center; z-index: 100; padding:24px; }
        .rent-app .modal { background:#fff; border-radius: var(--radius-xl); width:100%; max-width: 720px; max-height: 86vh; display:flex; flex-direction:column; box-shadow: var(--shadow-lg); overflow:hidden; }
        .dark .rent-app .modal { background: var(--bg-surface); }
        .rent-app .modal-head { padding:16px 20px; border-bottom: 1px solid var(--border-1); display:flex; align-items:center; justify-content:space-between; }
        .rent-app .modal-head h3 { margin:0; font-size:16px; font-weight:700; }
        .rent-app .modal-body { padding:16px 20px; overflow:auto; flex:1; }
        .rent-app .modal-foot { padding:14px 20px; border-top:1px solid var(--border-1); display:flex; gap:8px; justify-content:space-between; align-items:center; }
        .rent-app .bulk-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:8px; }
        .rent-app .bulk-card { border:1px solid var(--border-1); border-radius: var(--radius-lg); padding:10px 12px; cursor:pointer; background:#fff; display:flex; align-items:center; gap:10px; transition: border-color var(--dur), background var(--dur); }
        .dark .rent-app .bulk-card { background: var(--bg-surface); }
        .rent-app .bulk-card:hover { border-color: var(--gray-400); }
        .rent-app .bulk-card .thumb { width:44px; height:44px; border-radius:8px; background: var(--gray-100); display:flex; align-items:center; justify-content:center; font-size:15px; flex:0 0 44px; overflow:hidden; }
        .rent-app .bulk-card .thumb img { width:100%; height:100%; object-fit:cover; display:block; }
        .rent-app .bulk-card .name { font-size:12.5px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rent-app .bulk-card .sub { font-size:11px; color: var(--fg-3); font-family: var(--font-mono); }
        .rent-app .bulk-card.in-cart { border-color: var(--primary-300, #93c5fd); background: var(--primary-50, #eff6ff); }
        .dark .rent-app .bulk-card.in-cart { background: rgba(59,130,246,0.08); border-color: var(--primary-500, #3b82f6); }
        .rent-app .bulk-add-btn { flex: 0 0 auto; width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--border-1); background: #fff; color: var(--fg-2); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: background var(--dur), border-color var(--dur), color var(--dur); }
        .rent-app .bulk-add-btn:hover { background: var(--primary-50, #eff6ff); border-color: var(--primary-400, #60a5fa); color: var(--primary-700, #1d4ed8); }
        .dark .rent-app .bulk-add-btn { background: var(--bg-surface); }
        .rent-app .bulk-stepper { flex: 0 0 auto; display: inline-flex; align-items: center; gap: 2px; background: #fff; border: 1px solid var(--primary-300, #93c5fd); border-radius: 8px; padding: 2px; }
        .dark .rent-app .bulk-stepper { background: var(--bg-surface); }
        .rent-app .bulk-step-btn { width: 26px; height: 26px; border-radius: 6px; border: 0; background: transparent; color: var(--primary-700, #1d4ed8); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: background var(--dur); }
        .rent-app .bulk-step-btn:hover { background: var(--primary-50, #eff6ff); }
        .rent-app .bulk-step-qty { min-width: 22px; text-align: center; font-size: 13px; font-weight: 600; color: var(--primary-700, #1d4ed8); font-variant-numeric: tabular-nums; }
        .rent-app .bulk-cat-tabs { display:flex; gap:4px; flex-wrap: wrap; margin-bottom:12px; }
        .rent-app .bulk-cat-tabs button { padding:5px 10px; font-size:12px; font-weight:500; background: var(--gray-100); border:0; border-radius: var(--radius-full); color: var(--fg-2); cursor:pointer; }
        .rent-app .bulk-cat-tabs button.active { background: var(--danger-600); color:#fff; }

        /* === Unit modal === */
        /* ====================================================
           KELOLA UNIT + TRANSFER sheets — Opsi B redesign.
           Shared markup desktop ⇆ mobile (mobile = bottom sheet via CSS);
           same Livewire functionality, refreshed presentation.
           ==================================================== */
        .rent-app .unit-modal { max-width: 520px; }

        /* summary bar (period + availability) */
        .rent-app .um-summary { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 20px; background: var(--gray-50); border-bottom:1px solid var(--border-1); font-size:12.5px; }
        .dark .rent-app .um-summary { background: var(--gray-100); }
        .rent-app .um-summary .ums-l { color: var(--fg-3); }
        .rent-app .um-summary .ums-r { font-weight:700; font-variant-numeric: tabular-nums; }
        .rent-app .um-summary .ums-r.ok { color: var(--success-700); }
        .rent-app .um-summary .ums-r.warn { color: var(--danger-600); }

        .rent-app .unit-modal-banner { margin: 12px 20px 0; padding:10px 12px; border-radius:10px; display:flex; align-items:flex-start; gap:8px; font-size:12.5px; line-height:1.4; background: var(--warning-50); color: var(--warning-800); border: 1px solid var(--warning-100); }

        /* toolbar: count pill + auto-assign / clear tools */
        .rent-app .um-toolbar { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 20px 4px; }
        .rent-app .um-count { font-size:12px; font-weight:700; padding:5px 11px; border-radius: var(--radius-full); white-space:nowrap; font-variant-numeric: tabular-nums; }
        .rent-app .um-count.ok { background: var(--success-50); color: var(--success-700); }
        .rent-app .um-count.warn { background: var(--warning-50); color: var(--warning-800); }
        .rent-app .um-tools { display:flex; align-items:center; gap:16px; }
        .rent-app .um-tool { display:inline-flex; align-items:center; gap:5px; border:0; background:transparent; padding:0; font:700 12.5px var(--font-sans); color: var(--fg-2); cursor:pointer; }
        .rent-app .um-tool svg { width:13px; height:13px; }
        .rent-app .um-tool:disabled { color: var(--fg-4); cursor:not-allowed; }
        .rent-app .um-tool.danger { color: var(--danger-600); }
        .rent-app .um-tool.danger:disabled { color: var(--fg-4); }

        /* slots */
        .rent-app .um-slots { flex:1 1 auto; overflow-y:auto; padding:8px 20px 6px; display:flex; flex-direction:column; gap:15px; }
        .rent-app .um-slot-lbl { display:block; font-size:12.5px; font-weight:700; color: var(--fg-2); margin-bottom:7px; }
        .rent-app .um-slot-lbl .req { color: var(--danger-600); margin-left:2px; }
        .rent-app .sel-wrap { position:relative; }
        .rent-app .sel { width:100%; height:46px; padding:0 40px 0 13px; border:1.5px solid var(--border-1); border-radius:11px; background:#fff; font:600 13.5px var(--font-mono); color: var(--fg-1); appearance:none; -webkit-appearance:none; cursor:pointer; }
        .dark .rent-app .sel { background: var(--bg-surface); }
        .rent-app .sel:focus { outline:none; border-color: var(--danger-500); box-shadow: 0 0 0 3px color-mix(in srgb, var(--danger-500) 22%, transparent); }
        .rent-app .sel.sans { font-family: var(--font-sans); }
        .rent-app .sel:disabled { background: var(--gray-50); color: var(--fg-4); cursor:not-allowed; }
        .rent-app .um-slot.empty .sel { border-color: var(--warning-300); background-color: var(--warning-50); color: var(--warning-800); }
        .rent-app .sel-chev { position:absolute; right:13px; top:50%; transform:translateY(-50%); color: var(--fg-4); pointer-events:none; display:flex; }
        .rent-app .sel-chev svg { width:16px; height:16px; }

        /* per-slot transfer links */
        .rent-app .um-xfer { display:flex; align-items:center; gap:10px; margin-top:8px; flex-wrap:wrap; }
        .rent-app .um-xfer-lbl { font-size:10px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color: var(--fg-4); }
        .rent-app .um-xfer-link { border:0; background:transparent; padding:0; font:700 12.5px var(--font-sans); cursor:pointer; }
        .rent-app .um-xfer-link.move { color: var(--danger-600); }
        .rent-app .um-xfer-link.swap { color: #1F6FEB; }
        .rent-app .um-xfer-link.pull { display:inline-flex; align-items:center; gap:5px; color: var(--warning-800); }
        .rent-app .um-xfer-link.pull svg { width:13px; height:13px; }
        .rent-app .um-xfer-dot { color: var(--gray-300); font-weight:700; }

        .rent-app .unit-modal-empty { padding:24px; text-align:center; color: var(--danger-700); }
        .rent-app .unit-modal-empty .t { font-size:15px; font-weight:700; margin-bottom:4px; }
        .rent-app .unit-modal-empty .s { font-size:12.5px; }

        /* transfer sheet (Move / Swap / Tarik) */
        .rent-app .tx-seg { display:flex; gap:6px; padding:14px 20px 6px; }
        .rent-app .tx-seg button { flex:1; height:38px; border-radius:10px; border:1px solid var(--border-1); background:#fff; font:700 13px var(--font-sans); color: var(--fg-2); cursor:pointer; }
        .dark .rent-app .tx-seg button { background: var(--bg-surface); }
        .rent-app .tx-seg button.on { background: var(--danger-600); border-color: var(--danger-600); color:#fff; }
        .rent-app .tx-note { font-size:12px; color: var(--fg-3); line-height:1.5; padding:6px 20px 4px; }
        .rent-app .tx-fields { padding:8px 20px 6px; display:flex; flex-direction:column; gap:14px; max-height:58vh; overflow-y:auto; }
        .rent-app .tx-fld-lbl { display:block; font-size:12.5px; font-weight:700; color: var(--fg-2); margin-bottom:7px; }
        .rent-app .tx-fld-lbl .req { color: var(--danger-600); margin-left:2px; }
        .rent-app .tx-unit-static { font-size:13px; color: var(--fg-2); }
        .rent-app .tx-unit-static strong { color: var(--fg-1); }
        .rent-app .tx-swap-arrow { display:flex; align-items:center; justify-content:center; gap:8px; color: var(--fg-4); font-size:11px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
        .rent-app .tx-swap-arrow svg { width:18px; height:18px; transform:rotate(90deg); }
        .rent-app .tx-empty-hint { font-size:11.5px; color: var(--warning-800); background: var(--warning-50); border:1px solid var(--warning-100); border-radius:10px; padding:9px 12px; line-height:1.45; }

        /* searchable combobox (transfer modal) */
        .rent-app .tx-combo { position:relative; }
        .rent-app .tx-combo-trigger { width:100%; height:46px; padding:0 40px 0 13px; border:1.5px solid var(--border-1); border-radius:11px; background:#fff; font:600 13.5px var(--font-sans); color: var(--fg-1); cursor:pointer; display:flex; align-items:center; text-align:left; }
        .dark .rent-app .tx-combo-trigger { background: var(--bg-surface); }
        .rent-app .tx-combo-trigger:focus { outline:none; border-color: var(--danger-500); box-shadow: 0 0 0 3px color-mix(in srgb, var(--danger-500) 22%, transparent); }
        .rent-app .tx-combo-trigger .lbl { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .rent-app .tx-combo-trigger .lbl.ph { color: var(--fg-4); font-weight:500; }
        .rent-app .tx-combo-pop { margin-top:6px; background:#fff; border:1.5px solid var(--border-1); border-radius:11px; box-shadow: var(--shadow-lg); padding:7px; }
        .dark .rent-app .tx-combo-pop { background: var(--bg-surface); }
        .rent-app .tx-combo-search { width:100%; height:38px; padding:0 11px; border:1px solid var(--border-1); border-radius:8px; background: var(--gray-50); font:500 13px var(--font-sans); color: var(--fg-1); margin-bottom:6px; }
        .dark .rent-app .tx-combo-search { background: var(--bg-1); }
        .rent-app .tx-combo-search:focus { outline:none; border-color: var(--danger-500); }
        .rent-app .tx-combo-list { max-height:230px; overflow-y:auto; display:flex; flex-direction:column; gap:2px; }
        .rent-app .tx-combo-opt { display:block; width:100%; text-align:left; padding:9px 11px; border:0; border-radius:8px; background:transparent; font:500 13px var(--font-sans); color: var(--fg-2); cursor:pointer; white-space:normal; line-height:1.35; }
        .rent-app .tx-combo-opt:hover { background: var(--gray-100); }
        .dark .rent-app .tx-combo-opt:hover { background: var(--bg-1); }
        .rent-app .tx-combo-opt.on { background: var(--danger-50); color: var(--danger-700); font-weight:700; }
        .rent-app .tx-combo-empty { padding:12px; text-align:center; font-size:12px; color: var(--fg-4); }

        /* === Toast === */
        .rent-app .toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: var(--gray-900); color:#fff; padding:10px 16px; border-radius: var(--radius-lg); font-size:13px; z-index: 200; display:flex; gap:8px; align-items:center; box-shadow: var(--shadow-lg); }

        /* === QR scanner overlay === */
        .rent-app .qr-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 250; display:flex; align-items:center; justify-content:center; padding: 24px; }
        .rent-app .qr-modal { background:#fff; border-radius: 16px; width: 100%; max-width: 480px; padding: 20px; }
        .dark .rent-app .qr-modal { background: var(--bg-surface); }
        .rent-app .qr-modal-head { display:flex; align-items:center; justify-content:space-between; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid var(--border-1); }
        .rent-app .qr-modal-head h3 { margin:0; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; color: var(--fg-1); }
        .rent-app .qr-modal #qr-reader { width: 100%; min-height: 280px; }
        .rent-app .qr-modal .qr-hint { margin-top: 12px; font-size: 12.5px; color: var(--fg-3); text-align: center; }

        /* ====================================================
           MOBILE V1 layout — dedicated mobile, not desktop restyled
           Scoped inside .rent-app .mobile-view
           ==================================================== */
        .rent-app .mobile-view { font-size: 14px; }
        .rent-app .mobile-view .mbody {
            flex: 1 1 auto;
            overflow-y: visible;
            padding-bottom: calc(100px + env(safe-area-inset-bottom, 0px)); /* editbar (nav global disembunyikan di halaman ini) */
        }
        .rent-app .mobile-view .msec {
            background: #fff; margin: 10px 12px 0; border-radius: 14px; border: 1px solid var(--border-1); overflow: hidden;
        }
        .dark .rent-app .mobile-view .msec { background: var(--bg-surface); }
        .rent-app .mobile-view .msec-head {
            padding: 12px 14px;
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
        }
        .rent-app .mobile-view .msec-head h4 {
            margin: 0; font-size: 11.5px; font-weight: 700;
            color: var(--fg-3); text-transform: uppercase; letter-spacing: 0.06em;
        }
        .rent-app .mobile-view .msec-head .count { font-size: 11.5px; color: var(--fg-2); font-weight: 600; }
        .rent-app .mobile-view .inforow {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 12px 14px; border-top: 1px solid var(--gray-100);
        }
        .rent-app .mobile-view .inforow:first-of-type { border-top: 0; }
        .rent-app .mobile-view .inforow .k { font-size: 12.5px; color: var(--fg-3); font-weight: 500; }
        .rent-app .mobile-view .inforow .v { font-size: 13.5px; color: var(--fg-1); font-weight: 600; text-align: right; flex: 1; min-width: 0; }
        .rent-app .mobile-view .inforow .v.muted { color: var(--fg-2); font-weight: 500; }

        /* Mobile pills */
        .rent-app .mobile-view .pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 9px; border-radius: var(--radius-full);
            font-size: 11px; font-weight: 700; white-space: nowrap;
        }
        .rent-app .mobile-view .pill::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }

        /* Customer card */
        .rent-app .mobile-view .cust-row {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 14px;
            background: linear-gradient(135deg, color-mix(in srgb, var(--danger-50) 70%, #fff) 0%, #fff 60%);
        }
        .dark .rent-app .mobile-view .cust-row { background: linear-gradient(135deg, color-mix(in srgb, var(--danger-600) 12%, transparent) 0%, var(--bg-surface) 60%); }
        .rent-app .mobile-view .cust-row .avatar {
            width: 44px; height: 44px; border-radius: 50%; flex: 0 0 44px;
            background: var(--danger-100); color: var(--danger-700);
            font-weight: 700; font-size: 16px;
            display: flex; align-items: center; justify-content: center;
            margin-top: 1px;
        }
        .rent-app .mobile-view .cust-row .cust-info { flex: 1; min-width: 0; }
        .rent-app .mobile-view .cust-row .cust-name {
            font-size: 14.5px; font-weight: 700; color: var(--fg-1);
            display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
        }
        .rent-app .mobile-view .cust-row .cust-meta { font-size: 12.5px; color: var(--fg-3); margin-top: 1px; }
        .rent-app .mobile-view .cust-row .cust-action {
            padding: 6px 10px;
            background: #fff; border: 1px solid var(--border-1);
            border-radius: 8px;
            font-size: 12px; font-weight: 600; color: var(--fg-2);
            cursor: pointer; align-self: flex-start;
        }
        .dark .rent-app .mobile-view .cust-row .cust-action { background: var(--bg-surface); }
        .rent-app .mobile-view .cust-caps {
            display: flex; gap: 6px; align-items: center; flex-wrap: wrap;
            margin-top: 8px;
        }
        .rent-app .mobile-view .cap {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 9px; border-radius: var(--radius-full);
            font-size: 10.5px; font-weight: 700;
        }
        .rent-app .mobile-view .cap.cap-code {
            background: var(--gray-100); color: var(--fg-2); font-family: var(--font-mono);
        }

        /* Date / period card */
        .rent-app .mobile-view .date-card {
            display: grid; grid-template-columns: 1fr auto 1fr; gap: 10px;
            padding: 14px; align-items: center;
        }
        .rent-app .mobile-view .date-card .date {
            display: flex; flex-direction: column; gap: 2px;
            padding: 10px 12px;
            background: var(--gray-50); border-radius: 10px;
        }
        .rent-app .mobile-view .date-card .date-label {
            font-size: 10.5px; font-weight: 700; color: var(--fg-3);
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .rent-app .mobile-view .date-card .date-val {
            font-size: 13px; font-weight: 700; color: var(--fg-1); font-variant-numeric: tabular-nums;
        }
        .rent-app .mobile-view .date-card .date-time {
            font-size: 11.5px; color: var(--fg-3); font-variant-numeric: tabular-nums;
        }
        .rent-app .mobile-view .date-card .arrow {
            color: var(--fg-4); display: flex; align-items: center; justify-content: center;
        }
        .rent-app .mobile-view .duration-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 14px;
            background: var(--danger-50);
            border-top: 1px solid var(--danger-100);
            font-size: 12.5px;
        }
        .rent-app .mobile-view .duration-row strong { color: var(--danger-700); font-weight: 700; }

        .rent-app .mobile-view .date-input-wrap {
            position: relative; cursor: pointer;
        }
        .rent-app .mobile-view .date-input-wrap input {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; border: 0;
        }

        /* Reorder hint */
        .rent-app .mobile-view .reorder-hint {
            display: flex; align-items: center; gap: 6px;
            padding: 6px 14px 8px;
            font-size: 11px; color: var(--fg-3);
            background: var(--gray-50);
            border-top: 1px solid var(--gray-100); border-bottom: 1px solid var(--gray-100);
        }

        /* Item card */
        .rent-app .mobile-view .item-stack { display: flex; flex-direction: column; }
        .rent-app .mobile-view .mitem {
            padding: 12px 14px 12px 8px;
            border-top: 1px solid var(--gray-100);
            display: grid;
            grid-template-columns: 22px 1fr auto auto;
            grid-template-areas:
                'grip prod  prod  trail'
                'grip meta  meta  meta'
                'grip qty   price sub';
            gap: 4px 10px;
            background: #fff;
            position: relative;
        }
        .dark .rent-app .mobile-view .mitem { background: var(--bg-surface); }
        .rent-app .mobile-view .mitem:first-child { border-top: 0; }
        .rent-app .mobile-view .mitem.dragging { opacity: 0.45; background: var(--gray-50); }
        .rent-app .mobile-view .mitem.drag-over { background: color-mix(in srgb, var(--danger-50) 80%, #fff); box-shadow: inset 0 3px 0 var(--danger-600); }
        .rent-app .mobile-view .mitem-grip {
            grid-area: grip; display: flex; align-items: center; justify-content: center;
            color: var(--fg-4); cursor: grab; touch-action: none; align-self: center;
        }
        .rent-app .mobile-view .mitem-thumb { display: none; }
        .rent-app .mobile-view .mitem-prod {
            grid-area: prod; display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;
        }
        .rent-app .mobile-view .mitem-name {
            font-size: 13.5px; font-weight: 600; color: var(--fg-1); line-height: 1.35;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .rent-app .mobile-view .mitem-trail { grid-area: trail; display: flex; gap: 4px; align-items: center; }
        .rent-app .mobile-view .mitem-trail .iconbtn {
            width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
            background: transparent; border: 0; border-radius: 6px; color: var(--fg-3); cursor: pointer;
        }
        .rent-app .mobile-view .mitem-trail .iconbtn:active { background: var(--gray-100); color: var(--danger-600); }
        .rent-app .mobile-view .mitem-meta {
            grid-area: meta; display: flex; gap: 8px; align-items: center;
            font-size: 11.5px; color: var(--fg-3); flex-wrap: wrap;
        }
        .rent-app .mobile-view .mitem-meta .sku { font-family: var(--font-mono); }
        .rent-app .mobile-view .mitem-meta .dot { width: 3px; height: 3px; border-radius: 50%; background: var(--gray-300); }
        .rent-app .mobile-view .stock-pill {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 1px 7px; border-radius: var(--radius-full);
            font-size: 10.5px; font-weight: 600;
        }
        .rent-app .mobile-view .stock-pill::before {
            content: ''; width: 5px; height: 5px; border-radius: 50%; background: currentColor;
        }
        .rent-app .mobile-view .stock-pill.ok  { background: var(--success-50); color: var(--success-700); }
        .rent-app .mobile-view .stock-pill.low { background: var(--warning-50); color: var(--warning-800); }
        .rent-app .mobile-view .stock-pill.out { background: var(--danger-50); color: var(--danger-700); }

        .rent-app .mobile-view .mitem-qty {
            grid-area: qty; display: flex; align-items: stretch;
            border: 1px solid var(--border-1); border-radius: 8px; background: #fff;
            height: 32px; width: 102px; overflow: hidden;
        }
        .dark .rent-app .mobile-view .mitem-qty { background: var(--bg-surface); }
        .rent-app .mobile-view .mitem-qty button {
            width: 32px; flex: 0 0 32px;
            background: var(--gray-50); border: 0;
            font: 700 16px var(--font-sans); color: var(--fg-2);
            display: flex; align-items: center; justify-content: center; cursor: pointer;
        }
        .rent-app .mobile-view .mitem-qty button:active { background: var(--gray-200); color: var(--fg-1); }
        .rent-app .mobile-view .mitem-qty input {
            flex: 1; min-width: 0; border: 0; text-align: center;
            font: 600 13.5px var(--font-sans); color: var(--fg-1);
            font-variant-numeric: tabular-nums; outline: none; padding: 0; background: transparent;
        }
        .rent-app .mobile-view .mitem-qty input::-webkit-outer-spin-button,
        .rent-app .mobile-view .mitem-qty input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

        .rent-app .mobile-view .mitem-price {
            grid-area: price;
            display: flex; align-items: center; justify-content: flex-end; gap: 4px;
            font-size: 12px; color: var(--fg-3); font-variant-numeric: tabular-nums;
        }
        .rent-app .mobile-view .mitem-price strong { color: var(--fg-1); font-weight: 600; }
        .rent-app .mobile-view .mitem-sub {
            grid-area: sub;
            font-size: 14px; font-weight: 700; text-align: right;
            font-variant-numeric: tabular-nums; color: var(--fg-1); align-self: end;
        }

        /* Empty */
        .rent-app .mobile-view .empty { padding: 40px 20px; text-align: center; color: var(--fg-3); }
        .rent-app .mobile-view .empty .ico { font-size: 36px; margin-bottom: 8px; }
        .rent-app .mobile-view .empty .t { font-size: 14px; font-weight: 600; color: var(--fg-2); margin-bottom: 4px; }
        .rent-app .mobile-view .empty .s { font-size: 12.5px; }

        /* Sticky bottom dock — positioned ABOVE the gr-bottombar (64px + safe-area) */
        .rent-app .mobile-view .mdock {
            position: fixed; left: 0; right: 0;
            bottom: calc(64px + env(safe-area-inset-bottom, 0px));
            background: #fff;
            border-top: 1px solid var(--border-1);
            padding: 10px 14px 12px;
            box-shadow: 0 -8px 24px rgba(0,0,0,0.08);
            z-index: 35;
            display: flex; flex-direction: column; gap: 10px;
        }
        .dark .rent-app .mobile-view .mdock { background: var(--bg-surface); }
        .rent-app .mobile-view .mdock-summary {
            display: flex; align-items: flex-end; justify-content: space-between; gap: 12px;
        }
        .rent-app .mobile-view .mdock-meta {
            display: flex; gap: 6px; align-items: center;
            font-size: 11px; color: var(--fg-3); font-weight: 500;
        }
        .rent-app .mobile-view .mdock-meta .sep { color: var(--gray-300); }
        .rent-app .mobile-view .mdock-total { display: flex; align-items: baseline; gap: 8px; }
        .rent-app .mobile-view .mdock-total-label {
            font-size: 11px; color: var(--fg-3); font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .rent-app .mobile-view .mdock-total-val {
            font-size: 20px; font-weight: 800; color: var(--fg-1);
            font-variant-numeric: tabular-nums; line-height: 1.1;
        }
        .rent-app .mobile-view .mdock-actions {
            display: grid; grid-template-columns: 1fr 2fr; gap: 8px;
        }
        .rent-app .mobile-view .mdock .btn-cancel {
            height: 46px; background: #fff; color: var(--fg-2);
            border: 1px solid var(--border-1); border-radius: 12px;
            font: 600 14px var(--font-sans);
            display: inline-flex; align-items: center; justify-content: center; cursor: pointer;
        }
        .dark .rent-app .mobile-view .mdock .btn-cancel { background: var(--bg-surface); }
        .rent-app .mobile-view .mdock .btn-save {
            height: 46px; background: var(--danger-600); color: #fff;
            border: 0; border-radius: 12px;
            font: 700 14px var(--font-sans);
            display: inline-flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer;
        }
        .rent-app .mobile-view .mdock .btn-save:active { background: var(--danger-700); }

        /* FAB — circle, sits above the dock (which already sits above gr-bottombar) */
        .rent-app .mobile-view .fab {
            position: fixed; right: 16px;
            bottom: calc(64px + env(safe-area-inset-bottom, 0px) + 110px);
            width: 56px; height: 56px;
            background: var(--danger-600); color: #fff;
            border: 0; border-radius: 50%;
            box-shadow: 0 10px 24px color-mix(in srgb, var(--danger-600) 45%, transparent), 0 2px 4px rgba(0,0,0,0.1);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; z-index: 30; padding: 0;
        }
        .rent-app .mobile-view .fab:active { transform: scale(0.94); }

        /* Bottom sheet (catalog) */
        .rent-app .mobile-view .sheet-backdrop {
            position: fixed; inset: 0; background: rgba(17,24,39,0.5); z-index: 60;
        }
        .rent-app .mobile-view .sheet {
            position: fixed; left: 0; right: 0; bottom: 0;
            background: #fff; border-radius: 20px 20px 0 0;
            z-index: 61; max-height: 88vh;
            display: flex; flex-direction: column; overflow: hidden;
            box-shadow: 0 -10px 40px rgba(0,0,0,0.18);
            animation: sheet-in 0.22s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .dark .rent-app .mobile-view .sheet { background: var(--bg-surface); }
        @keyframes sheet-in { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .rent-app .mobile-view .sheet .grip {
            width: 36px; height: 4px; background: var(--gray-300);
            border-radius: 100px; margin: 8px auto 0; flex: 0 0 auto;
        }
        .rent-app .mobile-view .sheet-head {
            padding: 10px 14px 12px;
            display: flex; align-items: center; gap: 8px;
            border-bottom: 1px solid var(--border-1); flex: 0 0 auto;
        }
        .rent-app .mobile-view .sheet-head h3 { margin: 0; font-size: 15px; font-weight: 700; flex: 1; }
        .rent-app .mobile-view .sheet-close {
            width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            background: var(--gray-100); color: var(--fg-2);
            border: 0; border-radius: 50%; cursor: pointer;
        }
        .rent-app .mobile-view .sheet-foot {
            flex: 0 0 auto;
            padding: 10px 14px calc(10px + env(safe-area-inset-bottom, 0px));
            border-top: 1px solid var(--border-1); background: #fff;
        }
        .dark .rent-app .mobile-view .sheet-foot { background: var(--bg-surface); }
        .rent-app .mobile-view .sheet-done {
            width: 100%; height: 46px;
            background: var(--danger-600); color: #fff;
            border: 0; border-radius: 12px; font: 700 14px var(--font-sans); cursor: pointer;
        }

        /* Mobile search box */
        .rent-app .mobile-view .msearch {
            position: relative; display: flex; align-items: center;
            margin: 10px 14px; flex: 0 0 auto;
        }
        .rent-app .mobile-view .msearch .icon {
            position: absolute; left: 12px; color: var(--fg-3); display: flex;
        }
        .rent-app .mobile-view .msearch input {
            width: 100%; height: 42px; padding: 0 44px 0 38px;
            border: 1px solid var(--border-1); border-radius: 12px; background: #fff;
            font: 500 14px var(--font-sans); color: var(--fg-1); outline: none;
        }
        .dark .rent-app .mobile-view .msearch input { background: var(--bg-surface); }
        .rent-app .mobile-view .msearch input:focus {
            border-color: var(--danger-500);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--danger-500) 25%, transparent);
        }
        .rent-app .mobile-view .msearch .scan-btn {
            position: absolute; right: 6px; width: 32px; height: 32px;
            background: var(--gray-100); color: var(--fg-2);
            border: 0; border-radius: 8px;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
        }

        /* Category chips */
        .rent-app .mobile-view .chips-wrap {
            position: relative; padding: 0 0 8px; flex: 0 0 auto;
        }
        .rent-app .mobile-view .chips {
            display: flex; gap: 6px; padding: 0 14px;
            overflow-x: auto; overflow-y: hidden;
            -webkit-overflow-scrolling: touch; scrollbar-width: none;
            scroll-behavior: smooth; scroll-snap-type: x proximity;
        }
        .rent-app .mobile-view .chips::-webkit-scrollbar { display: none; }
        .rent-app .mobile-view .chip {
            padding: 6px 14px;
            background: var(--gray-100); border: 0;
            border-radius: var(--radius-full);
            font: 600 12.5px var(--font-sans);
            color: var(--fg-2); white-space: nowrap; cursor: pointer;
            flex: 0 0 auto; scroll-snap-align: start;
        }
        .rent-app .mobile-view .chip.active { background: var(--danger-600); color: #fff; }

        /* Catalog list inside sheet */
        .rent-app .mobile-view .catalog-list {
            flex: 1 1 auto; overflow-y: auto; -webkit-overflow-scrolling: touch; min-height: 0;
        }
        .rent-app .mobile-view .catalog-row {
            display: grid; grid-template-columns: 44px 1fr auto;
            gap: 10px; align-items: center;
            padding: 10px 14px;
            border-bottom: 1px solid var(--gray-100);
            background: #fff; cursor: pointer;
        }
        .dark .rent-app .mobile-view .catalog-row { background: var(--bg-surface); }
        .rent-app .mobile-view .catalog-row.out { opacity: 0.45; }
        .rent-app .mobile-view .catalog-row .thumb {
            width: 44px; height: 44px; border-radius: 10px;
            background: var(--gray-100);
            display: flex; align-items: center; justify-content: center; font-size: 18px;
            overflow: hidden;
        }
        .rent-app .mobile-view .catalog-row .thumb img { width:100%; height:100%; object-fit:cover; display:block; }
        .rent-app .mobile-view .catalog-row .info { min-width: 0; }
        .rent-app .mobile-view .catalog-row .name {
            font-size: 13.5px; font-weight: 600; color: var(--fg-1);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .rent-app .mobile-view .catalog-row .sub {
            font-size: 11.5px; color: var(--fg-3);
            display: flex; gap: 6px; align-items: center; margin-top: 1px;
        }
        .rent-app .mobile-view .catalog-row .add-btn {
            width: 34px; height: 34px;
            background: var(--danger-600); color: #fff;
            border: 0; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
        }
        .rent-app .mobile-view .qty-stepper {
            display: inline-flex; align-items: stretch;
            height: 34px;
            border: 1px solid color-mix(in srgb, var(--danger-600) 30%, var(--border-1));
            border-radius: 999px; background: var(--danger-50); overflow: hidden;
        }
        .rent-app .mobile-view .qty-stepper .qs-btn {
            width: 32px; height: 32px; background: transparent; border: 0;
            color: var(--danger-700);
            display: flex; align-items: center; justify-content: center; cursor: pointer;
        }
        .rent-app .mobile-view .qty-stepper .qs-val {
            min-width: 22px;
            display: inline-flex; align-items: center; justify-content: center;
            font: 700 13px var(--font-sans); color: var(--danger-700);
            font-variant-numeric: tabular-nums;
        }

        /* Mobile toast */
        .rent-app .mobile-view .mtoast {
            position: fixed; left: 50%; bottom: calc(64px + env(safe-area-inset-bottom, 0px) + 130px);
            transform: translateX(-50%);
            background: var(--gray-900); color: #fff;
            padding: 10px 14px; border-radius: 12px;
            font-size: 12.5px;
            display: flex; gap: 8px; align-items: center;
            z-index: 70;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            white-space: nowrap; max-width: 90%;
        }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>

    {{-- Toast (Livewire-driven, applies to desktop only; mobile uses its own .mtoast) --}}
    <div
        x-data="{ msg: null, t: null }"
        x-on:rent-toast.window="msg = $event.detail.message; clearTimeout(t); t = setTimeout(() => msg = null, 2200)"
        x-show="msg" x-cloak class="toast" style="display:none"
    >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
        <span x-text="msg"></span>
    </div>

    {{-- ============================================================
         DESKTOP V1
         ============================================================ --}}
    <div class="desktop-view">
        <div class="topbar">
            <div class="topbar-inner">
                <div style="min-width:0; flex:1;">
                    <div class="crumbs">
                        <a href="{{ \App\Filament\Resources\Rentals\RentalResource::getUrl('index') }}">Rentals</a>
                        <span class="sep">/</span>
                        @if($record && $record->exists)
                            <a href="{{ \App\Filament\Resources\Rentals\RentalResource::getUrl('view', ['record' => $record]) }}">{{ $rental_code }}</a>
                            <span class="sep">/</span>
                            <span style="color: var(--fg-1)">Edit</span>
                        @else
                            <span style="color: var(--fg-1)">New</span>
                        @endif
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; margin-top:2px;">
                        <h1>{{ $record && $record->exists ? 'Edit '.$rental_code : 'Buat Rental Baru' }}</h1>
                        @if($record && $record->exists)
                            <span class="pill pill-{{ $currentStatus['tone'] }}">{{ $currentStatus['label'] }}</span>
                        @endif
                    </div>
                </div>
                <div class="topbar-actions">
                    @if($missingUnitsCount > 0)
                        <span class="pill pill-red" style="align-self:center;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                            <span>{{ $missingUnitsCount }} unit kosong</span>
                        </span>
                    @endif
                    <div class="kebab-wrap" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                        <button type="button" class="kebab-btn" @click="open = !open" aria-label="More actions" aria-haspopup="true" :aria-expanded="open">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
                        </button>
                        @include('livewire.admin.partials.rental-editor-kebab-menu')
                    </div>
                    <button type="button" class="btn btn-secondary" wire:click="cancel">
                        <span class="text">Cancel</span>
                    </button>
                    @php $isNewRental = ! $record || ! $record->exists; @endphp
                    <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
                        <span class="text">{{ $isNewRental ? 'Create Rental' : 'Save Changes' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="page">
            {{-- Rental info card --}}
            <div class="card">
                <div class="card-body">
                    <div class="info-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px;">
                        <div class="field" style="grid-column: span 2;">
                            <label class="label">Kode Rental</label>
                            <input class="input" readonly value="{{ $rental_code }}">
                        </div>
                        <div class="field" style="grid-column: span 2;">
                            <label class="label">Customer<span class="req">*</span></label>
                            <div class="cust-typeahead" x-data="{ open: false }" @click.outside="open = false" style="position: relative;">
                                @if($custInfo)
                                    <div style="display:flex; gap:8px; align-items:center;">
                                        <input class="input" readonly value="{{ $custInfo['name'] }}{{ $custInfo['phone'] ? ' · '.$custInfo['phone'] : '' }}" style="flex:1; background: var(--gray-50);">
                                        <button type="button" class="btn btn-secondary" style="padding:6px 10px; font-size:12px;"
                                            wire:click="$set('customer_id', null)" title="Ganti customer">Ganti</button>
                                    </div>
                                @else
                                    <input
                                        class="input"
                                        type="text"
                                        placeholder="Cari nama, email, atau telepon…"
                                        wire:model.live.debounce.250ms="customerSearch"
                                        @focus="open = true"
                                        @input="open = true">
                                    <div x-show="open" x-cloak class="cust-dropdown"
                                        style="position: absolute; top: 100%; left: 0; right: 0; margin-top: 4px; background: #fff; border: 1px solid var(--border-1, #e5e7eb); border-radius: 8px; box-shadow: 0 6px 20px rgba(0,0,0,.08); z-index: 40; max-height: 320px; overflow-y: auto;">
                                        @forelse($this->customers as $c)
                                            <button type="button"
                                                style="display:flex; flex-direction:column; align-items:flex-start; gap:2px; width:100%; padding:10px 12px; border:0; background:transparent; cursor:pointer; text-align:left; border-bottom: 1px solid var(--gray-100, #f3f4f6);"
                                                onmouseover="this.style.background='var(--gray-50, #f9fafb)'"
                                                onmouseout="this.style.background='transparent'"
                                                wire:click="selectCustomer({{ $c['id'] }})"
                                                @click="open = false">
                                                <span style="font-size:13px; font-weight:600; color: var(--fg-1, #111827);">{{ $c['name'] }}</span>
                                                <span style="font-size:11.5px; color: var(--fg-3, #6b7280); font-family: var(--font-mono);">
                                                    {{ $c['phone'] ?? '—' }}{{ $c['email'] ? ' · '.$c['email'] : '' }}
                                                </span>
                                            </button>
                                        @empty
                                            <div style="padding:14px 12px; text-align:center; color: var(--fg-3, #6b7280); font-size:12.5px;">
                                                @if(trim($customerSearch) === '')
                                                    Mulai ketik untuk mencari customer
                                                @else
                                                    Tidak ada customer cocok dengan "{{ $customerSearch }}"
                                                @endif
                                            </div>
                                        @endforelse
                                        <button type="button"
                                            style="display:flex; align-items:center; gap:8px; width:100%; padding:10px 12px; border:0; background: var(--primary-50, #eff6ff); cursor:pointer; text-align:left; color: var(--primary-700, #1d4ed8); font-weight:600; font-size:13px; border-top: 1px solid var(--primary-200, #bfdbfe);"
                                            wire:click="openNewCustomerModal"
                                            @click="open = false">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                            Buat customer baru
                                        </button>
                                    </div>
                                @endif
                            </div>
                            @if($custInfo)
                                <div class="help" style="display:flex; align-items:center; gap:8px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    @if($waLink)
                                        <a href="{{ $waLink }}" target="_blank" rel="noopener" title="Hubungi via WhatsApp"
                                           style="display:inline-flex; align-items:center; gap:5px; color: var(--success-700, #15803d); text-decoration:none; font-weight:600;">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.52 3.48A11.94 11.94 0 0 0 12.05 0C5.46 0 .12 5.34.12 11.93c0 2.1.55 4.16 1.6 5.97L0 24l6.27-1.64a11.93 11.93 0 0 0 5.78 1.47h.01c6.59 0 11.93-5.34 11.93-11.93 0-3.19-1.24-6.18-3.47-8.42ZM12.06 21.8h-.01a9.86 9.86 0 0 1-5.03-1.38l-.36-.21-3.72.98 1-3.63-.24-.37a9.86 9.86 0 0 1-1.5-5.25c0-5.45 4.43-9.88 9.88-9.88 2.64 0 5.12 1.03 6.99 2.9a9.81 9.81 0 0 1 2.89 6.98c0 5.45-4.43 9.88-9.9 9.88Zm5.42-7.41c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.47-.88-.78-1.47-1.75-1.65-2.05-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51l-.57-.01c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.49 0 1.47 1.07 2.89 1.22 3.09.15.2 2.1 3.21 5.08 4.5.71.3 1.26.49 1.69.63.71.22 1.35.19 1.86.12.57-.08 1.76-.72 2-1.42.24-.7.24-1.29.17-1.42-.07-.13-.27-.2-.57-.35Z"/></svg>
                                            {{ $custInfo['phone'] }}
                                        </a>
                                    @else
                                        <span>{{ $custInfo['phone'] ?? '—' }}</span>
                                    @endif
                                    @php $vs = $custInfo['verification_status'] ?? 'not_verified'; @endphp
                                    @if($vs === 'verified')
                                        <span class="pill pill-green" style="font-size:10px; padding:1px 7px;">Terverifikasi</span>
                                    @elseif($vs === 'pending')
                                        <span class="pill pill-amber" style="font-size:10px; padding:1px 7px;">Sedang Diverifikasi</span>
                                    @else
                                        <span class="pill pill-amber" style="font-size:10px; padding:1px 7px;">Belum Verifikasi</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="field" style="grid-column: span 2;">
                            <label class="label">Mulai<span class="req">*</span></label>
                            <input class="input" type="datetime-local" wire:model.live="start_date">
                        </div>
                        <div class="field" style="grid-column: span 2;">
                            <label class="label">Selesai<span class="req">*</span></label>
                            <input class="input" type="datetime-local" wire:model.live="end_date">
                            <div class="help" style="display:flex; align-items:center; gap:6px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                <span>Durasi: <strong style="color: var(--fg-1)">{{ $this->durationLabel }}</strong> · ditagih <strong style="color: var(--fg-1)">{{ $days }} hari</strong></span>
                            </div>
                        </div>
                        <div class="field" style="grid-column: span 2;">
                            <label class="label">Status<span class="req">*</span></label>
                            <select class="select" wire:model.live="status">
                                @foreach($this->statuses as $s)
                                    <option value="{{ $s['value'] }}">{{ $s['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Items --}}
            <div class="card" x-data="rentalItemsUi()">
                <div class="card-head">
                    <h3>Items Rental</h3>
                    <span class="count-chip">
                        <strong>{{ count($items) }}</strong> produk ·
                        <strong>{{ $totalUnits }}</strong> unit
                    </span>
                </div>

                <div class="items-toolbar">
                    <div class="search-box" x-data="{ open: false }" @click.outside="open = false">
                        <span class="icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4.3-4.3"/></svg>
                        </span>
                        <input
                            class="input" type="text"
                            placeholder="Cari produk: nama, SKU, atau kategori…"
                            wire:model.live.debounce.250ms="searchTerm"
                            @focus="open = true"
                        >
                        <div class="kbd-hint"><span class="kbd">Enter</span></div>
                        @if(trim($searchTerm) !== '')
                            <div class="search-results" x-show="open" x-cloak>
                                @forelse($this->searchResults as $r)
                                    <div class="search-result"
                                         wire:click="addFromSearch('{{ $r['composite_id'] }}')"
                                         @click="open=false">
                                        <div class="thumb">📦</div>
                                        <div>
                                            <div class="name">{{ $r['name'] }}</div>
                                            <div class="meta"><span>{{ $r['cat'] }}</span></div>
                                        </div>
                                        <span class="stock {{ $r['avail'] === 0 ? 'out' : ($r['avail'] <= 2 ? 'low' : '') }}">
                                            {{ $r['avail'] === 0 ? 'Habis' : $r['avail'].' tersedia' }}
                                        </span>
                                        <span class="price">Rp {{ number_format($r['price'], 0, ',', '.') }}/hari</span>
                                    </div>
                                @empty
                                    <div class="search-empty">Tidak ada produk yang cocok dengan "{{ $searchTerm }}"</div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                    {{-- Bug 3: removed scanMode toggle. "Scan" button now opens camera. --}}
                    <button type="button" class="btn btn-secondary" @click="$dispatch('open-qr-scanner')" title="Scan QR / barcode dengan kamera">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span>Scan</span>
                    </button>
                    <button type="button" class="btn btn-secondary" wire:click="$set('catalogOpen', true)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        <span>Catalog</span>
                    </button>
                </div>

                @if(empty($items))
                    <div style="padding:48px; text-align:center; color: var(--fg-3);">
                        <div style="font-size:32px; margin-bottom:8px;">📦</div>
                        Belum ada produk. Gunakan search di atas untuk menambah.
                    </div>
                @else
                    <div class="items-table" x-init="initDrag($el)">
                        <div class="items-head">
                            <div></div>
                            <div class="num-col">#</div>
                            <div>Produk</div>
                            <div>Stok</div>
                            <div class="qty-head">Qty</div>
                            <div class="right">Harga / hari</div>
                            <div class="right">Disc</div>
                            <div class="right">Subtotal</div>
                            <div></div>
                        </div>
                        @foreach($items as $i => $it)
                            @php
                                $assigned = count($it['unit_ids']);
                                $missing = max(0, (int) $it['quantity'] - $assigned);
                                $gross = (float) $it['daily_rate'] * (int) $it['quantity'] * $days;
                                $rowSubtotal = max(0, $gross - $gross * ((float) $it['discount'] / 100));
                                $unitLabels = collect($it['unit_ids'])->map(fn($id) => $serialMap[$id] ?? '?')->all();
                                $avail = $this->availableCount($it['product_id'], $it['variation_id']) + $assigned;
                                $rowTotal = $this->totalOwnedFor($it['product_id'], $it['variation_id']);
                                $stockCls = $avail === 0 ? 'out' : ($avail <= 2 ? 'low' : '');
                                $product = $prodMap[$it['product_id']] ?? null;
                                $variation = $it['variation_id'] ? ($varMap[$it['variation_id']] ?? null) : null;
                                $catName = optional($product?->category)->name ?? 'Other';
                                $displayName = $variation ? ($product?->name.' ('.$variation->name.')') : ($product?->name ?? '—');
                            @endphp
                            <div class="item-row" wire:key="row-{{ $it['key'] }}" data-key="{{ $it['key'] }}">
                                <div class="grip" data-drag-handle draggable="true">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg>
                                </div>
                                <div class="row-num">{{ $i + 1 }}</div>
                                <div class="prod-cell">
                                    <div class="prod-thumb">
                                        @if($product && $product->image)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($product->image) }}" alt="" loading="lazy" style="width:100%; height:100%; object-fit:cover; border-radius:inherit; display:block;">
                                        @else
                                            📦
                                        @endif
                                    </div>
                                    <div class="prod-info">
                                        <div class="prod-name">{{ $displayName }}</div>
                                        <div class="prod-meta">
                                            @if($assigned === 0)
                                                <span class="unit-inline empty">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                                                    <span>0 unit ter-assign</span>
                                                </span>
                                            @else
                                                <span class="unit-inline" title="{{ implode(', ', $unitLabels) }}">
                                                    <span class="unit-inline-text">{{ implode(', ', $unitLabels) }}</span>
                                                    @if($missing > 0)
                                                        <span class="unit-inline-missing">+{{ $missing }} kosong</span>
                                                    @endif
                                                </span>
                                            @endif
                                            <span>·</span>
                                            <span>{{ $catName }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="stock-cell {{ $stockCls }}">
                                    <span class="dot"></span>
                                    {{ $avail === 0 ? 'Habis' : $avail.' stok' }}
                                </div>
                                <div class="qty-cell">
                                    <div class="qty-cell-inner">
                                        <input type="number" min="1" @if($rowTotal > 0) max="{{ $rowTotal }}" @endif class="cell-input"
                                            value="{{ $it['quantity'] }}"
                                            title="{{ $rowTotal > 0 ? 'Maks '.$rowTotal.' unit (total dimiliki)' : '' }}"
                                            wire:change="updateItem('{{ $it['key'] }}', 'quantity', $event.target.value)">
                                        <button type="button"
                                            class="unit-btn {{ $missing > 0 ? 'has-missing' : '' }}"
                                            wire:click="openUnitModal('{{ $it['key'] }}')"
                                            title="{{ $missing > 0 ? $missing.' unit belum ter-assign' : 'Kelola unit / serial' }}">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h10"/><circle cx="20" cy="18" r="1.5" fill="currentColor" stroke="none"/></svg>
                                            @if($missing > 0)<span class="unit-btn-badge">{{ $missing }}</span>@endif
                                        </button>
                                    </div>
                                </div>
                                <div class="price-cell">
                                    <div class="cell-input-wrap">
                                        <span class="unit">Rp</span>
                                        <input type="number" min="0" class="cell-input"
                                            value="{{ $it['daily_rate'] }}"
                                            wire:change="updateItem('{{ $it['key'] }}', 'daily_rate', $event.target.value)">
                                    </div>
                                </div>
                                <div class="disc-cell">
                                    <div class="cell-input-wrap">
                                        <input type="number" min="0" max="100" class="cell-input"
                                            value="{{ $it['discount'] }}"
                                            wire:change="updateItem('{{ $it['key'] }}', 'discount', $event.target.value)">
                                        <span class="unit">%</span>
                                    </div>
                                </div>
                                <div class="subtotal-cell">Rp {{ number_format($rowSubtotal, 0, ',', '.') }}</div>
                                <div class="row-actions" x-data="{ open: false }" @click.outside="open = false" style="position: relative;">
                                    @if($this->canTransfer && ($assigned > 0 || $missing > 0))
                                        <button type="button" class="btn-icon" title="Move / Swap / Pull unit antar rental"
                                            @click="open = !open">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                                        </button>
                                        <div x-show="open" x-cloak x-transition.opacity
                                            style="position: absolute; right: 0; top: 100%; margin-top: 4px; background: var(--bg-1, #fff); border: 1px solid var(--border-1, #e5e7eb); border-radius: 8px; box-shadow: 0 6px 16px rgba(0,0,0,.08); z-index: 30; min-width: 200px; padding: 4px;">
                                            @if($missing > 0)
                                                <button type="button"
                                                    style="display:flex; align-items:center; gap:8px; width:100%; padding:8px 10px; border:0; background:transparent; cursor:pointer; font-size:13px; border-radius:6px; text-align:left; color: var(--success-700, #15803d); font-weight:600;"
                                                    onmouseover="this.style.background='var(--success-50, #f0fdf4)'"
                                                    onmouseout="this.style.background='transparent'"
                                                    wire:click="openPullModal('{{ $it['key'] }}')"
                                                    @click="open = false">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                                                    Tarik dari rental lain ({{ $missing }})
                                                </button>
                                                @if($assigned > 0)
                                                    <div style="height:1px; background: var(--border-1, #e5e7eb); margin: 4px 2px;"></div>
                                                @endif
                                            @endif
                                            @if($assigned > 0)
                                                <button type="button"
                                                    style="display:flex; align-items:center; gap:8px; width:100%; padding:8px 10px; border:0; background:transparent; cursor:pointer; font-size:13px; border-radius:6px; text-align:left;"
                                                    onmouseover="this.style.background='var(--gray-50, #f9fafb)'"
                                                    onmouseout="this.style.background='transparent'"
                                                    wire:click="openTransferForRow('{{ $it['key'] }}', 'move')"
                                                    @click="open = false">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                                    Move ke rental lain
                                                </button>
                                                <button type="button"
                                                    style="display:flex; align-items:center; gap:8px; width:100%; padding:8px 10px; border:0; background:transparent; cursor:pointer; font-size:13px; border-radius:6px; text-align:left;"
                                                    onmouseover="this.style.background='var(--gray-50, #f9fafb)'"
                                                    onmouseout="this.style.background='transparent'"
                                                    wire:click="openTransferForRow('{{ $it['key'] }}', 'swap')"
                                                    @click="open = false">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                                                    Swap dengan rental lain
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                    <button type="button" class="btn-icon" title="Hapus"
                                        @click="$dispatch('confirm-remove-item', { key: '{{ $it['key'] }}', name: @js($displayName) })">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M6 6l1 14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-14"/></svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Totals / Notes / Deposit --}}
            <div class="totals-grid">
                <div class="card">
                    <div class="card-head"><h3>Catatan</h3></div>
                    <div class="card-body">
                        <textarea class="input" rows="6"
                            style="resize: vertical; min-height: 120px; font-family: inherit;"
                            placeholder="Catatan internal untuk rental ini…"
                            wire:model.blur="notes"></textarea>
                    </div>
                </div>

                <div style="display:flex; flex-direction:column; gap:20px;">
                    <div class="card">
                        <div class="card-head"><h3>Ringkasan</h3></div>
                        <div class="card-body" style="padding:8px 20px 16px;">
                            <div class="totals-row">
                                <span class="lbl">Subtotal ({{ count($items) }} item × {{ $days }} hari)</span>
                                <span class="val">Rp {{ number_format($totals['subtotal'], 0, ',', '.') }}</span>
                            </div>
                            @php($_dailyOpts = $this->dailyDiscountOptions)
                            @php($_dateOpts = $this->datePromotionOptions)
                            @php($_couponOpts = $this->couponOptions)
                            @if(count($_dailyOpts) || count($_dateOpts) || count($_couponOpts))
                                <div class="totals-row" style="flex-direction:column; align-items:stretch; gap:6px; padding-bottom:10px;">
                                    <span class="lbl" style="font-weight:600;">Diskon dari Promosi</span>
                                    @if(count($_dailyOpts))
                                        <select wire:model.live="daily_discount_id" style="width:100%; padding:7px 8px; border:1px solid var(--gray-300); border-radius:8px; background:#fff; font-size:13px;">
                                            <option value="">— Diskon Harian (opsional) —</option>
                                            @foreach($_dailyOpts as $id => $label)
                                                <option value="{{ $id }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @if(count($_dateOpts))
                                        <select wire:model.live="date_promotion_id" style="width:100%; padding:7px 8px; border:1px solid var(--gray-300); border-radius:8px; background:#fff; font-size:13px;">
                                            <option value="">— Promo Tanggal (opsional) —</option>
                                            @foreach($_dateOpts as $id => $label)
                                                <option value="{{ $id }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @if(count($_couponOpts))
                                        <select wire:model.live="discount_id" style="width:100%; padding:7px 8px; border:1px solid var(--gray-300); border-radius:8px; background:#fff; font-size:13px;">
                                            <option value="">— Kupon (opsional, ganti diskon manual) —</option>
                                            @foreach($_couponOpts as $id => $label)
                                                <option value="{{ $id }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                            @endif
                            @foreach($this->promoBreakdown as $line)
                                <div class="totals-row">
                                    <span class="lbl">{{ $line['label'] }}</span>
                                    <span class="val neg">− Rp {{ number_format($line['amount'], 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                            @if($discount_id)
                                <div class="totals-row">
                                    <span class="lbl">Kupon{{ $discount_code ? ' ('.$discount_code.')' : '' }}</span>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span class="val neg">− Rp {{ number_format($this->appliedDiscounts['coupon_amount'], 0, ',', '.') }}</span>
                                        <button type="button" wire:click="$set('discount_id', null)" title="Hapus kupon"
                                            style="border:none; background:var(--gray-100); color:var(--fg-2); width:22px; height:22px; border-radius:6px; cursor:pointer; line-height:1;">×</button>
                                    </div>
                                </div>
                            @else
                                <div class="totals-row">
                                    <span class="lbl">
                                        Diskon Manual
                                        @if($discount_type === 'percent' && $discount > 0)
                                            <span style="color: var(--fg-3); font-weight: 400;">({{ rtrim(rtrim(number_format($discount, 2), '0'), '.') }}%)</span>
                                        @endif
                                    </span>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <div class="cell-input-wrap" style="width:130px; background:#fff;">
                                            <span class="unit">{{ $discount_type === 'percent' ? '%' : 'Rp' }}</span>
                                            <input type="number" min="0" {{ $discount_type === 'percent' ? 'max=100' : '' }} step="{{ $discount_type === 'percent' ? '0.01' : '1' }}" class="cell-input" wire:model.live.debounce.400ms="discount">
                                        </div>
                                        <button type="button"
                                            class="toggle-type-btn {{ $discount_type === 'percent' ? 'active' : '' }}"
                                            wire:click="toggleDiscountType"
                                            title="{{ $discount_type === 'percent' ? 'Ganti ke Fixed (Rp)' : 'Ganti ke Persen (%)' }}">
                                            @if($discount_type === 'percent')
                                                {{-- show $ icon, click to switch to fixed --}}
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                            @else
                                                {{-- show % icon, click to switch to percent --}}
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                                            @endif
                                        </button>
                                    </div>
                                </div>
                            @endif
                            @if(! $discount_id && $totals['discount_amount'] > 0)
                                <div class="totals-row">
                                    <span class="lbl" style="padding-left: 16px; color: var(--fg-3); font-size: 12.5px;">↳ potongan</span>
                                    <span class="val neg">− Rp {{ number_format($totals['discount_amount'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                            @if($totals['net_subtotal'] < $totals['subtotal'])
                                <div class="totals-row">
                                    <span class="lbl">Setelah diskon</span>
                                    <span class="val">Rp {{ number_format($totals['net_subtotal'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                            @if($totals['ppn_amount'] > 0)
                                <div class="totals-row">
                                    <span class="lbl">PPN ({{ rtrim(rtrim(number_format($totals['ppn_rate'], 2), '0'), '.') }}%)</span>
                                    <span class="val">+ Rp {{ number_format($totals['ppn_amount'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                            @if($totals['deposit_amount'] > 0)
                                <div class="totals-row">
                                    <span class="lbl">
                                        Security Deposit
                                        @if($deposit_type === 'percent')
                                            <span style="color: var(--fg-3); font-weight: 400;">({{ rtrim(rtrim(number_format($deposit, 2), '0'), '.') }}%)</span>
                                        @endif
                                    </span>
                                    <span class="val">+ Rp {{ number_format($totals['deposit_amount'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                            <div class="totals-row grand">
                                <span class="lbl"><strong>Total</strong></span>
                                <span class="val">Rp {{ number_format($totals['total'], 0, ',', '.') }}</span>
                            </div>
                            @if($down_payment_amount > 0)
                                <div class="totals-row">
                                    <span class="lbl">DP dibayar</span>
                                    <span class="val">− Rp {{ number_format($down_payment_amount, 0, ',', '.') }}</span>
                                </div>
                                <div class="totals-row">
                                    <span class="lbl" style="font-weight: 600;">Sisa pelunasan</span>
                                    <span class="val" style="color: var(--danger-700);">Rp {{ number_format(max(0, $totals['total'] - $down_payment_amount), 0, ',', '.') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-head"><h3>Deposit & DP</h3></div>
                        <div class="card-body">
                            <div class="field" style="margin-bottom:14px;">
                                <label class="label">Security Deposit</label>
                                <div style="display:flex; align-items:stretch; gap:6px;">
                                    <div class="input-prefix-wrap" style="flex:1;">
                                        <span class="prefix">{{ $deposit_type === 'percent' ? '%' : 'Rp' }}</span>
                                        <input type="number" min="0" {{ $deposit_type === 'percent' ? 'max=100' : '' }} step="{{ $deposit_type === 'percent' ? '0.01' : '1' }}" class="input" wire:model.live.debounce.400ms="deposit">
                                    </div>
                                    <button type="button"
                                        class="toggle-type-btn {{ $deposit_type === 'percent' ? 'active' : '' }}"
                                        style="height: auto; align-self: stretch;"
                                        wire:click="toggleDepositType"
                                        title="{{ $deposit_type === 'percent' ? 'Ganti ke Fixed (Rp)' : 'Ganti ke Persen (%)' }}">
                                        @if($deposit_type === 'percent')
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                        @else
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                                        @endif
                                    </button>
                                </div>
                                <div class="help">
                                    Jaminan yang ditahan saat pickup
                                    @if($deposit_type === 'percent' && $totals['deposit_amount'] > 0)
                                        · <strong style="color: var(--fg-1);">Rp {{ number_format($totals['deposit_amount'], 0, ',', '.') }}</strong>
                                    @endif
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Down Payment (DP)</label>
                                <div class="input-prefix-wrap">
                                    <span class="prefix">Rp</span>
                                    <input type="number" min="0" class="input" wire:model.live.debounce.400ms="down_payment_amount">
                                </div>
                                <div class="help" style="display:flex; justify-content:space-between;">
                                    <span>Pembayaran muka</span>
                                    <span style="color: var(--fg-1); font-weight:600;">
                                        Sisa: Rp {{ number_format(max(0, $totals['total'] - $down_payment_amount), 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($record && $record->exists)
                @include('filament.resources.rentals.partials.activity-log', ['rental' => $record])
            @endif
        </div>
    </div>

    {{-- ============================================================
         MOBILE — Rental Edit "Opsi B" redesign
         Item rows = card layout (name + ⋯ action sheet, unit chip, qty,
         money), bottom = edit action bar (Tambah produk + Simpan).
         ============================================================ --}}
    <style>
        /* ----- Item card ----- */
        .rent-app .mobile-view .item {
            display: grid; grid-template-columns: 18px minmax(0, 1fr); column-gap: 8px;
            padding: 13px 14px 14px; border-top: 1px solid var(--gray-100);
            position: relative; background: #fff;
        }
        .dark .rent-app .mobile-view .item { background: var(--bg-surface); }
        .rent-app .mobile-view .item:first-child { border-top: 0; }
        .rent-app .mobile-view .item.dragging { opacity: .4; }
        .rent-app .mobile-view .item.warn-row { background: color-mix(in srgb, var(--warning-50) 45%, #fff); }
        .dark .rent-app .mobile-view .item.warn-row { background: color-mix(in srgb, var(--warning-50) 16%, var(--bg-surface)); }
        .rent-app .mobile-view .item-grip {
            grid-column: 1; grid-row: 1 / -1; align-self: flex-start; margin-top: 2px;
            color: var(--fg-4); display: flex; cursor: grab; touch-action: none;
        }
        .rent-app .mobile-view .item-grip:active { cursor: grabbing; }
        .rent-app .mobile-view .item > *:not(.item-grip) { grid-column: 2; }

        .rent-app .mobile-view .item-top { display: flex; align-items: flex-start; gap: 8px; }
        .rent-app .mobile-view .item-name {
            flex: 1; min-width: 0; font-size: 14px; font-weight: 700; line-height: 1.35; color: var(--fg-1);
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .rent-app .mobile-view .item-more {
            width: 32px; height: 32px; border-radius: 9px; border: 1px solid var(--border-1);
            background: #fff; color: var(--fg-3); display: grid; place-items: center; flex: none; cursor: pointer;
        }
        .dark .rent-app .mobile-view .item-more { background: var(--bg-surface); }
        .rent-app .mobile-view .item-more:active { background: var(--gray-100); }

        .rent-app .mobile-view .item-sub { display: flex; align-items: center; gap: 8px; margin-top: 5px; flex-wrap: wrap; }
        .rent-app .mobile-view .item-sku { font-size: 11px; color: var(--fg-3); }
        .rent-app .mobile-view .disc-tag { font-size: 11px; font-weight: 700; color: var(--success-700); }

        .rent-app .mobile-view .item-ctrl { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 11px; }

        /* unit chip — tap = kelola unit */
        .rent-app .mobile-view .unit-chip {
            display: flex; align-items: center; gap: 6px; height: 36px; padding: 0 10px 0 11px;
            border: 1px solid var(--border-1); border-radius: 10px; background: #fff; cursor: pointer; flex: 1; min-width: 0;
        }
        .dark .rent-app .mobile-view .unit-chip { background: var(--bg-surface); }
        .rent-app .mobile-view .unit-chip svg.lead { width: 15px; height: 15px; color: var(--fg-3); flex: none; }
        .rent-app .mobile-view .unit-chip .uc-text { font-size: 12.5px; font-weight: 600; color: var(--fg-1); white-space: nowrap; flex: none; }
        .rent-app .mobile-view .unit-chip .uc-serials { font-family: var(--font-mono); font-size: 11px; color: var(--fg-3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; min-width: 0; }
        .rent-app .mobile-view .unit-chip svg.chev { width: 13px; height: 13px; color: var(--fg-4); flex: none; }
        .rent-app .mobile-view .unit-chip.warn { border-color: var(--warning-300); background: var(--warning-50); }
        .rent-app .mobile-view .unit-chip.warn .uc-text { color: var(--warning-800); }
        .rent-app .mobile-view .unit-chip.warn svg.lead { color: var(--warning-700); }
        .rent-app .mobile-view .unit-chip.empty { border-color: var(--danger-200); background: var(--danger-50); }
        .rent-app .mobile-view .unit-chip.empty .uc-text { color: var(--danger-700); }
        .rent-app .mobile-view .uc-badge { background: var(--danger-600); color: #fff; font-size: 10px; font-weight: 700; border-radius: var(--radius-full); padding: 1px 6px; flex: none; }

        /* qty stepper */
        .rent-app .mobile-view .item .qty {
            display: flex; align-items: center; border: 1px solid var(--border-1); border-radius: 10px;
            overflow: hidden; height: 36px; flex: none; background: #fff;
        }
        .dark .rent-app .mobile-view .item .qty { background: var(--bg-surface); }
        .rent-app .mobile-view .item .qty button {
            width: 38px; height: 36px; border: 0; background: #fff; color: var(--fg-1);
            font: 600 19px var(--font-sans); cursor: pointer; display: grid; place-items: center;
        }
        .dark .rent-app .mobile-view .item .qty button { background: var(--bg-surface); }
        .rent-app .mobile-view .item .qty button:active { background: var(--gray-100); }
        .rent-app .mobile-view .item .qty button:disabled { color: var(--gray-300); cursor: not-allowed; }
        .rent-app .mobile-view .item .qty input {
            width: 42px; height: 36px; border: 0; border-left: 1px solid var(--border-1); border-right: 1px solid var(--border-1);
            text-align: center; font: 700 14px var(--font-sans); color: var(--fg-1); background: transparent;
            -moz-appearance: textfield; outline: none;
        }
        .rent-app .mobile-view .item .qty input::-webkit-outer-spin-button,
        .rent-app .mobile-view .item .qty input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

        .rent-app .mobile-view .item-money { display: flex; align-items: baseline; justify-content: space-between; margin-top: 10px; }
        .rent-app .mobile-view .item-rate { font-size: 12px; color: var(--fg-3); white-space: nowrap; }
        .rent-app .mobile-view .item-rate b { color: var(--fg-2); font-weight: 700; }
        .rent-app .mobile-view .item-total { font-size: 15px; font-weight: 800; color: var(--fg-1); font-variant-numeric: tabular-nums; white-space: nowrap; }

        /* ----- Per-item action sheet (⋯) ----- */
        .rent-app .mobile-view .act-list { padding: 8px; overflow-y: auto; }
        .rent-app .mobile-view .act {
            display: flex; align-items: center; gap: 13px; width: 100%; padding: 14px 12px; border: 0;
            background: transparent; border-radius: 12px; cursor: pointer; text-align: left; font-family: var(--font-sans);
        }
        .rent-app .mobile-view .act:active { background: var(--gray-100); }
        .rent-app .mobile-view .act .ai { width: 38px; height: 38px; border-radius: 10px; background: var(--gray-100); color: var(--fg-2); display: grid; place-items: center; flex: none; }
        .rent-app .mobile-view .act .at { flex: 1; min-width: 0; }
        .rent-app .mobile-view .act .at .att { display: block; font-size: 14.5px; font-weight: 700; color: var(--fg-1); }
        .rent-app .mobile-view .act .at .ats { display: block; font-size: 12px; color: var(--fg-3); margin-top: 1px; }
        .rent-app .mobile-view .act .chev { color: var(--fg-4); margin-left: auto; display: flex; }
        .rent-app .mobile-view .act.danger .ai { background: var(--danger-50); color: var(--danger-600); }
        .rent-app .mobile-view .act.danger .att { color: var(--danger-700); }
        .rent-app .mobile-view .act-badge { margin-left: auto; background: var(--warning-100); color: var(--warning-800); font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: var(--radius-full); }
        .rent-app .mobile-view .act-div { height: 1px; background: var(--gray-100); margin: 6px 14px; }
        .rent-app .mobile-view .sheet-head .sh-sub { font-size: 12px; color: var(--fg-3); margin-top: 2px; font-weight: 500; }

        /* In edit/new rental mode the global bottom nav is hidden — the editbar
           replaces it (Opsi B: "navigasi disembunyikan sementara"). This rule only
           exists in the DOM while this page is rendered, so it's effectively scoped. */
        @media (max-width: 767px) {
            .gr-bottombar { display: none !important; }
        }

        /* ----- Edit action bar (Opsi B) ----- */
        .rent-app .mobile-view .editbar {
            position: fixed; left: 0; right: 0;
            bottom: 0;
            background: #fff; border-top: 1px solid var(--border-1);
            box-shadow: 0 -2px 10px rgba(20,24,31,.05), 0 -8px 30px rgba(20,24,31,.05);
            padding: 11px 14px calc(14px + env(safe-area-inset-bottom, 0px)); z-index: 35;
        }
        .dark .rent-app .mobile-view .editbar { background: var(--bg-surface); }
        .rent-app .mobile-view .editbar .actions { display: flex; gap: 11px; }
        .rent-app .mobile-view .editbar .btn-add {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; height: 50px;
            border-radius: 14px; border: 1.5px solid var(--border-1); background: #fff; color: var(--fg-1);
            font: 700 15px var(--font-sans); cursor: pointer;
        }
        .dark .rent-app .mobile-view .editbar .btn-add { background: var(--bg-surface); }
        .rent-app .mobile-view .editbar .btn-add svg { color: var(--danger-600); }
        .rent-app .mobile-view .editbar .btn-save {
            flex: 1.35; display: flex; align-items: center; justify-content: center; gap: 8px; height: 50px;
            border-radius: 14px; border: 0; background: var(--danger-600); color: #fff;
            font: 800 16px var(--font-sans); cursor: pointer;
            box-shadow: 0 4px 12px color-mix(in srgb, var(--danger-600) 30%, transparent);
        }
        .rent-app .mobile-view .editbar .btn-save:active { background: var(--danger-700); }
        .rent-app .mobile-view .editbar .btn-save:disabled { opacity: .6; }

        /* ----- "belum disimpan" indicator in header ----- */
        .rent-app .mobile-view .msh-unsaved { color: var(--danger-600); font-weight: 600; white-space: nowrap; }
        .rent-app .mobile-view .msh-unsaved .msh-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--danger-600); margin: 0 2px 0 4px; vertical-align: middle; }

        /* ----- Discard-changes confirm sheet ----- */
        .rent-app .mobile-view .confirm-pad { padding: 22px 18px 6px; }
        .rent-app .mobile-view .confirm-pad h4 { margin: 0 0 6px; font-size: 17px; font-weight: 800; color: var(--fg-1); }
        .rent-app .mobile-view .confirm-pad p { margin: 0; font-size: 13.5px; line-height: 1.5; color: var(--fg-2); }
        .rent-app .mobile-view .sheet-foot.confirm-foot { display: flex; gap: 11px; }
        .rent-app .mobile-view .btn-wide-line {
            flex: 1; height: 50px; border-radius: 14px; border: 1px solid var(--border-1); background: #fff; color: var(--fg-1);
            font: 700 15px var(--font-sans); cursor: pointer; display: flex; align-items: center; justify-content: center;
        }
        .dark .rent-app .mobile-view .btn-wide-line { background: var(--bg-surface); }
        .rent-app .mobile-view .btn-wide-danger {
            flex: 1; height: 50px; border-radius: 14px; border: 0; background: var(--danger-600); color: #fff;
            font: 800 15px var(--font-sans); cursor: pointer; display: flex; align-items: center; justify-content: center; text-decoration: none;
        }
        .rent-app .mobile-view .btn-wide-danger:active { background: var(--danger-700); }

        /* ===========================================================
           Shared modals → bottom sheets on mobile (Kelola unit, Transfer,
           Hapus item, Buat customer). CSS-only: markup is shared with desktop,
           so all interaction/functionality is preserved — only the presentation
           changes to match the Opsi B bottom-sheet language.
           =========================================================== */
        @media (max-width: 768px) {
            .rent-app .modal-backdrop {
                align-items: flex-end;
                padding: 0;
                background: rgba(15,18,25,.42);
            }
            .rent-app .modal {
                max-width: 100% !important;
                width: 100%;
                max-height: 88vh;
                border-radius: 22px 22px 0 0;
                box-shadow: 0 -10px 40px rgba(0,0,0,.18);
                animation: sheet-in .24s cubic-bezier(.2,.8,.2,1);
            }
            /* drag-handle grip (first flex child of the column) */
            .rent-app .modal::before {
                content: '';
                display: block; flex: 0 0 auto;
                width: 38px; height: 4px; border-radius: 99px;
                background: var(--gray-200); margin: 9px auto 1px;
            }
            .rent-app .modal-head { padding: 8px 18px 12px; }
            .rent-app .modal-head h3 { font-size: 16px; font-weight: 800; }
            /* close button → rounded gray square like the sheet style */
            .rent-app .modal-head .btn-ghost.btn-icon {
                width: 32px; height: 32px; border-radius: 9px;
                background: var(--gray-100); color: var(--fg-2);
            }
            /* footer → full-width stacked-feel action buttons */
            .rent-app .modal-foot {
                gap: 11px;
                padding: 12px 18px calc(16px + env(safe-area-inset-bottom, 0px)) !important;
                justify-content: stretch !important;
            }
            .rent-app .modal-foot .btn {
                flex: 1; height: 50px; border-radius: 14px;
                font-size: 15px; font-weight: 700; justify-content: center;
            }
        }
    </style>
    <div class="mobile-view" x-data="mobileUi()">
        <div class="mobile-subhead">
            <a href="{{ $backUrl }}" class="msh-back" aria-label="Kembali"
               @click="if ($wire.isDirty) { $event.preventDefault(); showDiscard = true; }">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            </a>
            <div class="msh-title">
                {{ $record && $record->exists ? 'Edit '.$rental_code : 'Buat Rental Baru' }}
                <span class="msh-sub">
                    @if($record && $record->exists){{ $currentStatus['label'] }}@endif
                    <span x-show="$wire.isDirty" x-cloak class="msh-unsaved">
                        @if($record && $record->exists)<span class="msh-dot"></span>@endif belum disimpan
                    </span>
                </span>
            </div>
            <div class="kebab-wrap" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" class="msh-kebab" @click="open = !open" aria-label="More actions" aria-haspopup="true" :aria-expanded="open">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
                </button>
                @include('livewire.admin.partials.rental-editor-kebab-menu')
            </div>
        </div>
        <div class="mbody">
            {{-- Customer card --}}
            <div class="msec">
                <div class="cust-row">
                    <div class="avatar">
                        {{ $custInfo ? collect(explode(' ', $custInfo['name']))->take(2)->map(fn($p) => mb_substr($p, 0, 1))->implode('') : 'NA' }}
                    </div>
                    <div class="cust-info">
                        <div class="cust-name">
                            @if($custInfo)
                                <a href="{{ \App\Filament\Resources\Customers\CustomerResource::getUrl('view', ['record' => $custInfo['id']]) }}"
                                   target="_blank" rel="noopener"
                                   style="color: var(--fg-1); text-decoration: none;"
                                   title="Lihat detail customer">{{ $custInfo['name'] }}</a>
                            @else
                                Pilih customer
                            @endif
                            @if($custInfo)
                                @php $vsM = $custInfo['verification_status'] ?? 'not_verified'; @endphp
                                @if($vsM === 'verified')
                                    <span class="pill pill-green" style="font-size:9px; padding:1px 6px;">Terverifikasi</span>
                                @elseif($vsM === 'pending')
                                    <span class="pill pill-amber" style="font-size:9px; padding:1px 6px;">Diverifikasi</span>
                                @else
                                    <span class="pill pill-amber" style="font-size:9px; padding:1px 6px;">Belum Verif.</span>
                                @endif
                            @endif
                        </div>
                        <div class="cust-meta">
                            @if($waLink)
                                <a href="{{ $waLink }}" target="_blank" rel="noopener" style="color: var(--success-700, #15803d); text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:4px;">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.52 3.48A11.94 11.94 0 0 0 12.05 0C5.46 0 .12 5.34.12 11.93c0 2.1.55 4.16 1.6 5.97L0 24l6.27-1.64a11.93 11.93 0 0 0 5.78 1.47h.01c6.59 0 11.93-5.34 11.93-11.93 0-3.19-1.24-6.18-3.47-8.42ZM12.06 21.8h-.01a9.86 9.86 0 0 1-5.03-1.38l-.36-.21-3.72.98 1-3.63-.24-.37a9.86 9.86 0 0 1-1.5-5.25c0-5.45 4.43-9.88 9.88-9.88 2.64 0 5.12 1.03 6.99 2.9a9.81 9.81 0 0 1 2.89 6.98c0 5.45-4.43 9.88-9.9 9.88Zm5.42-7.41c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.47-.88-.78-1.47-1.75-1.65-2.05-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51l-.57-.01c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.49 0 1.47 1.07 2.89 1.22 3.09.15.2 2.1 3.21 5.08 4.5.71.3 1.26.49 1.69.63.71.22 1.35.19 1.86.12.57-.08 1.76-.72 2-1.42.24-.7.24-1.29.17-1.42-.07-.13-.27-.2-.57-.35Z"/></svg>
                                    {{ $custInfo['phone'] }}
                                </a>
                            @else
                                {{ $custInfo['phone'] ?? '—' }}
                            @endif
                        </div>
                        <div class="cust-caps">
                            <span class="cap cap-code">{{ $rental_code === 'AUTO' ? 'BARU' : $rental_code }}</span>
                            @if($record && $record->exists)
                                <span class="cap pill pill-{{ $currentStatus['tone'] }}">{{ $currentStatus['label'] }}</span>
                            @endif
                        </div>
                    </div>
                    <button type="button" class="cust-action" @click="editCustomer = true">Ubah</button>
                </div>

                {{-- Inline customer/status picker (collapsed by default) --}}
                <div x-show="editCustomer" x-cloak style="padding: 0 14px 14px; border-top: 1px solid var(--gray-100);">
                    <div class="field" style="margin-top:12px;">
                        <label class="label">Customer<span class="req">*</span></label>
                        <div x-data="{ open: false }" @click.outside="open = false" style="position: relative;">
                            @if($custInfo)
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <input class="input" readonly value="{{ $custInfo['name'] }}{{ $custInfo['phone'] ? ' · '.$custInfo['phone'] : '' }}" style="flex:1; background: var(--gray-50);">
                                    <button type="button" class="btn btn-secondary" style="padding:6px 10px; font-size:12px;"
                                        wire:click="$set('customer_id', null)" title="Ganti customer">Ganti</button>
                                </div>
                            @else
                                <input class="input" type="text" placeholder="Cari nama, email, atau telepon…"
                                    wire:model.live.debounce.250ms="customerSearch"
                                    @focus="open = true" @input="open = true">
                                <div x-show="open" x-cloak
                                    style="position: absolute; top: 100%; left: 0; right: 0; margin-top: 4px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 6px 20px rgba(0,0,0,.08); z-index: 40; max-height: 280px; overflow-y: auto;">
                                    @forelse($this->customers as $c)
                                        <button type="button"
                                            style="display:flex; flex-direction:column; align-items:flex-start; gap:2px; width:100%; padding:10px 12px; border:0; background:transparent; cursor:pointer; text-align:left; border-bottom: 1px solid #f3f4f6;"
                                            wire:click="selectCustomer({{ $c['id'] }})"
                                            @click="open = false">
                                            <span style="font-size:13px; font-weight:600;">{{ $c['name'] }}</span>
                                            <span style="font-size:11.5px; color: var(--fg-3, #6b7280); font-family: var(--font-mono);">{{ $c['phone'] ?? '—' }}{{ $c['email'] ? ' · '.$c['email'] : '' }}</span>
                                        </button>
                                    @empty
                                        <div style="padding:14px 12px; text-align:center; color: var(--fg-3, #6b7280); font-size:12.5px;">
                                            @if(trim($customerSearch) === '')
                                                Mulai ketik untuk mencari customer
                                            @else
                                                Tidak ada customer cocok dengan "{{ $customerSearch }}"
                                            @endif
                                        </div>
                                    @endforelse
                                    <button type="button"
                                        style="display:flex; align-items:center; gap:8px; width:100%; padding:10px 12px; border:0; background: var(--primary-50, #eff6ff); cursor:pointer; text-align:left; color: var(--primary-700, #1d4ed8); font-weight:600; font-size:13px; border-top: 1px solid var(--primary-200, #bfdbfe);"
                                        wire:click="openNewCustomerModal" @click="open = false">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                        Buat customer baru
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="field" style="margin-top:10px;">
                        <label class="label">Status</label>
                        <select class="select" wire:model.live="status">
                            @foreach($this->statuses as $s)
                                <option value="{{ $s['value'] }}">{{ $s['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Period card --}}
            <div class="msec">
                <div class="msec-head"><h4>Periode Rental</h4></div>
                <div class="date-card">
                    <div class="date date-input-wrap">
                        <span class="date-label">Mulai</span>
                        <span class="date-val">{{ $start_date ? \Carbon\Carbon::parse($start_date)->locale('id')->translatedFormat('j M') : '—' }}</span>
                        <span class="date-time">{{ $start_date ? \Carbon\Carbon::parse($start_date)->format('H:i') : '' }}</span>
                        <input type="datetime-local" wire:model.live="start_date">
                    </div>
                    <div class="arrow">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                    </div>
                    <div class="date date-input-wrap">
                        <span class="date-label">Selesai</span>
                        <span class="date-val">{{ $end_date ? \Carbon\Carbon::parse($end_date)->locale('id')->translatedFormat('j M') : '—' }}</span>
                        <span class="date-time">{{ $end_date ? \Carbon\Carbon::parse($end_date)->format('H:i') : '' }}</span>
                        <input type="datetime-local" wire:model.live="end_date">
                    </div>
                </div>
                <div class="duration-row">
                    <span>Total durasi rental</span>
                    <strong>{{ $days }} hari</strong>
                </div>
            </div>

            {{-- Items --}}
            <div class="msec" x-data="rentalItemsUi()">
                <div class="msec-head">
                    <h4>Items ({{ count($items) }})</h4>
                    <span class="count">
                        {{ $totalUnits }} unit
                        @if($missingUnitsCount > 0)
                            <span style="color: var(--danger-600); margin-left: 8px;">· {{ $missingUnitsCount }} kosong</span>
                        @endif
                    </span>
                </div>
                @if(!empty($items))
                    <div class="reorder-hint">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg>
                        <span>Tahan & drag untuk urutkan ulang</span>
                    </div>
                @endif
                <div class="item-stack" x-init="initDrag($el)">
                    @if(empty($items))
                        <div class="empty">
                            <div class="ico">📦</div>
                            <div class="t">Belum ada produk</div>
                            <div class="s">Tap tombol <strong>+</strong> di bawah</div>
                        </div>
                    @else
                        @foreach($items as $i => $it)
                            @php
                                $assigned = count($it['unit_ids']);
                                $missing = max(0, (int) $it['quantity'] - $assigned);
                                $gross = (float) $it['daily_rate'] * (int) $it['quantity'] * $days;
                                $rowSubtotal = max(0, $gross - $gross * ((float) $it['discount'] / 100));
                                $unitLabels = collect($it['unit_ids'])->map(fn($id) => $serialMap[$id] ?? '?')->all();
                                $avail = $this->availableCount($it['product_id'], $it['variation_id']) + $assigned;
                                $rowTotal = $this->totalOwnedFor($it['product_id'], $it['variation_id']);
                                $stockCls = $avail === 0 ? 'out' : ($avail <= 2 ? 'low' : 'ok');
                                $product = $prodMap[$it['product_id']] ?? null;
                                $variation = $it['variation_id'] ? ($varMap[$it['variation_id']] ?? null) : null;
                                $displayName = $variation ? ($product?->name.' ('.$variation->name.')') : ($product?->name ?? '—');
                                $catName = $product?->category?->name ?? 'Lainnya';
                            @endphp
                            <div class="item {{ $missing > 0 ? 'warn-row' : '' }}" wire:key="mrow-{{ $it['key'] }}" data-key="{{ $it['key'] }}" x-data="{ act: false }">
                                <div class="item-grip" data-drag-handle draggable="true" aria-label="Drag untuk urutkan">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg>
                                </div>

                                <div class="item-top">
                                    <div class="item-name">{{ $displayName }}</div>
                                    <button type="button" class="item-more" @click="act = true" aria-label="Aksi lain">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
                                    </button>
                                </div>

                                <div class="item-sub">
                                    <span class="item-sku">{{ $catName }}</span>
                                    <span class="stock-pill {{ $stockCls }}">{{ $avail === 0 ? 'Habis' : $avail.' stok' }}</span>
                                    @if((float) $it['discount'] > 0)
                                        <span class="disc-tag">−{{ rtrim(rtrim(number_format((float) $it['discount'], 2), '0'), '.') }}%</span>
                                    @endif
                                </div>

                                <div class="item-ctrl">
                                    <button type="button"
                                        class="unit-chip {{ $assigned === 0 ? 'empty' : ($missing > 0 ? 'warn' : '') }}"
                                        wire:click="openUnitModal('{{ $it['key'] }}')">
                                        <svg class="lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h10"/><circle cx="20" cy="18" r="1.4" fill="currentColor" stroke="none"/></svg>
                                        <span class="uc-text">{{ $assigned === 0 ? '0 unit' : ($missing > 0 ? $assigned.' dari '.$it['quantity'] : $assigned.' unit') }}</span>
                                        @if($assigned > 0 && $missing === 0)
                                            <span class="uc-serials">· {{ implode(', ', $unitLabels) }}</span>
                                        @endif
                                        @if($missing > 0)<span class="uc-badge">{{ $missing }} kosong</span>@endif
                                        <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                    </button>
                                    <div class="qty">
                                        <button type="button" @disabled((int) $it['quantity'] <= 1)
                                            wire:click="updateItem('{{ $it['key'] }}', 'quantity', {{ max(1, (int) $it['quantity'] - 1) }})">−</button>
                                        <input type="number" min="1" @if($rowTotal > 0) max="{{ $rowTotal }}" @endif
                                            value="{{ $it['quantity'] }}"
                                            wire:change="updateItem('{{ $it['key'] }}', 'quantity', $event.target.value)">
                                        <button type="button" @disabled($rowTotal > 0 && (int) $it['quantity'] >= $rowTotal)
                                            wire:click="updateItem('{{ $it['key'] }}', 'quantity', {{ (int) $it['quantity'] + 1 }})">+</button>
                                    </div>
                                </div>

                                <div class="item-money">
                                    <span class="item-rate"><b>Rp {{ number_format($it['daily_rate'], 0, ',', '.') }}</b>/hari</span>
                                    <span class="item-total">Rp {{ number_format($rowSubtotal, 0, ',', '.') }}</span>
                                </div>

                                {{-- Per-item action sheet (⋯) --}}
                                <div class="sheet-backdrop" x-show="act" x-cloak @click="act = false" style="display:none;"></div>
                                <div class="sheet" x-show="act" x-cloak style="display:none;">
                                    <div class="grip"></div>
                                    <div class="sheet-head">
                                        <div style="flex:1; min-width:0;">
                                            <h3 style="white-space:normal;">{{ $displayName }}</h3>
                                            <div class="sh-sub">{{ $catName }}</div>
                                        </div>
                                        <button type="button" class="sheet-close" @click="act = false">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    <div class="act-list">
                                        <button type="button" class="act" @click="act = false" wire:click="openUnitModal('{{ $it['key'] }}')">
                                            <span class="ai"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h10"/><circle cx="20" cy="18" r="1.4" fill="currentColor" stroke="none"/></svg></span>
                                            <span class="at"><span class="att">Kelola unit</span><span class="ats">{{ $assigned }} dari {{ $it['quantity'] }} unit ditugaskan</span></span>
                                            @if($missing > 0)
                                                <span class="act-badge">{{ $missing }} kosong</span>
                                            @else
                                                <span class="chev"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
                                            @endif
                                        </button>
                                        @if($this->canTransfer && $missing > 0)
                                            <button type="button" class="act" @click="act = false" wire:click="openPullModal('{{ $it['key'] }}')">
                                                <span class="ai"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7 7 7-7"/></svg></span>
                                                <span class="at"><span class="att">Tarik dari rental lain</span><span class="ats">Isi {{ $missing }} unit kosong dari rental overlap</span></span>
                                                <span class="chev"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
                                            </button>
                                        @endif
                                        @if($this->canTransfer && $assigned > 0)
                                            <button type="button" class="act" @click="act = false" wire:click="openTransferForRow('{{ $it['key'] }}', 'move')">
                                                <span class="ai"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                                                <span class="at"><span class="att">Pindahkan unit (Move)</span><span class="ats">Pindah unit ke rental lain</span></span>
                                                <span class="chev"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
                                            </button>
                                            <button type="button" class="act" @click="act = false" wire:click="openTransferForRow('{{ $it['key'] }}', 'swap')">
                                                <span class="ai"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg></span>
                                                <span class="at"><span class="att">Swap dengan rental lain</span><span class="ats">Tukar unit antar dua rental</span></span>
                                                <span class="chev"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span>
                                            </button>
                                        @endif
                                        <div class="act-div"></div>
                                        <button type="button" class="act danger"
                                            @click="act = false; $dispatch('confirm-remove-item', { key: '{{ $it['key'] }}', name: @js($displayName) })">
                                            <span class="ai"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M6 6l1 14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-14"/></svg></span>
                                            <span class="at"><span class="att">Hapus item</span><span class="ats">Keluarkan produk ini dari rental</span></span>
                                        </button>
                                    </div>
                                    <div style="height: calc(10px + env(safe-area-inset-bottom, 0px));"></div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            {{-- Summary --}}
            <div class="msec">
                <div class="msec-head"><h4>Ringkasan</h4></div>
                <div class="inforow">
                    <span class="k">Subtotal</span>
                    <span class="v">Rp {{ number_format($totals['subtotal'], 0, ',', '.') }}</span>
                </div>
                @foreach($this->promoBreakdown as $line)
                    <div class="inforow">
                        <span class="k">{{ $line['label'] }}</span>
                        <span class="v" style="color: var(--danger-700);">− Rp {{ number_format($line['amount'], 0, ',', '.') }}</span>
                    </div>
                @endforeach
                @php($_mDailyOpts = $this->dailyDiscountOptions)
                @php($_mDateOpts = $this->datePromotionOptions)
                @php($_mCouponOpts = $this->couponOptions)
                @if(count($_mDailyOpts) || count($_mDateOpts) || count($_mCouponOpts))
                    <div style="padding: 8px 14px 12px; display:flex; flex-direction:column; gap:6px;">
                        <span class="k" style="font-weight:600;">Diskon dari Promosi</span>
                        @if(count($_mDailyOpts))
                            <select wire:model.live="daily_discount_id" class="input">
                                <option value="">— Diskon Harian (opsional) —</option>
                                @foreach($_mDailyOpts as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                        @if(count($_mDateOpts))
                            <select wire:model.live="date_promotion_id" class="input">
                                <option value="">— Promo Tanggal (opsional) —</option>
                                @foreach($_mDateOpts as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                        @if(count($_mCouponOpts))
                            <select wire:model.live="discount_id" class="input">
                                <option value="">— Kupon (ganti diskon manual) —</option>
                                @foreach($_mCouponOpts as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                @endif
                @if($discount_id)
                    <div class="inforow">
                        <span class="k">Kupon{{ $discount_code ? ' ('.$discount_code.')' : '' }}</span>
                        <span class="v" style="display:flex; align-items:center; gap:8px;">
                            <span style="color: var(--danger-700);">− Rp {{ number_format($this->appliedDiscounts['coupon_amount'], 0, ',', '.') }}</span>
                            <button type="button" wire:click="$set('discount_id', null)" title="Hapus kupon"
                                style="border:none; background:var(--gray-100); color:var(--fg-2); width:24px; height:24px; border-radius:6px; line-height:1;">×</button>
                        </span>
                    </div>
                @else
                    <div class="inforow" @click="openDiscount = !openDiscount">
                        <span class="k">Diskon manual</span>
                        <span class="v {{ $discount > 0 ? '' : 'muted' }}">
                            @if($discount > 0)
                                {{ $discount_type === 'percent' ? rtrim(rtrim(number_format($discount, 2), '0'), '.').'%' : 'Rp '.number_format($discount, 0, ',', '.') }}
                                @if($totals['discount_amount'] > 0 && $discount_type === 'percent')
                                    <span style="color: var(--danger-700); font-weight:600;">(− Rp {{ number_format($totals['discount_amount'], 0, ',', '.') }})</span>
                                @endif
                            @else
                                Tambah diskon
                            @endif
                        </span>
                    </div>
                    <div x-show="openDiscount" x-cloak style="padding: 0 14px 12px;">
                        <div style="display:flex; gap:6px; align-items:stretch;">
                            <div class="input-prefix-wrap" style="flex:1;">
                                <span class="prefix">{{ $discount_type === 'percent' ? '%' : 'Rp' }}</span>
                                <input type="number" min="0" {{ $discount_type === 'percent' ? 'max=100' : '' }} step="{{ $discount_type === 'percent' ? '0.01' : '1' }}" class="input" wire:model.live.debounce.400ms="discount">
                            </div>
                            <button type="button"
                                class="toggle-type-btn {{ $discount_type === 'percent' ? 'active' : '' }}"
                                style="height: auto; min-height: 40px; width: 40px; flex: 0 0 40px;"
                                wire:click="toggleDiscountType">
                                @if($discount_type === 'percent')
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                @else
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                                @endif
                            </button>
                        </div>
                    </div>
                @endif
                @if($totals['ppn_amount'] > 0)
                    <div class="inforow">
                        <span class="k">PPN ({{ rtrim(rtrim(number_format($totals['ppn_rate'], 2), '0'), '.') }}%)</span>
                        <span class="v">+ Rp {{ number_format($totals['ppn_amount'], 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="inforow" @click="openDeposit = !openDeposit">
                    <span class="k">Security deposit</span>
                    <span class="v">
                        @if($deposit_type === 'percent' && $deposit > 0)
                            {{ rtrim(rtrim(number_format($deposit, 2), '0'), '.') }}%
                            <span style="color: var(--fg-3); font-weight:500;">(Rp {{ number_format($totals['deposit_amount'], 0, ',', '.') }})</span>
                        @else
                            Rp {{ number_format($deposit, 0, ',', '.') }}
                        @endif
                    </span>
                </div>
                <div x-show="openDeposit" x-cloak style="padding: 0 14px 12px;">
                    <div style="display:flex; gap:6px; align-items:stretch;">
                        <div class="input-prefix-wrap" style="flex:1;">
                            <span class="prefix">{{ $deposit_type === 'percent' ? '%' : 'Rp' }}</span>
                            <input type="number" min="0" {{ $deposit_type === 'percent' ? 'max=100' : '' }} step="{{ $deposit_type === 'percent' ? '0.01' : '1' }}" class="input" wire:model.live.debounce.400ms="deposit">
                        </div>
                        <button type="button"
                            class="toggle-type-btn {{ $deposit_type === 'percent' ? 'active' : '' }}"
                            style="height: auto; min-height: 40px; width: 40px; flex: 0 0 40px;"
                            wire:click="toggleDepositType">
                            @if($deposit_type === 'percent')
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            @else
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                            @endif
                        </button>
                    </div>
                </div>
                <div class="inforow" style="background: var(--gray-50); font-weight: 700;">
                    <span class="k" style="color: var(--fg-1); font-weight: 700;">Total</span>
                    <span class="v" style="color: var(--danger-600); font-size: 15px;">Rp {{ number_format($totals['total'], 0, ',', '.') }}</span>
                </div>
                <div class="inforow" @click="openDp = !openDp">
                    <span class="k">Down Payment</span>
                    <span class="v">Rp {{ number_format($down_payment_amount, 0, ',', '.') }}</span>
                </div>
                <div x-show="openDp" x-cloak style="padding: 0 14px 12px;">
                    <div class="input-prefix-wrap">
                        <span class="prefix">Rp</span>
                        <input type="number" min="0" class="input" wire:model.live.debounce.400ms="down_payment_amount">
                    </div>
                </div>
                <div class="inforow" @click="openNotes = !openNotes">
                    <span class="k">Catatan</span>
                    <span class="v {{ $notes ? '' : 'muted' }}">{{ $notes ? \Illuminate\Support\Str::limit($notes, 30) : 'Tap untuk tambah' }}</span>
                </div>
                <div x-show="openNotes" x-cloak style="padding: 0 14px 12px;">
                    <textarea class="input" rows="3" wire:model.blur="notes" placeholder="Catatan internal…"></textarea>
                </div>
            </div>
        </div>

        @if($record && $record->exists)
            <div style="padding: 0 14px 16px;">
                @include('filament.resources.rentals.partials.activity-log', ['rental' => $record])
            </div>
        @endif

        {{-- Edit action bar (Opsi B) — Tambah produk + Simpan. Batal via ← di header --}}
        <div class="editbar">
            <div class="actions">
                <button type="button" class="btn-add" wire:click="$set('catalogOpen', true)">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah produk
                </button>
                <button type="button" class="btn-save" wire:click="save" wire:loading.attr="disabled">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
                    {{ (! $record || ! $record->exists) ? 'Buat Rental' : 'Simpan' }}
                </button>
            </div>
        </div>

        {{-- Discard-changes confirm sheet (muncul saat back ditekan & ada perubahan belum disimpan) --}}
        <div class="sheet-backdrop" x-show="showDiscard" x-cloak @click="showDiscard = false" style="display:none;"></div>
        <div class="sheet" x-show="showDiscard" x-cloak style="display:none;">
            <div class="grip"></div>
            <div class="confirm-pad">
                <h4>Buang perubahan?</h4>
                <p>Ada perubahan yang belum disimpan. Kalau keluar sekarang, perubahan akan hilang.</p>
            </div>
            <div class="sheet-foot confirm-foot">
                <button type="button" class="btn-wide-line" @click="showDiscard = false">Tetap di sini</button>
                <a href="{{ $backUrl }}" class="btn-wide-danger">Buang &amp; keluar</a>
            </div>
        </div>

        {{-- Mobile toast --}}
        <div
            x-data="{ msg: null, t: null }"
            x-on:rent-toast.window="msg = $event.detail.message; clearTimeout(t); t = setTimeout(() => msg = null, 1800)"
            x-show="msg" x-cloak class="mtoast" style="display:none"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
            <span x-text="msg"></span>
        </div>
    </div>

    {{-- ============================================================
         CATALOG MODAL (shared) — desktop modal-style; mobile uses bottom sheet
         ============================================================ --}}
    @if($catalogOpen)
        @php
            $cartQtyMap = [];
            foreach ($items as $__it) {
                $cartQtyMap[$__it['composite_id']] = ($cartQtyMap[$__it['composite_id']] ?? 0) + (int) $__it['quantity'];
            }
            $catalogMaxMap = [];
            foreach ($this->catalogRows as $__r) {
                $catalogMaxMap[$__r['composite_id']] = (int) $__r['total'];
            }
        @endphp
        <div x-data="catalogShared(@js($cartQtyMap), @js($catalogMaxMap))"
             wire:key="catalog-shared-wrap">
        {{-- Mobile bottom sheet --}}
        <div class="mobile-view">
            <div class="sheet-backdrop" wire:click="$set('catalogOpen', false)"></div>
            <div class="sheet" x-data="{ q: '', cat: 'All' }">
                <div class="grip"></div>
                <div class="sheet-head">
                    <h3>Tambah Produk</h3>
                    <button class="sheet-close" wire:click="$set('catalogOpen', false)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="msearch">
                    <span class="icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4.3-4.3"/></svg></span>
                    <input placeholder="Cari produk…" x-model="q">
                    <button class="scan-btn" @click="$dispatch('open-qr-scanner'); $wire.set('catalogOpen', false)" aria-label="Scan">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5v14M7 5v14M11 5v14M15 5v14M19 5v14"/></svg>
                    </button>
                </div>
                <div class="chips-wrap">
                    <div class="chips">
                        <button class="chip" :class="cat === 'All' ? 'active' : ''" @click="cat = 'All'">Semua</button>
                        @foreach($this->catalogCategories as $c)
                            <button class="chip" :class="cat === '{{ $c }}' ? 'active' : ''" @click="cat = '{{ $c }}'">{{ $c }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="catalog-list">
                    @foreach($this->catalogRows as $r)
                        @php $needle = strtolower(addslashes($r['name'])); @endphp
                        <div class="catalog-row"
                             wire:key="cat-mob-{{ $r['composite_id'] }}"
                             x-show="(cat === 'All' || cat === '{{ $r['cat'] }}') && (q === '' || '{{ $needle }}'.includes(q.toLowerCase()))">
                            <div class="thumb" @click.stop="add('{{ $r['composite_id'] }}')">
                                @if(!empty($r['image']))
                                    <img src="{{ $r['image'] }}" alt="" loading="lazy">
                                @else
                                    📦
                                @endif
                            </div>
                            <div class="info" @click.stop="add('{{ $r['composite_id'] }}')">
                                <div class="name">{{ $r['name'] }}</div>
                                <div class="sub">
                                    <span x-text="localQty['{{ $r['composite_id'] }}'] ? localQty['{{ $r['composite_id'] }}'] + ' di cart' : '{{ $r['avail'] }} stok'"></span>
                                    <span style="color: var(--gray-300)">·</span>
                                    <span>Rp {{ number_format($r['price'], 0, ',', '.') }}/hari</span>
                                </div>
                            </div>
                            <template x-if="(localQty['{{ $r['composite_id'] }}'] || 0) > 0">
                                <div class="qty-stepper">
                                    <button type="button" class="qs-btn" title="Kurangi"
                                        @click.stop="dec('{{ $r['composite_id'] }}')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M5 12h14"/></svg>
                                    </button>
                                    <span class="qs-val" x-text="localQty['{{ $r['composite_id'] }}'] || 0"></span>
                                    <button type="button" class="qs-btn" title="Tambah"
                                        @click.stop="add('{{ $r['composite_id'] }}')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                    </button>
                                </div>
                            </template>
                            <template x-if="!((localQty['{{ $r['composite_id'] }}'] || 0) > 0)">
                                <button class="add-btn" @click.stop="add('{{ $r['composite_id'] }}')" title="{{ $r['avail'] === 0 ? 'Stok habis — tetap bisa ditambah, lalu pakai Transfer untuk Move/Swap dari rental lain' : '' }}">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                </button>
                            </template>
                        </div>
                    @endforeach
                </div>
                <div class="sheet-foot" style="display:flex; align-items:center; gap:8px;">
                    <span x-show="syncing" x-cloak style="display:inline-flex; align-items:center; gap:6px; color: var(--fg-3); font-size:12px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" style="animation:spin 0.8s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                        Sinkron…
                    </span>
                    <button class="sheet-done" wire:click="$set('catalogOpen', false)" style="margin-left:auto;">Selesai</button>
                </div>
            </div>
        </div>

        {{-- Desktop modal --}}
        <div class="desktop-view">
            <div class="modal-backdrop" wire:click.self="$set('catalogOpen', false)">
                <div class="modal" style="max-width: 820px;" x-data="{ q: '', cat: 'All' }">
                    <div class="modal-head">
                        <h3>Catalog Produk</h3>
                        <button class="btn btn-ghost btn-icon" wire:click="$set('catalogOpen', false)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="search-box" style="margin-bottom:12px;">
                            <span class="icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4.3-4.3"/></svg></span>
                            <input class="input" placeholder="Filter…" x-model="q">
                        </div>
                        <div class="bulk-cat-tabs">
                            <button type="button" :class="cat === 'All' ? 'active' : ''" @click="cat = 'All'">Semua</button>
                            @foreach($this->catalogCategories as $c)
                                <button type="button" :class="cat === '{{ $c }}' ? 'active' : ''" @click="cat = '{{ $c }}'">{{ $c }}</button>
                            @endforeach
                        </div>
                        <div class="bulk-grid">
                            @foreach($this->catalogRows as $r)
                                @php $needle = strtolower(addslashes($r['name'])); @endphp
                                <div class="bulk-card"
                                    wire:key="bulk-card-{{ $r['composite_id'] }}"
                                    :class="(localQty['{{ $r['composite_id'] }}'] || 0) > 0 ? 'in-cart' : ''"
                                    x-show="(cat === 'All' || cat === '{{ $r['cat'] }}') && (q === '' || '{{ $needle }}'.includes(q.toLowerCase()))">
                                    <div class="thumb">
                                        @if(!empty($r['image']))
                                            <img src="{{ $r['image'] }}" alt="" loading="lazy">
                                        @else
                                            📦
                                        @endif
                                    </div>
                                    <div style="min-width:0; flex:1;">
                                        <div class="name">{{ $r['name'] }}</div>
                                        <div class="sub">{{ $r['avail'] }} stok</div>
                                    </div>
                                    <template x-if="(localQty['{{ $r['composite_id'] }}'] || 0) > 0">
                                        <div class="bulk-stepper">
                                            <button type="button" class="bulk-step-btn" title="Kurangi"
                                                @click.stop="dec('{{ $r['composite_id'] }}')">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M5 12h14"/></svg>
                                            </button>
                                            <span class="bulk-step-qty" x-text="localQty['{{ $r['composite_id'] }}'] || 0"></span>
                                            <button type="button" class="bulk-step-btn" title="Tambah"
                                                @click.stop="add('{{ $r['composite_id'] }}')">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                            </button>
                                        </div>
                                    </template>
                                    <template x-if="!((localQty['{{ $r['composite_id'] }}'] || 0) > 0)">
                                        <button type="button" class="bulk-add-btn" title="Tambahkan"
                                            @click.stop="add('{{ $r['composite_id'] }}')">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                        </button>
                                    </template>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-foot">
                        <span style="color: var(--fg-3); font-size:13px;">
                            <span x-show="!syncing">Klik produk untuk menambahkan ke rental</span>
                            <span x-show="syncing" x-cloak style="display:inline-flex; align-items:center; gap:6px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" style="animation:spin 0.8s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                                Menyinkronkan…
                            </span>
                        </span>
                        <button class="btn btn-secondary" wire:click="$set('catalogOpen', false)">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
        </div>{{-- /catalogShared wrapper --}}
    @endif

    {{-- ============================================================
         UNIT MANAGER MODAL
         ============================================================ --}}
    @if($unitModalOpen && $this->unitModalContext)
        @php $um = $this->unitModalContext; @endphp
        <div class="modal-backdrop" wire:click.self="closeUnitModal">
            <div class="modal unit-modal"
                 x-data="{
                    draft: @js(array_pad($um['unit_ids'], $um['qty'], null)),
                    pool: @js($um['pool']),
                    qty: {{ $um['qty'] }},
                    submit() { @this.saveUnits(this.draft.filter(v => v !== null && v !== '')); },
                    autoAssign() {
                        const used = new Set(this.draft.filter(Boolean).map(String));
                        for (let i = 0; i < this.draft.length; i++) {
                            if (this.draft[i]) continue;
                            const cand = this.pool.find(p => p.available && !used.has(String(p.id)));
                            if (cand) { this.draft[i] = cand.id; used.add(String(cand.id)); }
                        }
                    },
                    clearAll() { this.draft = this.draft.map(() => null); },
                    assigned() { return this.draft.filter(Boolean).length; }
                 }">
                <div class="modal-head">
                    <div style="min-width:0; flex:1;">
                        <h3>Kelola Unit</h3>
                        <div style="font-size:12.5px; color: var(--fg-3); display:flex; gap:6px; align-items:center; margin-top:4px;">
                            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:60%;">{{ $um['product_name'] }}</span>
                            <span style="color: var(--gray-300)">·</span>
                            <span style="font-family: var(--font-mono);">{{ $um['sku'] }}</span>
                        </div>
                    </div>
                    <button class="btn btn-ghost btn-icon" wire:click="closeUnitModal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="um-summary">
                    <span class="ums-l">Periode · {{ $um['date_label'] ?: '—' }}</span>
                    <span class="ums-r {{ $um['available_total'] < $um['qty'] ? 'warn' : 'ok' }}">{{ $um['available_total'] }} dari {{ $um['pool_total'] }} unit tersedia</span>
                </div>

                @if($um['pool_total'] === 0 || $um['available_total'] === 0)
                    <div class="unit-modal-empty">
                        <div class="t">0 unit tersedia</div>
                        <div class="s">Semua unit sudah dipinjam{{ $um['date_label'] ? ' pada periode '.$um['date_label'] : '' }}. Coba ubah tanggal atau ganti produk.</div>
                    </div>
                @else
                    @if($um['qty'] > $um['available_total'])
                        <div class="unit-modal-banner">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                            <span>Qty <strong>{{ $um['qty'] }}</strong> melebihi stok tersedia (<strong>{{ $um['available_total'] }}</strong> unit). Beberapa slot tidak bisa diisi.</span>
                        </div>
                    @endif
                    <div class="um-toolbar">
                        <span class="um-count" :class="assigned() === qty ? 'ok' : 'warn'">
                            <span x-text="assigned()"></span>/{{ $um['qty'] }} ter-assign
                        </span>
                        <div class="um-tools">
                            <button type="button" class="um-tool" @click="autoAssign()" :disabled="assigned() === qty">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15.5-6.3L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15.5 6.3L3 16"/><path d="M3 21v-5h5"/></svg>
                                <span>Auto-assign sisa</span>
                            </button>
                            <button type="button" class="um-tool danger" @click="clearAll()" :disabled="assigned() === 0">Kosongkan</button>
                        </div>
                    </div>
                    <div class="um-slots">
                        <template x-for="(val, i) in draft" :key="i">
                            <div class="um-slot" :class="!val ? 'empty' : ''">
                                <label class="um-slot-lbl">
                                    Unit #<span x-text="i + 1"></span><span class="req">*</span>
                                </label>
                                <div class="sel-wrap">
                                    <select class="sel"
                                            @change="draft[i] = $event.target.value === '' ? null : Number($event.target.value)">
                                        <option value="" :selected="draft[i] === null || draft[i] === '' || draft[i] === undefined">— Pilih unit —</option>
                                        <template x-for="p in pool" :key="p.id">
                                            <option :value="p.id"
                                                :selected="String(draft[i]) === String(p.id)"
                                                :disabled="!p.available || (draft.filter((d,j) => j !== i).map(String).includes(String(p.id)))"
                                                x-text="p.serial + (!p.available ? ' (dipinjam)' : (draft.filter((d,j) => j !== i).map(String).includes(String(p.id)) ? ' (slot lain)' : ''))"></option>
                                        </template>
                                    </select>
                                    <span class="sel-chev"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
                                </div>
                                @if($this->canTransfer)
                                    <template x-if="val">
                                        <div class="um-xfer">
                                            <span class="um-xfer-lbl">Transfer</span>
                                            <button type="button" class="um-xfer-link move" @click="$wire.openMoveModal(Number(val))">Move</button>
                                            <span class="um-xfer-dot">·</span>
                                            <button type="button" class="um-xfer-link swap" @click="$wire.openSwapModal(Number(val))">Swap</button>
                                        </div>
                                    </template>
                                    <template x-if="!val">
                                        <div class="um-xfer">
                                            <span class="um-xfer-lbl">Transfer</span>
                                            <button type="button" class="um-xfer-link pull" @click="$wire.openPullModal(@js($um['key']))">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7 7 7-7"/></svg>
                                                <span>Tarik dari rental lain</span>
                                            </button>
                                        </div>
                                    </template>
                                @endif
                            </div>
                        </template>
                    </div>
                @endif

                <div class="modal-foot">
                    <button type="button" class="btn btn-secondary" wire:click="closeUnitModal">Batal</button>
                    <button type="button" class="btn btn-primary" @click="submit()">Simpan unit</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================
         TRANSFER (MOVE / SWAP) MODAL
         ============================================================ --}}
    @if($transferModalOpen)
        @php $tx = $this->transferContext; @endphp
        @if($tx)
            <div class="modal-backdrop" wire:click.self="closeTransferModal" style="z-index: 60;">
                <div class="modal" style="max-width: 560px;">
                    @php $chevSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>'; @endphp
                    <div class="modal-head">
                        <h3 style="display:flex; align-items:center; gap:8px;">
                            @if($tx['mode'] === 'move')
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                Pindahkan Unit (Move)
                            @elseif($tx['mode'] === 'pull')
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7 7 7-7"/></svg>
                                Tarik dari Rental Lain
                            @else
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                                Swap dengan Rental Lain
                            @endif
                        </h3>
                        <button class="btn btn-ghost btn-icon" wire:click="closeTransferModal" type="button">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </div>

                    {{-- Segmented mode toggle — switch Move / Swap / Tarik in place --}}
                    <div class="tx-seg">
                        <button type="button" class="{{ $tx['mode'] === 'move' ? 'on' : '' }}" wire:click="switchTransferMode('move')">Move</button>
                        <button type="button" class="{{ $tx['mode'] === 'swap' ? 'on' : '' }}" wire:click="switchTransferMode('swap')">Swap</button>
                        <button type="button" class="{{ $tx['mode'] === 'pull' ? 'on' : '' }}" wire:click="switchTransferMode('pull')">Tarik</button>
                    </div>

                    <div class="tx-note">
                        @if($tx['mode'] === 'pull')
                            Pilih unit dari rental lain yang overlap (produk sama, status quotation/confirmed/late_pickup) untuk mengisi slot kosong.
                        @elseif($tx['mode'] === 'swap')
                            Tukar unit yang ditugaskan di rental ini dengan unit di rental tujuan. Total kedua rental dihitung ulang otomatis.
                        @else
                            Pindahkan unit yang ditugaskan ke rental tujuan. Total kedua rental dihitung ulang otomatis.
                        @endif
                    </div>

                    <div class="tx-fields">
                        @if($tx['mode'] === 'pull')
                            {{-- PULL mode: pick a RentalItem from another (conflicting) rental --}}
                            <div>
                                <label class="tx-fld-lbl">Unit dari rental lain<span class="req">*</span></label>
                                @if(empty($tx['pull_candidates']))
                                    <div class="tx-empty-hint">Tidak ada unit produk ini di rental lain yang overlap periode rental ini (status quotation/confirmed/late_pickup).</div>
                                @else
                                    <div class="tx-combo" wire:key="txc-pull"
                                         x-data="txCombo({ options: @js(collect($tx['pull_candidates'])->map(fn($pc) => ['value' => (string) $pc['item_id'], 'label' => $pc['label']])->values()), prop: 'transferTargetItemId', live: false, placeholder: '— Pilih unit untuk ditarik —' })"
                                         @click.outside="open=false" x-on:keydown.escape="open=false">
                                        <button type="button" class="tx-combo-trigger" @click="toggle()">
                                            <span class="lbl" :class="{ 'ph': !currentLabel }" x-text="currentLabel || placeholder"></span>
                                            <span class="sel-chev">{!! $chevSvg !!}</span>
                                        </button>
                                        <div class="tx-combo-pop" x-show="open" x-cloak>
                                            <input type="text" class="tx-combo-search" x-ref="search" x-model="query" placeholder="Cari unit / rental…" @keydown.escape.stop="open=false">
                                            <div class="tx-combo-list">
                                                <template x-for="o in filtered" :key="o.value">
                                                    <button type="button" class="tx-combo-opt" :class="{ 'on': String(o.value) === current }" @click="choose(o)" x-text="o.label"></button>
                                                </template>
                                                <div class="tx-combo-empty" x-show="filtered.length === 0">Tidak ada hasil</div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            {{-- Unit dari rental ini (Move / Swap) --}}
                            <div>
                                <label class="tx-fld-lbl">Unit dari rental ini<span class="req">*</span></label>
                                @if(empty($tx['pickable_units']))
                                    <div class="tx-empty-hint">Belum ada unit yang ditugaskan untuk dipindah / di-swap.</div>
                                @else
                                    <div class="tx-combo" wire:key="txc-unit"
                                         x-data="txCombo({ options: @js(collect($tx['pickable_units'])->map(fn($u) => ['value' => (string) $u['id'], 'label' => $u['serial']])->values()), prop: 'transferUnitId', live: true, placeholder: '— Pilih unit —' })"
                                         @click.outside="open=false" x-on:keydown.escape="open=false">
                                        <button type="button" class="tx-combo-trigger" @click="toggle()">
                                            <span class="lbl" :class="{ 'ph': !currentLabel }" x-text="currentLabel || placeholder"></span>
                                            <span class="sel-chev">{!! $chevSvg !!}</span>
                                        </button>
                                        <div class="tx-combo-pop" x-show="open" x-cloak>
                                            <input type="text" class="tx-combo-search" x-ref="search" x-model="query" placeholder="Cari serial…" @keydown.escape.stop="open=false">
                                            <div class="tx-combo-list">
                                                <template x-for="o in filtered" :key="o.value">
                                                    <button type="button" class="tx-combo-opt" :class="{ 'on': String(o.value) === current }" @click="choose(o)" x-text="o.label"></button>
                                                </template>
                                                <div class="tx-combo-empty" x-show="filtered.length === 0">Tidak ada hasil</div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            @if($tx['mode'] === 'swap')
                                <div class="tx-swap-arrow">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                                    <span>ditukar dengan</span>
                                </div>
                            @endif

                            {{-- Rental tujuan --}}
                            <div>
                                <label class="tx-fld-lbl">Rental tujuan<span class="req">*</span></label>
                                <div class="tx-combo" wire:key="txc-target"
                                     x-data="txCombo({ options: @js(collect($tx['targets'])->map(fn($t) => ['value' => (string) $t['id'], 'label' => $t['label']])->values()), prop: 'transferTargetRentalId', live: true, placeholder: '— Pilih rental —' })"
                                     @click.outside="open=false" x-on:keydown.escape="open=false">
                                    <button type="button" class="tx-combo-trigger" @click="toggle()">
                                        <span class="lbl" :class="{ 'ph': !currentLabel }" x-text="currentLabel || placeholder"></span>
                                        <span class="sel-chev">{!! $chevSvg !!}</span>
                                    </button>
                                    <div class="tx-combo-pop" x-show="open" x-cloak>
                                        <input type="text" class="tx-combo-search" x-ref="search" x-model="query" placeholder="Cari kode / nama / tanggal…" @keydown.escape.stop="open=false">
                                        <div class="tx-combo-list">
                                            <template x-for="o in filtered" :key="o.value">
                                                <button type="button" class="tx-combo-opt" :class="{ 'on': String(o.value) === current }" @click="choose(o)" x-text="o.label"></button>
                                            </template>
                                            <div class="tx-combo-empty" x-show="filtered.length === 0">Tidak ada hasil</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if($tx['mode'] === 'swap')
                                <div>
                                    <label class="tx-fld-lbl">Tukar dengan unit<span class="req">*</span></label>
                                    @if(empty($tx['target_items']))
                                        <div class="sel-wrap">
                                            <select class="sel sans" disabled>
                                                <option value="">Pilih rental tujuan dulu</option>
                                            </select>
                                            <span class="sel-chev">{!! $chevSvg !!}</span>
                                        </div>
                                    @else
                                        <div class="tx-combo" wire:key="txc-swapunit-{{ $transferTargetRentalId }}"
                                             x-data="txCombo({ options: @js(collect($tx['target_items'])->map(fn($ti) => ['value' => (string) $ti['id'], 'label' => $ti['label']])->values()), prop: 'transferTargetItemId', live: false, placeholder: '— Pilih unit lawan —' })"
                                             @click.outside="open=false" x-on:keydown.escape="open=false">
                                            <button type="button" class="tx-combo-trigger" @click="toggle()">
                                                <span class="lbl" :class="{ 'ph': !currentLabel }" x-text="currentLabel || placeholder"></span>
                                                <span class="sel-chev">{!! $chevSvg !!}</span>
                                            </button>
                                            <div class="tx-combo-pop" x-show="open" x-cloak>
                                                <input type="text" class="tx-combo-search" x-ref="search" x-model="query" placeholder="Cari produk / serial…" @keydown.escape.stop="open=false">
                                                <div class="tx-combo-list">
                                                    <template x-for="o in filtered" :key="o.value">
                                                        <button type="button" class="tx-combo-opt" :class="{ 'on': String(o.value) === current }" @click="choose(o)" x-text="o.label"></button>
                                                    </template>
                                                    <div class="tx-combo-empty" x-show="filtered.length === 0">Tidak ada hasil</div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>

                    <div class="modal-foot" style="display:flex; gap:8px; justify-content:flex-end; padding: 12px 20px; border-top: 1px solid var(--border-1, #e5e7eb);">
                        <button type="button" class="btn btn-secondary" wire:click="closeTransferModal">Batal</button>
                        <button type="button" class="btn btn-primary" wire:click="confirmTransfer" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="confirmTransfer">
                                {{ $tx['mode'] === 'move' ? 'Pindahkan' : ($tx['mode'] === 'pull' ? 'Tarik' : 'Tukar') }}
                            </span>
                            <span wire:loading wire:target="confirmTransfer">Memproses…</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- ============================================================
         QR Scanner overlay
         ============================================================ --}}
    <div
        x-data="qrScannerCtl()"
        x-on:open-qr-scanner.window="open()"
        x-show="visible" x-cloak
        class="qr-overlay" style="display: none;"
        @click.self="close()"
    >
        <div class="qr-modal">
            <div class="qr-modal-head">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Scan QR / Barcode
                </h3>
                <button class="btn btn-ghost btn-icon" @click="close()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="qr-reader"></div>
            <p class="qr-hint" x-show="!message" x-cloak>Arahkan kamera ke QR / barcode SKU produk atau serial unit</p>
            <p class="qr-hint" x-show="message" x-text="message" style="color: var(--success-700); font-weight:600;" x-cloak></p>
        </div>
    </div>

    {{-- ============================================================
         CREATE CUSTOMER MODAL
         ============================================================ --}}
    @if($newCustomerModalOpen)
        <div class="modal-backdrop" wire:click.self="closeNewCustomerModal" style="z-index: 70;">
            <div class="modal" style="max-width: 480px;">
                <div class="modal-head">
                    <h3 style="display:flex; align-items:center; gap:8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                        Buat Customer Baru
                    </h3>
                    <button class="btn btn-ghost btn-icon" wire:click="closeNewCustomerModal" type="button">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="modal-body" style="padding: 16px 20px; display:flex; flex-direction:column; gap: 14px;">
                    <div class="field">
                        <label class="label">Nama<span class="req">*</span></label>
                        <input class="input" type="text" wire:model="newCustomerName" placeholder="Nama lengkap" autofocus>
                        @error('newCustomerName') <div class="help" style="color: var(--danger-700, #b91c1c);">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label class="label">Email</label>
                        <input class="input" type="email" wire:model="newCustomerEmail" placeholder="customer@example.com (opsional)">
                        @error('newCustomerEmail') <div class="help" style="color: var(--danger-700, #b91c1c);">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label class="label">Telepon</label>
                        <input class="input" type="tel" wire:model="newCustomerPhone" placeholder="08xxxxxxxxxx (opsional)">
                        @error('newCustomerPhone') <div class="help" style="color: var(--danger-700, #b91c1c);">{{ $message }}</div> @enderror
                    </div>
                    <div style="font-size: 11.5px; color: var(--fg-3, #6b7280); padding: 8px 10px; background: var(--gray-50, #f9fafb); border: 1px solid var(--border-1, #e5e7eb); border-radius: 8px;">
                        Customer dibuat tanpa password. Mereka dapat di-set password kemudian dari halaman User. Customer akan otomatis dipilih untuk rental ini setelah dibuat.
                    </div>
                </div>
                <div class="modal-foot" style="display:flex; gap:8px; justify-content:flex-end; padding: 12px 20px; border-top: 1px solid var(--border-1, #e5e7eb);">
                    <button type="button" class="btn btn-secondary" wire:click="closeNewCustomerModal">Batal</button>
                    <button type="button" class="btn btn-primary" wire:click="createCustomer" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="createCustomer">Buat Customer</span>
                        <span wire:loading wire:target="createCustomer">Memproses…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================
         CUSTOM CONFIRM-REMOVE MODAL (replaces native confirm)
         ============================================================ --}}
    <div x-data="{
            open: false,
            key: null,
            name: '',
        }"
         @confirm-remove-item.window="key = $event.detail.key; name = $event.detail.name || ''; open = true;"
         @keydown.escape.window="open = false">
        <template x-if="open">
            <div class="modal-backdrop" @click.self="open = false" x-cloak>
                <div class="modal" style="max-width: 420px;" @keydown.enter="$wire.removeItem(key); open = false;">
                    <div class="modal-body" style="padding: 24px 24px 8px;">
                        <div style="display:flex; gap:14px; align-items:flex-start;">
                            <div style="flex:0 0 44px; width:44px; height:44px; border-radius:50%; background: var(--danger-50, #fee2e2); color: var(--danger-600, #dc2626); display:flex; align-items:center; justify-content:center;">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M6 6l1 14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-14"/></svg>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <h3 style="margin:0 0 6px; font-size:16px; font-weight:600; color: var(--fg-1);">Hapus item dari rental?</h3>
                                <p style="margin:0; font-size:13.5px; color: var(--fg-3); line-height:1.5;">
                                    <span x-text="name || 'Item ini'"></span> akan dihapus dari daftar. Tindakan ini tidak bisa dibatalkan setelah rental disimpan.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-foot" style="display:flex; gap:8px; justify-content:flex-end; padding:16px 24px 20px;">
                        <button type="button" class="btn btn-secondary" @click="open = false">Batal</button>
                        <button type="button" class="btn"
                                style="background: var(--danger-600, #dc2626); color:#fff; border-color: var(--danger-600, #dc2626);"
                                @click="$wire.removeItem(key); open = false;">
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>

<script>
    function mobileUi() {
        return {
            editCustomer: false,
            openDiscount: false,
            openDeposit: false,
            openDp: false,
            openNotes: false,
            showDiscard: false,
        };
    }

    function rentalItemsUi() {
        return {
            dragKey: null,
            initDrag(root) {
                root.addEventListener('dragstart', (e) => {
                    const handle = e.target.closest('[data-drag-handle]');
                    if (!handle) { e.preventDefault(); return; }
                    const row = handle.closest('[data-key]');
                    if (!row) return;
                    this.dragKey = row.dataset.key;
                    e.dataTransfer.effectAllowed = 'move';
                    try { e.dataTransfer.setData('text/plain', this.dragKey); } catch (_) {}
                    row.classList.add('dragging');
                });
                root.addEventListener('dragover', (e) => {
                    if (this.dragKey == null) return;
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    const row = e.target.closest('[data-key]');
                    if (!row || row.dataset.key === this.dragKey) return;
                    const rect = row.getBoundingClientRect();
                    const before = (e.clientY - rect.top) < rect.height / 2;
                    const dragged = root.querySelector('[data-key="' + CSS.escape(this.dragKey) + '"]');
                    if (!dragged) return;
                    if (before) row.parentNode.insertBefore(dragged, row);
                    else row.parentNode.insertBefore(dragged, row.nextSibling);
                });
                root.addEventListener('dragend', () => {
                    const rows = root.querySelectorAll('[data-key]');
                    rows.forEach(r => r.classList.remove('dragging'));
                    const order = Array.from(rows).map(r => r.dataset.key);
                    this.dragKey = null;
                    @this.call('reorder', order);
                });
                root.addEventListener('drop', (e) => { e.preventDefault(); });
            },
        };
    }

    // Optimistic-UI store for the catalog popup. localQty mirrors the server-side
    // cart map but updates immediately on click so the user sees feedback while the
    // Livewire round-trip is in flight. The server response (which re-renders the
    // blade with a fresh seed) takes over on the next sync — see Alpine.morph rules.
    function catalogShared(seed, maxMap) {
        return {
            localQty: Object.assign({}, seed || {}),
            // Per-composite ceiling = total units physically owned. Mirrors the server
            // cap in addProduct()/updateItem so the stepper stops at the owned total.
            maxQty: Object.assign({}, maxMap || {}),
            // Coalesce rapid clicks into a single Livewire call per composite_id to
            // avoid the editor recomputing avail map N times in a row.
            _pendingAdd: {},
            _flushTimer: null,
            // Spinner state is Alpine-owned (not wire:loading) because the catalog
            // modal — including the loading spans — is re-rendered by the catalogRows
            // computed on every request. A morphed-in wire:loading element gets the
            // server's default (visible) state and never gets re-hidden, so the spinner
            // sticks on forever. Tracking in-flight calls here survives the morph
            // because this x-data root carries a wire:key.
            _inflight: 0,
            get syncing() { return this._inflight > 0; },
            _track(promise) {
                this._inflight++;
                Promise.resolve(promise).finally(() => {
                    if (this._inflight > 0) this._inflight--;
                });
            },
            add(compositeId) {
                const max = this.maxQty[compositeId];
                const cur = this.localQty[compositeId] || 0;
                if (max !== undefined && cur >= max) {
                    this.$dispatch('rent-toast', { message: `Maksimal ${max} unit (total dimiliki)` });
                    return;
                }
                this.localQty[compositeId] = cur + 1;
                this._pendingAdd[compositeId] = (this._pendingAdd[compositeId] || 0) + 1;
                this._scheduleFlush();
            },
            dec(compositeId) {
                const cur = this.localQty[compositeId] || 0;
                if (cur <= 0) return;
                this.localQty[compositeId] = cur - 1;
                // Decrement is server-authoritative (no batching) so unit_ids stays consistent.
                this._flushPending();
                this._track(@this.call('decrementByComposite', compositeId));
            },
            _scheduleFlush() {
                if (this._flushTimer) clearTimeout(this._flushTimer);
                this._flushTimer = setTimeout(() => this._flushPending(), 120);
            },
            _flushPending() {
                if (this._flushTimer) { clearTimeout(this._flushTimer); this._flushTimer = null; }
                const pending = this._pendingAdd;
                this._pendingAdd = {};
                const batch = [];
                for (const cid in pending) {
                    batch.push({ id: cid, qty: pending[cid] });
                }
                if (batch.length === 0) return;
                this._track(@this.call('addProductsBatch', batch));
            },
        };
    }

    function qrScannerCtl() {
        return {
            visible: false,
            scanner: null,
            message: null,
            open() {
                this.visible = true;
                this.message = null;
                this.$nextTick(() => this.boot());
            },
            close() {
                this.visible = false;
                this.message = null;
                if (this.scanner) {
                    try { this.scanner.clear(); } catch (_) {}
                    this.scanner = null;
                }
            },
            boot() {
                const start = () => {
                    if (this.scanner) return;
                    const w = window.innerWidth > 600 ? 280 : 220;
                    this.scanner = new Html5QrcodeScanner('qr-reader', {
                        fps: 10, qrbox: { width: w, height: w },
                        aspectRatio: 1.0, showTorchButtonIfSupported: true
                    }, false);
                    this.scanner.render(
                        (decodedText) => this.onScan(decodedText),
                        () => {}
                    );
                };
                if (typeof Html5QrcodeScanner === 'undefined') {
                    const s = document.createElement('script');
                    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js';
                    s.onload = start;
                    document.head.appendChild(s);
                } else {
                    start();
                }
            },
            onScan(text) {
                this.message = 'Terdeteksi: ' + text;
                @this.call('handleScanned', text);
                setTimeout(() => this.close(), 700);
            }
        };
    }

    /* Searchable combobox for transfer modal selects (Move / Swap / Tarik).
       config: { options:[{value,label}], prop:'wireProp', live:bool, placeholder } */
    function txCombo(config) {
        return {
            open: false,
            query: '',
            options: config.options || [],
            prop: config.prop,
            live: config.live !== false,
            placeholder: config.placeholder || 'Pilih…',
            get current() {
                const v = this.$wire.get(this.prop);
                return v === null || v === undefined ? '' : String(v);
            },
            get currentLabel() {
                const o = this.options.find(x => String(x.value) === this.current);
                return o ? o.label : '';
            },
            get filtered() {
                const q = this.query.trim().toLowerCase();
                if (!q) return this.options;
                return this.options.filter(o => String(o.label).toLowerCase().includes(q));
            },
            toggle() {
                this.open = !this.open;
                if (this.open) {
                    this.query = '';
                    this.$nextTick(() => this.$refs.search && this.$refs.search.focus());
                }
            },
            choose(o) {
                this.$wire.set(this.prop, o.value, this.live);
                this.query = '';
                this.open = false;
            },
        };
    }
</script>
