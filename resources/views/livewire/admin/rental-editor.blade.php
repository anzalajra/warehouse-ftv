@php
    $days = $this->days;
    $totals = $this->totals;
    $custInfo = $this->customerInfo;
    $currentStatus = $this->currentStatus;
    $missingUnitsCount = 0;
    foreach ($items as $__it) {
        $missingUnitsCount += max(0, (int) $__it['quantity'] - count($__it['unit_ids']));
    }
@endphp

<div class="rent-app" wire:ignore.self>
    {{-- ====================================================
         Bind design primary to admin Filament theme color
         (design uses --danger-* as its primary).
         ==================================================== --}}
    <style>
        .rent-app {
            /* Pull the admin's primary palette into the design tokens */
            --danger-50:  rgb(var(--fi-color-primary-50));
            --danger-100: rgb(var(--fi-color-primary-100));
            --danger-200: rgb(var(--fi-color-primary-200));
            --danger-300: rgb(var(--fi-color-primary-300));
            --danger-400: rgb(var(--fi-color-primary-400));
            --danger-500: rgb(var(--fi-color-primary-500));
            --danger-600: rgb(var(--fi-color-primary-600));
            --danger-700: rgb(var(--fi-color-primary-700));
            --primary-50: rgb(var(--fi-color-primary-50));
            --primary-500: rgb(var(--fi-color-primary-500));
            --primary-600: rgb(var(--fi-color-primary-600));

            /* Neutrals */
            --gray-50:#f9fafb; --gray-100:#f3f4f6; --gray-200:#e5e7eb; --gray-300:#d1d5db;
            --gray-400:#9ca3af; --gray-500:#6b7280; --gray-600:#4b5563; --gray-700:#374151;
            --gray-800:#1f2937; --gray-900:#111827;

            --success-50:#f0fdf4; --success-100:#dcfce7; --success-600:#16a34a; --success-700:#15803d;
            --warning-50:#fefce8; --warning-100:#fef9c3; --warning-300:#fcd34d; --warning-600:#ca8a04; --warning-700:#a16207; --warning-800:#854d0e;

            --bg-page: var(--gray-50);
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
            --bg-page: #09090b;
            --gray-50:#1f1f23; --gray-100:#27272a; --gray-200:#3f3f46; --gray-300:#52525b;
            --fg-1:#fafafa; --fg-2:#e4e4e7; --fg-3:#a1a1aa; --fg-4:#71717a;
            --border-1:#27272a;
        }

        .rent-app * { box-sizing: border-box; }
        .rent-app { font-family: var(--font-sans); font-size: var(--text-sm); color: var(--fg-1); line-height: 1.5; }

        /* === Buttons === */
        .rent-app .btn { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border-radius:var(--radius-lg); font-size:var(--text-sm); font-weight:600; line-height:1; border:1px solid transparent; cursor:pointer; transition: background var(--dur) var(--ease), border-color var(--dur) var(--ease); white-space:nowrap; }
        .rent-app .btn-icon { padding:8px; }
        .rent-app .btn-primary { background: var(--danger-600); color:#fff; }
        .rent-app .btn-primary:hover { background: var(--danger-700); }
        .rent-app .btn-secondary { background:#fff; color: var(--fg-1); border-color: var(--border-1); }
        .dark .rent-app .btn-secondary { background: var(--bg-surface); color: var(--fg-1); }
        .rent-app .btn-secondary:hover { background: var(--gray-50); }
        .rent-app .btn-ghost { background: transparent; color: var(--fg-2); }
        .rent-app .btn-ghost:hover { background: var(--gray-100); color: var(--fg-1); }
        .rent-app .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        /* === Inputs === */
        .rent-app .field { display:block; }
        .rent-app .label { display:block; font-size:12.5px; font-weight:600; color:var(--fg-2); margin-bottom:6px; }
        .rent-app .label .req { color:var(--danger-600); margin-left:2px; }
        .rent-app .input, .rent-app .select { width:100%; padding:8px 12px; border:1px solid var(--border-1); border-radius:var(--radius-lg); background:#fff; color:var(--fg-1); font:400 14px var(--font-sans); outline:none; transition: border-color var(--dur), box-shadow var(--dur); }
        .dark .rent-app .input, .dark .rent-app .select { background: var(--bg-surface); }
        .rent-app .input:focus, .rent-app .select:focus { border-color: var(--danger-500); box-shadow:0 0 0 3px rgb(var(--fi-color-primary-500) / 0.18); }
        .rent-app .input[readonly] { background: var(--gray-50); color: var(--fg-3); }
        .rent-app .input-prefix-wrap { display:flex; align-items:stretch; border:1px solid var(--border-1); border-radius:var(--radius-lg); background:#fff; overflow:hidden; }
        .dark .rent-app .input-prefix-wrap { background: var(--bg-surface); }
        .rent-app .input-prefix-wrap:focus-within { border-color: var(--danger-500); box-shadow:0 0 0 3px rgb(var(--fi-color-primary-500) / 0.18); }
        .rent-app .input-prefix-wrap .prefix { padding:8px 12px; background:var(--gray-50); color:var(--fg-3); border-right:1px solid var(--border-1); display:flex; align-items:center; font-size:14px; }
        .rent-app .input-prefix-wrap .input { border:0; border-radius:0; box-shadow:none; flex:1; }
        .rent-app .help { font-size:12.5px; color: var(--fg-3); margin-top:6px; }

        /* === Cards === */
        .rent-app .card { background: var(--bg-surface); border:1px solid var(--border-1); border-radius: var(--radius-lg); }
        .rent-app .card-head { padding:14px 20px; border-bottom:1px solid var(--border-1); display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .rent-app .card-head h3 { margin:0; font-size: var(--text-base); font-weight:600; }
        .rent-app .card-body { padding:20px; }

        /* === Pills === */
        .rent-app .pill { display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:var(--radius-full); font-size:12px; font-weight:600; line-height:1.4; }
        .rent-app .pill::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
        .rent-app .pill-blue { background:#e0f2fe; color:#075985; }
        .rent-app .pill-green { background: var(--success-100); color: var(--success-700); }
        .rent-app .pill-amber { background: var(--warning-100); color: var(--warning-800); }
        .rent-app .pill-red { background: var(--danger-100); color: var(--danger-700); }
        .rent-app .pill-gray { background: var(--gray-100); color: var(--fg-2); }

        /* === Top sticky bar === */
        .rent-app .topbar { position: sticky; top: 0; z-index: 30; background: rgba(255,255,255,0.92); backdrop-filter: blur(8px); border-bottom: 1px solid var(--border-1); margin: -1.5rem -1.5rem 0; }
        .dark .rent-app .topbar { background: rgba(24,24,27,0.92); }
        .rent-app .topbar-inner { padding:12px 24px; display:flex; align-items:center; gap:16px; }
        .rent-app .crumbs { display:flex; align-items:center; gap:6px; min-width:0; font-size:12.5px; color: var(--fg-3); }
        .rent-app .crumbs a { color: var(--fg-3); text-decoration:none; }
        .rent-app .crumbs a:hover { color: var(--fg-1); }
        .rent-app .crumbs .sep { color: var(--gray-300); }
        .rent-app .topbar h1 { margin:0; font-size:18px; font-weight:700; color: var(--fg-1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-variant-numeric: tabular-nums; }
        .rent-app .topbar-actions { margin-left: auto; display:flex; align-items:center; gap:8px; }

        /* === Page === */
        .rent-app .page { padding:20px 24px 64px; display:flex; flex-direction:column; gap:20px; }

        /* === Toolbar === */
        .rent-app .items-toolbar { display:flex; align-items:center; gap:8px; padding:12px 16px; background: var(--gray-50); border-bottom:1px solid var(--border-1); flex-wrap: wrap; }
        .rent-app .search-box { flex:1 1 320px; position: relative; display: flex; align-items: center; }
        .rent-app .search-box .icon { position:absolute; left:10px; color:var(--fg-3); pointer-events:none; display:flex; }
        .rent-app .search-box .input { padding-left: 36px; padding-right: 80px; background:#fff; }
        .dark .rent-app .search-box .input { background: var(--bg-surface); }
        .rent-app .search-box .kbd-hint { position:absolute; right:8px; display:flex; gap:4px; pointer-events:none; }
        .rent-app .kbd { font-family: var(--font-mono); font-size:11px; padding:2px 6px; border-radius:4px; background:#fff; color: var(--fg-3); border:1px solid var(--border-1); line-height:1; height:18px; display:inline-flex; align-items:center; }

        /* search dropdown */
        .rent-app .search-results { position: absolute; top: calc(100% + 6px); left: 0; right: 0; background:#fff; border:1px solid var(--border-1); border-radius: var(--radius-lg); box-shadow: var(--shadow-dropdown); max-height: 360px; overflow:auto; z-index: 20; }
        .dark .rent-app .search-results { background: var(--bg-surface); }
        .rent-app .search-result { display:grid; grid-template-columns: 36px 1fr auto auto; gap:12px; align-items:center; padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--gray-100); }
        .rent-app .search-result:last-child { border-bottom: 0; }
        .rent-app .search-result:hover { background: var(--primary-50); }
        .rent-app .search-result .thumb { width:36px; height:36px; border-radius:6px; background: var(--gray-100); display:flex; align-items:center; justify-content:center; font-size:16px; }
        .rent-app .search-result .name { font-size:13.5px; font-weight:500; color: var(--fg-1); }
        .rent-app .search-result .meta { font-size:11.5px; color: var(--fg-3); font-family: var(--font-mono); }
        .rent-app .search-result .stock { font-size:11.5px; font-weight:600; padding:2px 8px; border-radius: var(--radius-full); background: var(--success-50); color: var(--success-700); white-space: nowrap; }
        .rent-app .search-result .stock.low { background: var(--warning-50); color: var(--warning-800); }
        .rent-app .search-result .stock.out { background: var(--danger-50); color: var(--danger-700); }
        .rent-app .search-result .price { font-size:12px; color: var(--fg-2); font-variant-numeric: tabular-nums; }
        .rent-app .search-empty { padding:24px; text-align:center; color: var(--fg-3); font-size:13px; }

        /* === Items table === */
        .rent-app .items-table { width:100%; }
        .rent-app .items-head, .rent-app .item-row { display:grid; grid-template-columns: 24px 28px minmax(0, 2.2fr) 90px 130px 110px 64px 110px 36px; align-items:center; gap:12px; padding:8px 16px; }
        .rent-app .items-head { background: var(--gray-50); border-bottom: 1px solid var(--border-1); font-size:11.5px; font-weight:600; color: var(--fg-3); text-transform: uppercase; letter-spacing:.04em; }
        .rent-app .items-head .num-col, .rent-app .items-head .right { text-align: right; }
        .rent-app .item-row { border-bottom: 1px solid var(--gray-100); background: var(--bg-surface); transition: background var(--dur-fast); }
        .rent-app .item-row:hover { background: var(--gray-50); }
        .rent-app .grip { cursor: grab; color: var(--fg-4); display:flex; align-items:center; justify-content:center; opacity:0; transition: opacity var(--dur); width:24px; height:24px; border-radius:4px; }
        .rent-app .grip:hover { background: var(--gray-200); color: var(--fg-2); }
        .rent-app .item-row:hover .grip { opacity:1; }
        .rent-app .item-row.dragging { opacity:.4; }
        .rent-app .row-num { color: var(--fg-3); font-size:12px; font-variant-numeric: tabular-nums; text-align:right; }
        .rent-app .prod-cell { display:flex; align-items:center; gap:10px; min-width:0; }
        .rent-app .prod-thumb { width:32px; height:32px; border-radius:6px; flex:0 0 32px; background: var(--gray-100); display:flex; align-items:center; justify-content:center; font-size:14px; }
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
        .rent-app .cell-input:focus { background:#fff; border-color: var(--danger-500); box-shadow: 0 0 0 3px rgb(var(--fi-color-primary-500) / 0.18); }
        .dark .rent-app .cell-input:focus { background: var(--bg-surface); }
        .rent-app .cell-input-wrap { display:flex; align-items:center; gap:2px; border:1px solid transparent; border-radius:6px; }
        .rent-app .cell-input-wrap:hover { background:#fff; border-color: var(--border-1); }
        .dark .rent-app .cell-input-wrap:hover { background: var(--bg-surface); }
        .rent-app .cell-input-wrap:focus-within { background:#fff; border-color: var(--danger-500); box-shadow: 0 0 0 3px rgb(var(--fi-color-primary-500) / 0.18); }
        .dark .rent-app .cell-input-wrap:focus-within { background: var(--bg-surface); }
        .rent-app .cell-input-wrap .unit { font-size:11px; color: var(--fg-3); padding-right:6px; }
        .rent-app .cell-input-wrap .cell-input { border:0; box-shadow:none; padding-right:2px; }
        .rent-app .subtotal-cell { font-size:13.5px; font-weight:600; text-align:right; font-variant-numeric: tabular-nums; padding-right:8px; }
        .rent-app .row-actions { display:flex; justify-content:center; }
        .rent-app .row-actions .btn-icon { color: var(--fg-3); padding:4px; border-radius:4px; opacity:0; background: transparent; border:0; cursor:pointer; transition: opacity var(--dur), color var(--dur), background var(--dur); }
        .rent-app .item-row:hover .row-actions .btn-icon { opacity:1; }
        .rent-app .row-actions .btn-icon:hover { color: var(--danger-600); background: var(--danger-50); }

        /* qty cell + unit button */
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

        /* === Totals === */
        .rent-app .totals-grid { display:grid; grid-template-columns: 1fr 360px; gap:20px; }
        .rent-app .totals-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom: 1px solid var(--gray-100); font-size:13.5px; }
        .rent-app .totals-row:last-child { border-bottom:0; }
        .rent-app .totals-row .lbl { color: var(--fg-2); }
        .rent-app .totals-row .val { font-weight:600; font-variant-numeric: tabular-nums; }
        .rent-app .totals-row.grand { border-top: 2px solid var(--gray-200); margin-top:4px; padding-top:14px; font-size:16px; }
        .rent-app .totals-row.grand .val { color: var(--danger-600); font-size:18px; font-weight:700; }
        .rent-app .count-chip { display:inline-flex; align-items:center; gap:6px; background:#fff; border:1px solid var(--border-1); padding:6px 10px; border-radius:var(--radius-full); font-size:12.5px; color: var(--fg-2); }
        .dark .rent-app .count-chip { background: var(--bg-surface); }
        .rent-app .count-chip strong { color: var(--fg-1); font-weight:700; }

        /* === Scanner banner === */
        .rent-app .scanner-banner { display:flex; align-items:center; gap:10px; padding:10px 16px; background: linear-gradient(135deg, #fef9c3 0%, #fef3c7 100%); border-bottom: 1px solid var(--warning-300); font-size:13px; color: var(--warning-800); font-weight:500; }
        .rent-app .scanner-banner .dot-blink { width:8px; height:8px; border-radius:50%; background: var(--danger-600); animation: scan-pulse 1.2s ease-in-out infinite; }
        @keyframes scan-pulse { 0%,100% { opacity:1; transform: scale(1); } 50% { opacity:.4; transform: scale(.7); } }

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
        .rent-app .bulk-card .thumb { width:36px; height:36px; border-radius:6px; background: var(--gray-100); display:flex; align-items:center; justify-content:center; font-size:14px; flex:0 0 36px; }
        .rent-app .bulk-card .name { font-size:12.5px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rent-app .bulk-card .sub { font-size:11px; color: var(--fg-3); font-family: var(--font-mono); }
        .rent-app .bulk-cat-tabs { display:flex; gap:4px; flex-wrap: wrap; margin-bottom:12px; }
        .rent-app .bulk-cat-tabs button { padding:5px 10px; font-size:12px; font-weight:500; background: var(--gray-100); border:0; border-radius: var(--radius-full); color: var(--fg-2); cursor:pointer; }
        .rent-app .bulk-cat-tabs button.active { background: var(--danger-600); color:#fff; }

        /* === Unit modal === */
        .rent-app .unit-modal { max-width: 520px; }
        .rent-app .unit-modal-summary { padding:12px 20px; background: var(--gray-50); border-bottom:1px solid var(--border-1); display:flex; gap:24px; flex-wrap:wrap; }
        .rent-app .unit-modal-summary-row { display:flex; flex-direction:column; gap:2px; }
        .rent-app .unit-modal-summary-row .lbl { font-size:10.5px; font-weight:600; color: var(--fg-3); text-transform: uppercase; letter-spacing:.06em; }
        .rent-app .unit-modal-summary-row .val { font-size:13px; font-weight:600; color: var(--fg-1); font-variant-numeric: tabular-nums; }
        .rent-app .unit-modal-summary-row .val.warn { color: var(--warning-800); }
        .rent-app .unit-modal-summary-row .val.ok { color: var(--success-700); }
        .rent-app .unit-modal-summary-row .val.danger { color: var(--danger-700); }
        .rent-app .unit-modal-banner { margin: 12px 20px 0; padding:10px 12px; border-radius:10px; display:flex; align-items:flex-start; gap:8px; font-size:12.5px; line-height:1.4; background: var(--warning-50); color: var(--warning-800); border: 1px solid var(--warning-100); }
        .rent-app .unit-modal-slots { flex:1 1 auto; overflow-y:auto; padding:8px 20px; display:flex; flex-direction:column; gap:12px; }
        .rent-app .unit-slot { display:flex; flex-direction:column; gap:6px; }
        .rent-app .unit-slot-label { font-size:12.5px; font-weight:600; color: var(--fg-2); }
        .rent-app .unit-slot-label .req { color: var(--danger-600); margin-left:2px; }
        .rent-app .unit-slot-select { width:100%; padding:9px 12px; font:500 13.5px var(--font-mono); color: var(--fg-1); background:#fff; border: 1.5px solid var(--border-1); border-radius:8px; outline:none; cursor:pointer; }
        .dark .rent-app .unit-slot-select { background: var(--bg-surface); }
        .rent-app .unit-slot-select:focus { border-color: var(--danger-500); box-shadow: 0 0 0 3px rgb(var(--fi-color-primary-500) / 0.18); }
        .rent-app .unit-slot.empty .unit-slot-select { border-color: var(--warning-300); background-color: var(--warning-50); }
        .rent-app .unit-modal-empty { padding:24px; text-align:center; color: var(--danger-700); }
        .rent-app .unit-modal-empty .t { font-size:15px; font-weight:700; margin-bottom:4px; }
        .rent-app .unit-modal-empty .s { font-size:12.5px; }

        /* ================== MOBILE BREAKPOINT ================== */
        .rent-app .mtopbar, .rent-app .mdock, .rent-app .fab { display: none; }

        @media (max-width: 768px) {
            .rent-app { background: #f3f1ed; }
            .dark .rent-app { background: var(--bg-page); }

            /* Hide desktop top bar; show mobile one inside the items card area */
            .rent-app .topbar { display: none; }
            .rent-app .page { padding: 12px 12px 220px; gap:12px; }

            /* Info grid stack */
            .rent-app .info-grid { grid-template-columns: 1fr !important; }
            .rent-app .totals-grid { grid-template-columns: 1fr !important; }

            /* Items table → stacked cards */
            .rent-app .items-head { display:none; }
            .rent-app .item-row { display:grid; grid-template-columns: 24px 1fr auto; grid-template-areas: 'grip prod del' '. meta meta' 'stock stock stock' 'qty price disc' '. . sub'; gap:6px 10px; padding:10px 12px; }
            .rent-app .item-row .grip { grid-area: grip; opacity:1; }
            .rent-app .item-row .prod-cell { grid-area: prod; }
            .rent-app .item-row .stock-cell { grid-area: stock; }
            .rent-app .item-row .qty-cell { grid-area: qty; }
            .rent-app .item-row .price-cell { grid-area: price; }
            .rent-app .item-row .disc-cell { grid-area: disc; }
            .rent-app .item-row .subtotal-cell { grid-area: sub; text-align: right; }
            .rent-app .item-row .row-actions { grid-area: del; }
            .rent-app .item-row .row-actions .btn-icon { opacity:1; }
            .rent-app .item-row .cell-input, .rent-app .item-row .cell-input-wrap { background: var(--gray-50); border:1px solid var(--border-1); }
            .rent-app .item-row .cell-input { text-align:left; padding:6px 8px; }
            .rent-app .item-row .qty-cell::before { content: 'Qty'; }
            .rent-app .item-row .price-cell::before { content: 'Harga'; }
            .rent-app .item-row .disc-cell::before { content: 'Disc'; }
            .rent-app .item-row .qty-cell::before, .rent-app .item-row .price-cell::before, .rent-app .item-row .disc-cell::before { display:block; font-size:10.5px; color: var(--fg-3); text-transform: uppercase; letter-spacing:.04em; margin-bottom:2px; font-weight:600; }

            /* Hide desktop toolbar's bulk button, leave search + scan only */
            .rent-app .items-toolbar { padding: 10px 12px; }

            /* Sticky bottom dock with Save/Cancel */
            .rent-app .mdock { display:flex; position: fixed; left:0; right:0; bottom:0; background: var(--bg-surface); border-top: 1px solid var(--border-1); padding: 10px 14px calc(10px + env(safe-area-inset-bottom)); box-shadow: 0 -8px 24px rgba(0,0,0,.08); z-index: 40; flex-direction: column; gap: 10px; }
            .rent-app .mdock-summary { display:flex; align-items:flex-end; justify-content:space-between; gap:12px; }
            .rent-app .mdock-meta { display:flex; gap:6px; align-items:center; font-size:11px; color: var(--fg-3); font-weight:500; }
            .rent-app .mdock-total { display:flex; align-items:baseline; gap:8px; }
            .rent-app .mdock-total-label { font-size:11px; color: var(--fg-3); font-weight:700; text-transform: uppercase; letter-spacing:.05em; }
            .rent-app .mdock-total-val { font-size:20px; font-weight:800; color: var(--fg-1); font-variant-numeric: tabular-nums; line-height:1.1; }
            .rent-app .mdock-actions { display:grid; grid-template-columns: 1fr 2fr; gap:8px; }
            .rent-app .mdock .btn-cancel { height:46px; background:#fff; color: var(--fg-2); border:1px solid var(--border-1); border-radius:12px; font:600 14px var(--font-sans); display:inline-flex; align-items:center; justify-content:center; cursor:pointer; }
            .dark .rent-app .mdock .btn-cancel { background: var(--bg-surface); }
            .rent-app .mdock .btn-save { height:46px; background: var(--danger-600); color:#fff; border:0; border-radius:12px; font:700 14px var(--font-sans); display:inline-flex; align-items:center; justify-content:center; gap:6px; cursor:pointer; }

            /* FAB */
            .rent-app .fab { display: flex; position: fixed; right: 16px; bottom: calc(env(safe-area-inset-bottom) + 130px); width:56px; height:56px; background: var(--danger-600); color:#fff; border:0; border-radius:50%; box-shadow: 0 10px 24px rgba(var(--fi-color-primary-600), 0.4), 0 2px 4px rgba(0,0,0,0.1); align-items:center; justify-content:center; cursor:pointer; z-index: 30; padding: 0; }
        }

        /* === Toast === */
        .rent-app .toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background: var(--gray-900); color:#fff; padding:10px 16px; border-radius: var(--radius-lg); font-size:13px; z-index: 200; display:flex; gap:8px; align-items:center; box-shadow: var(--shadow-lg); animation: toast-in .18s var(--ease); }
        @keyframes toast-in { from { opacity:0; transform: translate(-50%, 8px); } to { opacity:1; transform: translate(-50%, 0); } }

        /* === QR scanner overlay === */
        .rent-app .qr-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 250; display:flex; align-items:center; justify-content:center; padding: 24px; }
        .rent-app .qr-modal { background:#fff; border-radius: 16px; width: 100%; max-width: 480px; padding: 20px; }
        .dark .rent-app .qr-modal { background: var(--bg-surface); }
        .rent-app .qr-modal-head { display:flex; align-items:center; justify-content:space-between; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid var(--border-1); }
        .rent-app .qr-modal-head h3 { margin:0; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; color: var(--fg-1); }
        .rent-app .qr-modal #qr-reader { width: 100%; min-height: 280px; }
        .rent-app .qr-modal .qr-hint { margin-top: 12px; font-size: 12.5px; color: var(--fg-3); text-align: center; }
    </style>

    {{-- Toast (Livewire-driven) --}}
    <div
        x-data="{ msg: null, t: null }"
        x-on:rent-toast.window="msg = $event.detail.message; clearTimeout(t); t = setTimeout(() => msg = null, 2200)"
        x-show="msg"
        x-cloak
        class="toast"
        style="display:none"
    >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
        <span x-text="msg"></span>
    </div>

    {{-- ================= Sticky desktop top bar ================= --}}
    <div class="topbar">
        <div class="topbar-inner">
            <div style="min-width:0; flex:1;">
                <div class="crumbs">
                    <a href="{{ \App\Filament\Resources\Rentals\RentalResource::getUrl('index') }}">Rentals</a>
                    <span class="sep">/</span>
                    <span style="color: var(--fg-1)">{{ $rental_code === 'AUTO' ? 'Baru' : $rental_code }}</span>
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
                <button type="button" class="btn btn-secondary" wire:click="cancel">
                    <span class="text">Cancel</span>
                </button>
                <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
                    <span class="text">Save Changes</span>
                </button>
            </div>
        </div>
    </div>

    <div class="page">
        {{-- ================= Rental info card ================= --}}
        <div class="card">
            <div class="card-body">
                <div class="info-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px;">
                    <div class="field" style="grid-column: span 2;">
                        <label class="label">Kode Rental</label>
                        <input class="input" readonly value="{{ $rental_code }}">
                    </div>
                    <div class="field" style="grid-column: span 2;">
                        <label class="label">Customer<span class="req">*</span></label>
                        <select class="select" wire:model.live="customer_id">
                            <option value="">— Pilih customer —</option>
                            @foreach($this->customers as $c)
                                <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                            @endforeach
                        </select>
                        @if($custInfo)
                            <div class="help" style="display:flex; align-items:center; gap:8px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                {{ $custInfo['phone'] ?? '—' }}
                                @if($custInfo['verified'])
                                    <span class="pill pill-green" style="font-size:10px; padding:1px 7px;">Verified</span>
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

        {{-- ================= Items ================= --}}
        <div class="card" x-data="rentalItemsUi()">
            <div class="card-head">
                <h3>Items Rental</h3>
                <span class="count-chip">
                    <strong>{{ count($items) }}</strong> produk ·
                    <strong>{{ collect($items)->sum('quantity') }}</strong> unit
                </span>
            </div>

            <div class="items-toolbar">
                <div class="search-box" x-data="{ open: false }" @click.outside="open = false">
                    <span class="icon">
                        @if($scanMode)
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5v14M7 5v14M11 5v14M15 5v14M19 5v14"/></svg>
                        @else
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4.3-4.3"/></svg>
                        @endif
                    </span>
                    <input
                        class="input"
                        type="text"
                        placeholder="{{ $scanMode ? 'Scan / ketik SKU lalu Enter…' : 'Cari produk: nama, SKU, atau kategori…' }}"
                        wire:model.live.debounce.250ms="searchTerm"
                        @focus="open = true"
                        wire:keydown.enter.prevent="{{ $scanMode ? 'scanSku' : '' }}"
                    >
                    <div class="kbd-hint">
                        @if($scanMode)
                            <span class="kbd">Enter</span>
                        @else
                            <span class="kbd">Enter</span>
                        @endif
                    </div>
                    @if(! $scanMode && trim($searchTerm) !== '')
                        <div class="search-results" x-show="open" x-cloak>
                            @forelse($this->searchResults as $r)
                                <div class="search-result"
                                     wire:click="addFromSearch('{{ $r['composite_id'] }}')"
                                     @click="open=false"
                                >
                                    <div class="thumb">📦</div>
                                    <div>
                                        <div class="name">{{ $r['name'] }}</div>
                                        <div class="meta"><span class="sku">{{ $r['sku'] }}</span><span>·</span><span>{{ $r['cat'] }}</span></div>
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
                <button type="button" class="btn {{ $scanMode ? 'btn-primary' : 'btn-secondary' }}" wire:click="$toggle('scanMode')" title="Mode scan SKU">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5v14M7 5v14M11 5v14M15 5v14M19 5v14"/></svg>
                    <span>{{ $scanMode ? 'Mode Scan' : 'Scan' }}</span>
                </button>
                <button type="button" class="btn btn-secondary" @click="$dispatch('open-qr-scanner')" title="Scan QR / barcode dengan kamera">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>Kamera</span>
                </button>
                <button type="button" class="btn btn-secondary" wire:click="$set('catalogOpen', true)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    <span>Catalog</span>
                </button>
            </div>

            @if($scanMode)
                <div class="scanner-banner">
                    <span class="dot-blink"></span>
                    <span>Mode Scan aktif — fokus di search bar, ketik / scan SKU lalu tekan <span class="kbd">Enter</span></span>
                </div>
            @endif

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
                        <div class="right">Qty</div>
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
                            // pull unit labels
                            $unitLabels = \App\Models\ProductUnit::whereIn('id', $it['unit_ids'])->pluck('serial_number')->all();
                            $avail = $this->availableCount($it['product_id'], $it['variation_id']) + $assigned;
                            $stockCls = $avail === 0 ? 'out' : ($avail <= 2 ? 'low' : '');
                            $product = \App\Models\Product::find($it['product_id']);
                            $variation = $it['variation_id'] ? \App\Models\ProductVariation::find($it['variation_id']) : null;
                            $catName = optional($product?->category)->name ?? 'Other';
                            $displayName = $variation ? ($product?->name.' ('.$variation->name.')') : ($product?->name ?? '—');
                        @endphp
                        <div class="item-row" wire:key="row-{{ $it['key'] }}" data-key="{{ $it['key'] }}">
                            <div class="grip" data-drag-handle draggable="true">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg>
                            </div>
                            <div class="row-num">{{ $i + 1 }}</div>
                            <div class="prod-cell">
                                <div class="prod-thumb">📦</div>
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
                                    <input
                                        type="number" min="1" class="cell-input"
                                        value="{{ $it['quantity'] }}"
                                        wire:change="updateItem('{{ $it['key'] }}', 'quantity', $event.target.value)"
                                    >
                                    <button type="button"
                                        class="unit-btn {{ $missing > 0 ? 'has-missing' : '' }}"
                                        wire:click="openUnitModal('{{ $it['key'] }}')"
                                        title="{{ $missing > 0 ? $missing.' unit belum ter-assign' : 'Kelola unit / serial' }}"
                                    >
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
                            <div class="row-actions">
                                <button type="button" class="btn-icon" wire:click="removeItem('{{ $it['key'] }}')" title="Hapus" wire:confirm="Hapus item ini?">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M6 6l1 14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-14"/></svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ================= Totals / Notes / Deposit ================= --}}
        <div class="totals-grid">
            <div class="card">
                <div class="card-head"><h3>Catatan</h3></div>
                <div class="card-body">
                    <textarea
                        class="input"
                        rows="6"
                        style="resize: vertical; min-height: 120px; font-family: inherit;"
                        placeholder="Catatan internal untuk rental ini…"
                        wire:model.blur="notes"
                    ></textarea>
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
                        <div class="totals-row">
                            <span class="lbl">Diskon Manual</span>
                            <div class="cell-input-wrap" style="width:160px; background:#fff;">
                                <span class="unit">{{ $discount_type === 'percent' ? '%' : 'Rp' }}</span>
                                <input type="number" min="0" class="cell-input" wire:model.live.debounce.400ms="discount">
                            </div>
                        </div>
                        @if($totals['ppn_amount'] > 0)
                            <div class="totals-row">
                                <span class="lbl">PPN ({{ rtrim(rtrim(number_format($totals['ppn_rate'], 2), '0'), '.') }}%)</span>
                                <span class="val">Rp {{ number_format($totals['ppn_amount'], 0, ',', '.') }}</span>
                            </div>
                        @endif
                        <div class="totals-row grand">
                            <span class="lbl"><strong>Total</strong></span>
                            <span class="val">Rp {{ number_format($totals['total'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head"><h3>Deposit & DP</h3></div>
                    <div class="card-body">
                        <div class="field" style="margin-bottom:14px;">
                            <label class="label">Security Deposit</label>
                            <div class="input-prefix-wrap">
                                <span class="prefix">{{ $deposit_type === 'percent' ? '%' : 'Rp' }}</span>
                                <input type="number" min="0" class="input" wire:model.live.debounce.400ms="deposit">
                            </div>
                            <div class="help">Jaminan yang ditahan saat pickup</div>
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
    </div>

    {{-- ================= Mobile sticky bottom dock ================= --}}
    <div class="mdock">
        <div class="mdock-summary">
            <div>
                <div class="mdock-meta">
                    <span>{{ count($items) }} item</span>
                    <span style="color:var(--gray-300)">·</span>
                    <span>{{ $days }} hari</span>
                    @if($missingUnitsCount > 0)
                        <span style="color:var(--gray-300)">·</span>
                        <span style="color: var(--danger-700); font-weight:600;">{{ $missingUnitsCount }} unit kosong</span>
                    @endif
                </div>
                <div class="mdock-total">
                    <span class="mdock-total-label">Total</span>
                    <span class="mdock-total-val">Rp {{ number_format($totals['total'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
        <div class="mdock-actions">
            <button type="button" class="btn-cancel" wire:click="cancel">Batal</button>
            <button type="button" class="btn-save" wire:click="save" wire:loading.attr="disabled">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
                Simpan
            </button>
        </div>
    </div>

    {{-- ================= Mobile FAB → opens catalog modal ================= --}}
    <button type="button" class="fab" wire:click="$set('catalogOpen', true)" aria-label="Tambah produk">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
    </button>

    {{-- ================= Catalog modal ================= --}}
    @if($catalogOpen)
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
                            <div
                                class="bulk-card"
                                wire:click="addFromSearch('{{ $r['composite_id'] }}')"
                                x-show="(cat === 'All' || cat === '{{ $r['cat'] }}') && (q === '' || '{{ strtolower(addslashes($r['name'].' '.$r['sku'])) }}'.includes(q.toLowerCase()))"
                            >
                                <div class="thumb">📦</div>
                                <div style="min-width:0; flex:1;">
                                    <div class="name">{{ $r['name'] }}</div>
                                    <div class="sub">{{ $r['sku'] }} · {{ $r['avail'] }} stok</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-foot">
                    <span style="color: var(--fg-3); font-size:13px;">Klik produk untuk menambahkan ke rental</span>
                    <button class="btn btn-secondary" wire:click="$set('catalogOpen', false)">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ================= Unit Manager modal ================= --}}
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
                 }"
            >
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

                <div class="unit-modal-summary">
                    <div class="unit-modal-summary-row">
                        <span class="lbl">Periode</span>
                        <span class="val">{{ $um['date_label'] ?: '—' }}</span>
                    </div>
                    <div class="unit-modal-summary-row">
                        <span class="lbl">Tersedia</span>
                        <span class="val {{ $um['available_total'] < $um['qty'] ? 'warn' : 'ok' }}">
                            {{ $um['available_total'] }} dari {{ $um['pool_total'] }} unit
                        </span>
                    </div>
                </div>

                @if($um['pool_total'] === 0 || $um['available_total'] === 0)
                    <div class="unit-modal-empty">
                        <div class="t">0 unit tersedia</div>
                        <div class="s">
                            Semua unit sudah dipinjam{{ $um['date_label'] ? ' pada periode '.$um['date_label'] : '' }}. Coba ubah tanggal atau ganti produk.
                        </div>
                    </div>
                @else
                    @if($um['qty'] > $um['available_total'])
                        <div class="unit-modal-banner">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                            <span>Qty <strong>{{ $um['qty'] }}</strong> melebihi stok yang tersedia (<strong>{{ $um['available_total'] }}</strong> unit). Beberapa slot tidak bisa diisi.</span>
                        </div>
                    @endif

                    <div style="padding:12px 20px 8px; display:flex; align-items:center; justify-content:space-between; gap:12px;">
                        <span class="pill pill-amber" x-show="assigned() < qty" style="font-size:12px;">
                            <span x-text="assigned()"></span>/{{ $um['qty'] }} ter-assign
                        </span>
                        <span class="pill pill-green" x-show="assigned() === qty" style="font-size:12px;">
                            <span x-text="assigned()"></span>/{{ $um['qty'] }} ter-assign
                        </span>
                        <div style="display:flex; gap:12px;">
                            <button type="button" class="btn btn-ghost" @click="autoAssign()" style="font-size:12.5px; padding:4px 8px;">Auto-assign sisa</button>
                            <button type="button" class="btn btn-ghost" @click="clearAll()" style="font-size:12.5px; padding:4px 8px; color: var(--danger-600);">Kosongkan</button>
                        </div>
                    </div>

                    <div class="unit-modal-slots">
                        <template x-for="(val, i) in draft" :key="i">
                            <div class="unit-slot" :class="!val ? 'empty' : ''">
                                <label class="unit-slot-label">
                                    Unit #<span x-text="i + 1"></span><span class="req">*</span>
                                </label>
                                <select class="unit-slot-select" x-model="draft[i]">
                                    <option :value="null">— Pilih unit —</option>
                                    <template x-for="p in pool" :key="p.id">
                                        <option
                                            :value="p.id"
                                            :disabled="!p.available || (draft.filter((d,j) => j !== i).map(String).includes(String(p.id)))"
                                            x-text="p.serial + (!p.available ? ' (dipinjam)' : (draft.filter((d,j) => j !== i).map(String).includes(String(p.id)) ? ' (sudah dipakai)' : ''))"
                                        ></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                    </div>
                @endif

                <div class="modal-foot">
                    <button type="button" class="btn btn-secondary" wire:click="closeUnitModal">Cancel</button>
                    <button type="button" class="btn btn-primary" @click="submit()">Submit</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ================= QR Scanner overlay ================= --}}
    <div
        x-data="qrScannerCtl()"
        x-on:open-qr-scanner.window="open()"
        x-show="visible"
        x-cloak
        class="qr-overlay"
        style="display: none;"
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
</div>

<script>
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
</script>
