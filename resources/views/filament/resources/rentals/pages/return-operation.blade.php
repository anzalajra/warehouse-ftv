<x-filament-panels::page>
    @php
        $items = $this->getDeliveryItems();
        // Inline icon helper (heroicons-like stroke set). Returns an <svg> string.
        $icon = function (string $name, string $class = ''): string {
            $paths = [
                'check' => 'M4.5 12.5l5 5 10-11',
                'checkCircle' => 'M9 12.5l2.2 2.2L15.5 10M12 21a9 9 0 100-18 9 9 0 000 18z',
                'x' => 'M6 6l12 12M18 6L6 18',
                'xCircle' => 'M9.2 9.2l5.6 5.6M14.8 9.2l-5.6 5.6M12 21a9 9 0 100-18 9 9 0 000 18z',
                'alert' => 'M12 9v4m0 4h.01M10.3 4.3L2.6 18a2 2 0 001.7 3h15.4a2 2 0 001.7-3L13.7 4.3a2 2 0 00-3.4 0z',
                'alertCircle' => 'M12 8v5m0 3h.01M12 21a9 9 0 100-18 9 9 0 000 18z',
                'broken' => 'M13 3l-2 7h5l-3 11M5 12h3M16 12h3',
                'lost' => 'M9.2 9.2a3 3 0 114.2 4.2M12 17h.01M12 21a9 9 0 100-18 9 9 0 000 18z',
                'scan' => 'M4 7V5a1 1 0 011-1h2M20 7V5a1 1 0 00-1-1h-2M4 17v2a1 1 0 001 1h2M20 17v2a1 1 0 01-1 1h-2M3 12h18',
                'qr' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h3v3h-3zM20 14v6M17 20h3',
                'camera' => 'M3 8a2 2 0 012-2h1.5l1-1.5h5l1 1.5H18a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2zM12 16.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z',
                'photo' => 'M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zM8 11a2 2 0 100-4 2 2 0 000 4zM3 16l5-4 4 3 4-4 5 5',
                'plus' => 'M12 5v14M5 12h14',
                'cube' => 'M12 2.5l8.5 4.9v9.2L12 21.5 3.5 16.6V7.4zM3.7 7.5L12 12.3l8.3-4.8M12 12.3V21.5',
                'layers' => 'M12 3l9 5-9 5-9-5 9-5zM3 13l9 5 9-5M3 17l9 5 9-5',
                'swap' => 'M7 7h11l-3-3M17 17H6l3 3',
                'filter' => 'M4 5h16M7 12h10M10 19h4',
                'truck' => 'M3 6h11v9H3zM14 9h4l3 3v3h-7M7.5 17.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM17.5 17.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
                'printer' => 'M7 8V4h10v4M7 18H5a2 2 0 01-2-2v-3a2 2 0 012-2h14a2 2 0 012 2v3a2 2 0 01-2 2h-2M7 14h10v6H7z',
                'send' => 'M5 12l15-7-7 15-2-6-6-2z',
                'edit' => 'M4 20h4l11-11-4-4L4 16zM14 6l4 4',
                'chat' => 'M21 12a8 8 0 01-11.5 7.2L3 21l1.8-6.5A8 8 0 1121 12z',
                'doc' => 'M7 3h7l5 5v13H7zM14 3v5h5',
                'arrowRight' => 'M5 12h14M13 6l6 6-6 6',
                'arrowLeft' => 'M19 12H5M11 18l-6-6 6-6',
                'user' => 'M12 12a4 4 0 100-8 4 4 0 000 8zM5 21a7 7 0 0114 0',
                'phone' => 'M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z',
                'calendar' => 'M4 7h16v13H4zM4 7V5a1 1 0 011-1h14a1 1 0 011 1v2M8 3v4M16 3v4M4 11h16',
                'clock' => 'M12 7v5l3 2M12 21a9 9 0 100-18 9 9 0 000 18z',
                'cash' => 'M3 7h18v10H3zM12 15a3 3 0 100-6 3 3 0 000 6zM6 7v10M18 7v10',
                'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
                'trash' => 'M5 7h14M9 7V5h6v2M6 7l1 13h10l1-13',
                'list' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
                'grid' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
                'download' => 'M12 3v12m0 0l-4-4m4 4l4-4M5 21h14',
                'refresh' => 'M4 12a8 8 0 0114-5l2 2M20 12a8 8 0 01-14 5l-2-2M18 4v5h-5M6 20v-5h5',
                'bolt' => 'M13 3L4 14h7l-1 7 9-11h-7z',
                'eye' => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7zM12 15a3 3 0 100-6 3 3 0 000 6z',
                'chevron' => 'M6 9l6 6 6-6',
                'mail' => 'M3 7a1 1 0 011-1h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1zM3.5 7.5l8.5 6 8.5-6',
                'pin' => 'M12 21s7-5.6 7-11a7 7 0 10-14 0c0 5.4 7 11 7 11zM12 12a2.5 2.5 0 100-5 2.5 2.5 0 000 5z',
                'dots' => 'M12 6.2v.01M12 12v.01M12 17.8v.01',
                'keyboard' => 'M3 6h18v12H3zM7 10h.01M11 10h.01M15 10h.01M8 14h8',
                'lock' => 'M5 11h14v9H5zM8 11V8a4 4 0 018 0v3',
                'cameraOff' => 'M3 3l18 18M9.5 5h4l1 1.5H19a2 2 0 012 2v7M3 8.5V17a2 2 0 002 2h11',
                'zap' => 'M13 3L4 14h7l-1 7 9-11h-7z',
                'spinner' => 'M12 3a9 9 0 109 9',
            ];
            $d = $paths[$name] ?? $paths['cube'];
            $segs = array_filter(explode('M', $d));
            $svg = '<svg class="ic ' . trim($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
            foreach ($segs as $s) { $svg .= '<path d="M' . $s . '"></path>'; }
            return $svg . '</svg>';
        };

        $conditionOptions = \App\Models\DeliveryItem::getConditionInOptions();
        $conditionMeta = \App\Models\DeliveryItem::getConditionMeta();
        $issueConditions = \App\Models\DeliveryItem::getIssueConditions();

        $total = $items->count();
        $checkedCount = $items->where('is_checked', true)->count();
        $remaining = $total - $checkedCount;
        $allChecked = $remaining === 0;
        $uncheckedCount = $remaining;

        // "Issues" chip = fair/poor/broken/lost. The Maintenance confirmation banner only
        // counts broken/lost and is fetched live via $wire->maintenanceSummary() in the modal.
        $isIssue = fn ($it) => $it->condition && in_array($it->condition, $issueConditions);
        $issuesCount = $items->filter($isIssue)->count();

        $fin = $this->settlementData();
        $waUrl = $this->whatsappReminderUrl();

        $cust = $rental->customer;
        $initials = strtoupper(collect(explode(' ', $cust->name ?? '?'))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''));
        $history = $this->customerHistory();
        $histTone = ['active' => 'warning', 'completed' => 'success', 'late_return' => 'danger', 'late_pickup' => 'danger', 'cancelled' => 'gray'];
        $isLate = in_array($rental->status, [\App\Models\Rental::STATUS_LATE_RETURN]);

        $editItem = $editingId ? $items->firstWhere('id', $editingId) : null;
    @endphp

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet" />
    <style>
    #op-console {
    /* ============================================================
       Gearent — Pickup & Return Operation redesign
       Filament-flavored utilitarian admin UI. Light theme, red primary.
       ============================================================ */

    & {
      --accent: #dc2626;          /* red-600 */
      --accent-600: #dc2626;
      --accent-700: #b91c1c;
      --accent-50: #fef2f2;
      --accent-100: #fee2e2;
      --accent-200: #fecaca;

      --bg: #f3f4f6;              /* gray-100 page */
      --card: #ffffff;
      --card-2: #f9fafb;          /* gray-50 inset */
      --border: #e5e7eb;          /* gray-200 */
      --border-2: #eef0f3;
      --ring: rgba(17, 24, 39, 0.06);

      --text: #111827;            /* gray-900 */
      --text-2: #374151;          /* gray-700 */
      --muted: #6b7280;           /* gray-500 */
      --muted-2: #9ca3af;         /* gray-400 */

      --success: #16a34a;
      --success-bg: #f0fdf4;
      --success-bd: #bbf7d0;
      --warning: #d97706;
      --warning-bg: #fffbeb;
      --warning-bd: #fde68a;
      --danger: #dc2626;
      --danger-bg: #fef2f2;
      --danger-bd: #fecaca;
      --info: #2563eb;
      --info-bg: #eff6ff;
      --info-bd: #bfdbfe;

      --r-lg: 14px;
      --r-md: 10px;
      --r-sm: 7px;

      --shadow-sm: 0 1px 2px rgba(17,24,39,.05);
      --shadow-md: 0 4px 16px rgba(17,24,39,.08);
      --shadow-lg: 0 12px 40px rgba(17,24,39,.16);

      --pad: 20px;       /* density-controlled */
      --row-pad: 13px;
      --font: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
    }

    &[data-density="compact"] {
      --pad: 14px;
      --row-pad: 9px;
    }

    * { box-sizing: border-box; }

    & {
      margin: 0;
      background: var(--bg);
      color: var(--text);
      font-family: var(--font);
      -webkit-font-smoothing: antialiased;
      text-rendering: optimizeLegibility;
    }

    svg.ic { width: 1.1em; height: 1.1em; flex: none; display: inline-block; vertical-align: -0.15em; }
    .page-title h1 svg.ic { width: 1em; height: 1em; }

    button { font-family: inherit; cursor: pointer; }
    input, textarea, select { font-family: inherit; }

    /* ---------- App shell / top bar ---------- */
    .app {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 30;
      background: rgba(255,255,255,.85);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid var(--border);
      padding: 11px clamp(14px, 3vw, 28px);
      display: flex;
      align-items: center;
      gap: 16px;
      flex-wrap: wrap;
    }
    .brand {
      display: flex; align-items: center; gap: 10px;
      font-weight: 800; letter-spacing: -.02em; font-size: 16px;
    }
    .brand-mark {
      width: 26px; height: 26px; border-radius: 7px;
      background: var(--accent); color: #fff;
      display: grid; place-items: center; font-size: 15px; font-weight: 800;
      box-shadow: var(--shadow-sm);
    }
    .brand small { color: var(--muted); font-weight: 600; font-size: 11px; letter-spacing: .04em; text-transform: uppercase; }

    .topbar-spacer { flex: 1; }
    .view-switch { display: inline-flex; align-items: center; gap: 9px; }
    .view-label { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted-2); }

    /* Segmented control */
    .seg {
      display: inline-flex;
      background: var(--card-2);
      border: 1px solid var(--border);
      border-radius: var(--r-md);
      padding: 3px;
      gap: 2px;
    }
    .seg button {
      border: 0; background: transparent; color: var(--muted);
      font-weight: 650; font-size: 13px;
      padding: 7px 14px; border-radius: 7px;
      display: inline-flex; align-items: center; gap: 7px;
      transition: all .15s ease;
      white-space: nowrap;
    }
    .seg button .ic { width: 16px; height: 16px; }
    .seg button[aria-pressed="true"] {
      background: var(--card); color: var(--text);
      box-shadow: var(--shadow-sm);
    }
    .seg.accent button[aria-pressed="true"] { color: var(--accent-700); }

    /* ---------- Page ---------- */
    .page {
      width: 100%;
      max-width: 1180px;
      margin: 0 auto;
      padding: clamp(16px, 2.4vw, 28px);
      display: flex;
      flex-direction: column;
      gap: 16px;
      flex: 1;
    }

    .page-head {
      display: flex; align-items: flex-start; gap: 16px; flex-wrap: wrap;
    }
    .page-title { display: flex; flex-direction: column; gap: 4px; }
    .page-title h1 {
      margin: 0; font-size: clamp(19px, 2.4vw, 23px); font-weight: 800; letter-spacing: -.02em;
      display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    }
    .page-title .crumb { font-size: 12.5px; color: var(--muted); font-weight: 600; }
    .page-title .crumb b { color: var(--text-2); }
    .head-actions { display: flex; gap: 8px; margin-left: auto; flex-wrap: wrap; }

    /* ---------- Operation toolbar (view switch + actions + validate) ---------- */
    .op-toolbar {
      position: sticky;
      top: 60px;
      z-index: 19;
      display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
      padding: 9px 11px;
      background: rgba(255,255,255,.92);
      backdrop-filter: blur(10px);
      border: 1px solid var(--border);
      border-radius: var(--r-lg);
      box-shadow: var(--shadow-sm);
    }
    .op-toolbar .op-spacer { flex: 1; }
    .view-seg button { padding: 7px 16px; }
    .op-status {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 12.5px; font-weight: 650; color: var(--warning);
      padding: 0 4px; white-space: nowrap;
    }
    .op-status .ic { width: 16px; height: 16px; }
    .op-status.ok { color: var(--success); }
    .op-actions { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .op-actions::before {
      content: ''; width: 1px; align-self: stretch; background: var(--border-2); margin: 2px 2px;
    }
    .btn-chev { width: 13px !important; height: 13px !important; opacity: .55; margin-left: -1px; }

    /* dropdown menu */
    .dropdown { position: relative; display: inline-flex; }
    .dropdown button[aria-expanded="true"] .btn-chev { transform: rotate(180deg); }
    .dropdown button[aria-expanded="true"] { background: var(--card-2); border-color: #d6dae0; }
    .menu {
      position: absolute; top: calc(100% + 6px); left: 0; z-index: 50;
      min-width: 246px; padding: 6px;
      background: var(--card); border: 1px solid var(--border); border-radius: var(--r-md);
      box-shadow: var(--shadow-lg);
      display: flex; flex-direction: column; gap: 2px;
      animation: menuIn .14s cubic-bezier(.16,1,.3,1);
    }
    .menu.menu-right { left: auto; right: 0; }
    @keyframes menuIn { from { transform: translateY(-4px); } to { transform: none; } }
    .menu-item {
      display: flex; align-items: flex-start; gap: 10px; text-align: left;
      padding: 9px 10px; border: 0; background: transparent; border-radius: var(--r-sm);
      transition: background .12s;
    }
    .menu-item:hover { background: var(--card-2); }
    .menu-item > .ic { width: 17px; height: 17px; flex: none; margin-top: 1px; color: var(--muted); }
    .menu-item:hover > .ic { color: var(--accent); }
    .menu-item .mi-main { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
    .menu-item .mi-t { font-size: 13px; font-weight: 650; color: var(--text); }
    .menu-item .mi-d { font-size: 11.5px; color: var(--muted); font-weight: 500; line-height: 1.35; }
    @media (max-width: 720px) {
      .op-toolbar { gap: 8px; }
      .op-status { order: 5; width: 100%; justify-content: flex-start; }
      .op-actions { width: 100%; }
      .op-actions::before { display: none; }
      .op-actions .btn { flex: 1; }
    }

    /* ---------- Buttons ---------- */
    .btn {
      display: inline-flex; align-items: center; justify-content: center; gap: 7px;
      border: 1px solid var(--border);
      background: var(--card);
      color: var(--text-2);
      font-weight: 650; font-size: 13px;
      padding: 8px 13px; border-radius: var(--r-md);
      box-shadow: var(--shadow-sm);
      transition: all .14s ease;
      white-space: nowrap;
    }
    .btn:hover { background: var(--card-2); border-color: #d6dae0; }
    .btn .ic { width: 16px; height: 16px; }
    .btn-primary {
      background: var(--accent); color: #fff; border-color: var(--accent);
    }
    .btn-primary:hover { background: var(--accent-700); border-color: var(--accent-700); }
    .btn-success { background: var(--success); color: #fff; border-color: var(--success); }
    .btn-success:hover { filter: brightness(.95); }
    .btn-ghost { background: transparent; box-shadow: none; border-color: transparent; }
    .btn-ghost:hover { background: var(--card-2); }
    .btn-lg { padding: 12px 20px; font-size: 14.5px; border-radius: 11px; }
    .btn-sm { padding: 6px 10px; font-size: 12px; }
    .btn:disabled, .btn[aria-disabled="true"] {
      opacity: .5; cursor: not-allowed; pointer-events: none;
    }
    .btn-block { width: 100%; }

    /* ---------- Card / section (Filament-like) ---------- */
    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--r-lg);
      box-shadow: var(--shadow-sm);
      overflow: hidden;
    }
    .card-head {
      display: flex; align-items: center; gap: 12px;
      padding: 14px var(--pad);
      border-bottom: 1px solid var(--border-2);
    }
    .card-head h2 {
      margin: 0; font-size: 14.5px; font-weight: 750; letter-spacing: -.01em;
      display: flex; align-items: center; gap: 9px;
    }
    .card-head .sub { font-size: 12px; color: var(--muted); font-weight: 500; }
    .card-head .right { margin-left: auto; display: flex; align-items: center; gap: 8px; }
    .card-body { padding: var(--pad); }
    .card-body.flush { padding: 0; }

    /* ---------- Badges ---------- */
    .badge {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: 11.5px; font-weight: 700; letter-spacing: .01em;
      padding: 3px 9px; border-radius: 999px;
      border: 1px solid transparent; line-height: 1.4;
      white-space: nowrap;
    }
    .badge .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .badge .ic { width: 13px; height: 13px; }
    .b-gray { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }
    .b-primary { background: var(--accent-50); color: var(--accent-700); border-color: var(--accent-100); }
    .b-success { background: var(--success-bg); color: #15803d; border-color: var(--success-bd); }
    .b-warning { background: var(--warning-bg); color: #b45309; border-color: var(--warning-bd); }
    .b-danger { background: var(--danger-bg); color: #b91c1c; border-color: var(--danger-bd); }
    .b-info { background: var(--info-bg); color: #1d4ed8; border-color: var(--info-bd); }

    /* ---------- Rental info card (clean identity + stats) ---------- */
    .rental-card { display: flex; flex-wrap: wrap; align-items: stretch; }
    .rental-id {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 12px;
      flex: 1; min-width: 280px;
    }
    .ri-trigger {
      display: flex; align-items: center; gap: 13px;
      flex: 1; min-width: 0; text-align: left;
      background: transparent; border: 1px solid transparent; border-radius: var(--r-md);
      padding: 6px 8px; transition: background .14s, border-color .14s;
    }
    .ri-trigger:hover { background: var(--card-2); border-color: var(--border); }
    .ri-avatar {
      width: 44px; height: 44px; flex: none; border-radius: 11px;
      background: var(--accent-50); color: var(--accent-700);
      border: 1px solid var(--accent-100);
      display: grid; place-items: center; font-weight: 800; font-size: 15px; letter-spacing: .01em;
    }
    .ri-who { display: flex; flex-direction: column; gap: 4px; min-width: 0; flex: 1; }
    .ri-who .ri-name { font-size: 16px; font-weight: 750; letter-spacing: -.01em; display: flex; align-items: center; gap: 5px; }
    .ri-who .ri-name .ri-chev { width: 15px; height: 15px; color: var(--muted-2); transition: transform .14s; }
    .ri-trigger:hover .ri-chev { color: var(--text-2); transform: translateY(1px); }
    .ri-who .ri-sub { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .ri-code {
      font-size: 11.5px; font-weight: 600; color: var(--text-2);
      background: var(--card-2); border: 1px solid var(--border);
      padding: 1px 7px; border-radius: 5px;
    }
    .ri-phone { display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; color: var(--muted); font-weight: 600; }
    .ri-phone .ic { width: 13px; height: 13px; }

    .rental-stats {
      display: flex; align-items: stretch;
      border-left: 1px solid var(--border-2);
    }
    .rs-cell {
      display: flex; flex-direction: column; gap: 5px; justify-content: center;
      padding: 14px 20px; min-width: 0;
      border-left: 1px solid var(--border-2);
    }
    .rs-cell:first-child { border-left: 0; }
    .rs-cell .rs-k {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted);
    }
    .rs-cell .rs-k .ic { width: 13px; height: 13px; color: var(--muted-2); }
    .rs-cell .rs-v { font-size: 13.5px; font-weight: 700; color: var(--text); display: inline-flex; align-items: center; gap: 7px; white-space: nowrap; }

    @media (max-width: 720px) {
      .rental-stats { border-left: 0; border-top: 1px solid var(--border-2); width: 100%; }
      .rs-cell { flex: 1; }
    }
    @media (max-width: 460px) {
      .rental-stats { flex-direction: column; }
      .rs-cell { border-left: 0; border-top: 1px solid var(--border-2); }
      .rs-cell:first-child { border-top: 0; }
    }

    /* ---------- Customer profile modal ---------- */
    .cust-head { display: flex; align-items: center; gap: 14px; }
    .cust-avatar {
      width: 54px; height: 54px; flex: none; border-radius: 13px;
      background: var(--accent-50); color: var(--accent-700); border: 1px solid var(--accent-100);
      display: grid; place-items: center; font-weight: 800; font-size: 19px;
    }
    .cust-id { display: flex; flex-direction: column; gap: 5px; }
    .cust-id .cust-name { font-size: 18px; font-weight: 800; letter-spacing: -.02em; }
    .cust-tags { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .cust-tags-edit {
      width: 20px; height: 20px; border-radius: 999px; flex: none;
      border: 1px dashed var(--border); background: transparent; color: var(--muted-2);
      display: grid; place-items: center; transition: all .14s;
    }
    .cust-tags-edit:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-50); }
    .cust-tags-edit .ic { width: 12px; height: 12px; }
    .cust-id .cust-since { font-size: 12.5px; color: var(--muted); font-weight: 600; }

    .cust-metrics {
      display: grid; grid-template-columns: repeat(3, 1fr);
      background: var(--card-2); border: 1px solid var(--border); border-radius: var(--r-md);
      overflow: hidden;
    }
    .cm-cell { padding: 12px 14px; display: flex; flex-direction: column; gap: 3px; border-left: 1px solid var(--border); }
    .cm-cell:first-child { border-left: 0; }
    .cm-cell .cm-v { font-size: 18px; font-weight: 800; letter-spacing: -.01em; display: inline-flex; align-items: center; gap: 6px; }
    .cm-cell .cm-v.ok { color: #15803d; font-size: 15px; }
    .cm-cell .cm-v .ic { width: 16px; height: 16px; }
    .cm-cell .cm-k { font-size: 11px; color: var(--muted); font-weight: 650; text-transform: uppercase; letter-spacing: .03em; }

    .cust-contact { display: flex; flex-direction: column; }
    .cc-row { display: flex; align-items: center; gap: 11px; padding: 10px 0; border-bottom: 1px solid var(--border-2); }
    .cc-row:last-child { border-bottom: 0; }
    .cc-ic { width: 30px; height: 30px; flex: none; border-radius: 8px; background: var(--card-2); border: 1px solid var(--border); display: grid; place-items: center; color: var(--muted); }
    .cc-ic .ic { width: 15px; height: 15px; }
    .cc-row .cc-k { font-size: 12px; color: var(--muted); font-weight: 650; width: 64px; flex: none; text-transform: uppercase; letter-spacing: .03em; }
    .cc-row .cc-v { font-size: 13.5px; font-weight: 650; color: var(--text); flex: 1; line-height: 1.4; }

    .cust-hist .ch-head { display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 750; color: var(--text-2); text-transform: uppercase; letter-spacing: .03em; margin-bottom: 9px; }
    .cust-hist .ch-head .ic { width: 15px; height: 15px; color: var(--muted); }
    .cust-hist .ch-count { font-size: 11px; background: var(--card-2); border: 1px solid var(--border); color: var(--muted); padding: 0 7px; border-radius: 999px; font-weight: 700; }
    .ch-list { display: flex; flex-direction: column; gap: 6px; }
    .ch-item {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 12px; background: var(--card-2);
      border: 1px solid var(--border); border-radius: var(--r-md);
      text-decoration: none; color: inherit;
      transition: border-color .14s, background .14s, box-shadow .14s;
    }
    .ch-item:hover { background: var(--card); border-color: var(--accent-200); box-shadow: var(--shadow-sm); }
    .ch-item .ch-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .ch-item .ch-code { font-size: 12.5px; font-weight: 700; color: var(--text); }
    .ch-item:hover .ch-code { color: var(--accent-700); }
    .ch-item .ch-meta { font-size: 11.5px; color: var(--muted); font-weight: 600; }
    .ch-item .ch-amt { font-size: 13px; font-weight: 750; white-space: nowrap; }
    .ch-item .ch-go { width: 15px; height: 15px; color: var(--muted-2); transform: rotate(-90deg); flex: none; }
    .ch-item:hover .ch-go { color: var(--accent); }
    .ch-more { margin-top: 8px; color: var(--text-2); }

    /* ---------- Banners ---------- */
    .banner {
      display: flex; gap: 12px; align-items: flex-start;
      padding: 13px 15px; border-radius: var(--r-md);
      border: 1px solid; font-size: 13px; line-height: 1.5;
    }
    .banner .ic { width: 19px; height: 19px; flex: none; margin-top: 1px; }
    .banner .body { flex: 1; }
    .banner .body strong { font-weight: 750; }
    .banner .actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 9px; }
    .banner-danger { background: var(--danger-bg); border-color: var(--danger-bd); color: #991b1b; }
    .banner-warning { background: var(--warning-bg); border-color: var(--warning-bd); color: #92400e; }
    .banner-success { background: var(--success-bg); border-color: var(--success-bd); color: #166534; }
    .banner-info { background: var(--info-bg); border-color: var(--info-bd); color: #1e40af; }
    /* ---------- Conflict banner (redesigned: header strip + clean list) ---------- */
    .conflict-banner {
      border: 1px solid var(--danger-bd);
      background: var(--danger-bg);
      border-radius: var(--r-lg);
      overflow: hidden;
      box-shadow: var(--shadow-sm);
    }
    .cb-head {
      display: flex; align-items: center; gap: 11px;
      padding: 12px 14px;
      border-bottom: 1px solid var(--danger-bd);
    }
    .cb-icon {
      width: 32px; height: 32px; flex: none; border-radius: 9px;
      background: var(--danger); color: #fff;
      display: grid; place-items: center;
    }
    .cb-icon .ic { width: 18px; height: 18px; }
    .cb-headtx { display: flex; flex-direction: column; gap: 1px; flex: 1; min-width: 0; }
    .cb-headtx strong { font-size: 14px; font-weight: 750; color: #991b1b; letter-spacing: -.01em; }
    .cb-headtx span { font-size: 12.5px; color: #b45454; font-weight: 500; }
    .cb-count {
      flex: none; min-width: 24px; height: 24px; padding: 0 7px;
      border-radius: 999px; background: var(--danger); color: #fff;
      font-size: 12.5px; font-weight: 750; display: grid; place-items: center;
    }
    .cb-list { display: flex; flex-direction: column; background: var(--card); }
    .cb-row {
      display: flex; align-items: center; gap: 12px;
      padding: 11px 14px;
      border-bottom: 1px solid var(--border-2);
    }
    .cb-row:last-child { border-bottom: 0; }
    .cb-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
    .cb-name {
      font-size: 13.5px; font-weight: 700; color: var(--text);
      display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .cb-name .mono {
      font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 11px;
      font-weight: 600; color: var(--text-2);
      background: var(--card-2); border: 1px solid var(--border);
      padding: 1px 6px; border-radius: 5px;
    }
    .cb-detail { font-size: 12px; color: var(--muted); display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
    .cb-status {
      font-weight: 700; color: #b91c1c;
      background: var(--danger-bg); border: 1px solid var(--danger-bd);
      padding: 1px 8px; border-radius: 999px; font-size: 11px; white-space: nowrap;
    }
    .cb-swap { flex: none; }

    /* ---------- Scan bar ---------- */
    /* ---------- Scan action (single button) ---------- */
    .scan-btn {
      width: 100%;
      display: flex; align-items: center; justify-content: center; gap: 11px;
      padding: 12px 16px;
      background: var(--card-2);
      border: 1.5px dashed #d6dae0;
      border-radius: var(--r-md);
      color: var(--text); font-family: var(--font);
      font-size: 14px; font-weight: 700; letter-spacing: -.01em;
      cursor: pointer;
      transition: border-color .15s, background .15s, color .15s;
    }
    .scan-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-50); }
    .scan-btn:active { transform: translateY(1px); }
    .scan-btn.live { border-style: solid; border-color: var(--accent); background: var(--accent-50); color: var(--accent); }
    .scan-btn .scan-ic {
      width: 32px; height: 32px; border-radius: 9px; flex: none;
      background: var(--card); border: 1px solid var(--border);
      display: grid; place-items: center; color: var(--accent);
      transition: border-color .15s;
    }
    .scan-btn:hover .scan-ic { border-color: var(--accent-200); }
    .scan-btn .scan-ic .ic { width: 20px; height: 20px; }

    /* ---------- Toolbar (progress + filters) ---------- */
    .toolbar {
      display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
      padding: 12px var(--pad);
      border-bottom: 1px solid var(--border-2);
      background: var(--card);
    }
    .progress-wrap { display: flex; align-items: center; gap: 11px; min-width: 220px; flex: 1; }
    .progress-num { font-size: 13px; font-weight: 750; white-space: nowrap; }
    .progress-num b { color: var(--success); }
    .progress-track { flex: 1; height: 8px; border-radius: 999px; background: #eceef1; overflow: hidden; min-width: 90px; }
    .progress-fill { height: 100%; border-radius: 999px; background: var(--success); transition: width .35s cubic-bezier(.4,0,.2,1); }
    .progress-fill.warn { background: var(--warning); }

    .chips { display: inline-flex; gap: 6px; flex-wrap: wrap; }
    .toolbar .mark-all { margin-left: auto; white-space: nowrap; flex: none; }
    .chip {
      border: 1px solid var(--border); background: var(--card);
      color: var(--muted); font-weight: 650; font-size: 12.5px;
      padding: 6px 12px; border-radius: 999px;
      display: inline-flex; align-items: center; gap: 6px;
      transition: all .14s;
    }
    .chip:hover { border-color: #d0d4da; color: var(--text-2); }
    .chip[aria-pressed="true"] { background: var(--text); color: #fff; border-color: var(--text); }
    .chip .count {
      font-size: 11px; background: rgba(0,0,0,.07); color: inherit;
      padding: 0 6px; border-radius: 999px; min-width: 17px; text-align: center; font-weight: 700;
    }
    .chip[aria-pressed="true"] .count { background: rgba(255,255,255,.22); }
    .chip-danger[aria-pressed="false"] { color: #b91c1c; border-color: var(--danger-bd); background: var(--danger-bg); }

    /* ---------- Checklist rows (Layout A) ---------- */
    .rows { display: flex; flex-direction: column; }
    .row {
      display: grid;
      grid-template-columns: 44px 1fr auto;
      gap: 14px; align-items: center;
      padding: var(--row-pad) var(--pad);
      border-bottom: 1px solid var(--border-2);
      transition: background .12s;
    }
    .row:last-child { border-bottom: 0; }
    .row:hover { background: var(--card-2); }
    .row.is-kit { padding-left: calc(var(--pad) + 26px); background: linear-gradient(90deg, var(--card-2), transparent 40%); }
    .row.checked { background: linear-gradient(90deg, var(--success-bg), transparent 55%); }
    .row.conflict { background: linear-gradient(90deg, var(--danger-bg), transparent 60%); }
    .row.flash { animation: flash 1.1s ease; }
    @keyframes flash {
      0% { background: var(--accent-100); }
      100% { background: transparent; }
    }

    .row .thumb {
      width: 44px; height: 44px; border-radius: 9px; flex: none;
      background: repeating-linear-gradient(45deg, #eef0f3, #eef0f3 6px, #f6f7f9 6px, #f6f7f9 12px);
      border: 1px solid var(--border); display: grid; place-items: center;
      color: var(--muted-2); overflow: hidden; position: relative;
    }
    .row .thumb img { width: 100%; height: 100%; object-fit: cover; }
    .row .thumb .ic { width: 20px; height: 20px; }
    .row .thumb.has-photo { border-color: var(--success-bd); }
    .row .thumb .cam-badge {
      position: absolute; right: -3px; bottom: -3px; width: 17px; height: 17px; border-radius: 50%;
      background: var(--success); color: #fff; display: grid; place-items: center; border: 2px solid #fff;
    }
    .row .thumb .cam-badge .ic { width: 9px; height: 9px; }

    .row .main { min-width: 0; display: flex; flex-direction: column; gap: 4px; }
    .row .name { font-size: 14px; font-weight: 700; letter-spacing: -.01em; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .row .meta { font-size: 12px; color: var(--muted); display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
    .row .meta .sn { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 11.5px; color: var(--text-2); background: var(--card-2); border: 1px solid var(--border); padding: 1px 6px; border-radius: 5px; }
    .row .meta .tags { display: inline-flex; gap: 5px; flex-wrap: wrap; }
    .row .right { display: flex; align-items: center; gap: 10px; }
    .row-actions { display: inline-flex; align-items: center; gap: 6px; }
    .btn-icon { padding: 7px; }
    .btn-icon .ic { width: 16px; height: 16px; }
    .btn-icon[aria-pressed="true"] { background: var(--accent-50); border-color: var(--accent-200); color: var(--accent-700); }

    .tag-pill {
      font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 999px;
      background: var(--warning-bg); color: #b45309; border: 1px solid var(--warning-bd);
    }

    /* condition mini badge */
    .cond { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700; }
    .cond .dot { width: 8px; height: 8px; border-radius: 50%; }
    .cond.good .dot { background: var(--success); } .cond.good { color: #15803d; }
    .cond.minor .dot { background: var(--warning); } .cond.minor { color: #b45309; }
    .cond.broken .dot { background: var(--danger); } .cond.broken { color: #b91c1c; }
    .cond.lost .dot { background: #6b7280; } .cond.lost { color: #4b5563; }
    .cond.none .dot { background: var(--muted-2); } .cond.none { color: var(--muted); }

    /* check status icon */
    .check-ic { width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; flex: none; }
    .check-ic.on { background: var(--success); color: #fff; }
    .check-ic.off { background: #fff; border: 2px solid var(--border); color: var(--muted-2); }
    .check-ic .ic { width: 16px; height: 16px; }

    /* ---------- Inline editor (Layout A expand) ---------- */
    .editor {
      grid-column: 1 / -1;
      background: var(--card-2);
      border: 1px solid var(--border);
      border-radius: var(--r-md);
      padding: 16px;
      margin-top: 4px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .editor .field { display: flex; flex-direction: column; gap: 7px; }
    .editor .field.full { grid-column: 1 / -1; }
    .field-label { font-size: 12px; font-weight: 700; color: var(--text-2); text-transform: uppercase; letter-spacing: .03em; }

    /* condition segmented (big) */
    .cond-seg { display: flex; gap: 8px; flex-wrap: wrap; }
    .cond-btn {
      flex: 1; min-width: 78px;
      border: 1.5px solid var(--border); background: var(--card);
      border-radius: var(--r-md); padding: 11px 8px;
      display: flex; flex-direction: column; align-items: center; gap: 6px;
      font-weight: 700; font-size: 12.5px; color: var(--text-2);
      transition: all .14s;
    }
    .cond-btn .ic { width: 20px; height: 20px; }
    .cond-btn:hover { border-color: #cfd3d9; }
    .cond-btn[aria-pressed="true"].c-good { border-color: var(--success); background: var(--success-bg); color: #15803d; }
    .cond-btn[aria-pressed="true"].c-minor { border-color: var(--warning); background: var(--warning-bg); color: #b45309; }
    .cond-btn[aria-pressed="true"].c-broken { border-color: var(--danger); background: var(--danger-bg); color: #b91c1c; }
    .cond-btn[aria-pressed="true"].c-lost { border-color: #6b7280; background: #f3f4f6; color: #374151; }

    /* damage tags */
    .dmg-tags { display: flex; gap: 7px; flex-wrap: wrap; }
    .dmg-tag {
      border: 1px solid var(--border); background: var(--card);
      border-radius: 999px; padding: 6px 12px; font-size: 12.5px; font-weight: 650; color: var(--muted);
      display: inline-flex; align-items: center; gap: 6px; transition: all .14s;
    }
    .dmg-tag .ic { width: 14px; height: 14px; }
    .dmg-tag:hover { border-color: var(--warning-bd); color: #b45309; }
    .dmg-tag[aria-pressed="true"] { background: var(--warning-bg); border-color: var(--warning); color: #b45309; }

    /* photo capture */
    .photos { display: flex; gap: 9px; flex-wrap: wrap; }
    .photo-slot {
      width: 72px; height: 72px; border-radius: var(--r-md);
      border: 1.5px dashed #cfd3d9; background: var(--card);
      display: grid; place-items: center; color: var(--muted-2);
      position: relative; overflow: hidden; transition: all .14s;
    }
    .photo-slot:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-50); }
    .photo-slot .ic { width: 22px; height: 22px; }
    .photo-slot.filled { border-style: solid; border-color: var(--success-bd); }
    .photo-slot.filled .fill {
      position: absolute; inset: 0;
      background: repeating-linear-gradient(135deg, #dbeafe, #dbeafe 7px, #eff6ff 7px, #eff6ff 14px);
      display: grid; place-items: center; color: #1d4ed8;
    }
    .photo-slot .rm {
      position: absolute; top: 3px; right: 3px; width: 18px; height: 18px; border-radius: 50%;
      background: rgba(17,24,39,.7); color: #fff; border: 0; display: grid; place-items: center;
    }
    .photo-slot .rm .ic { width: 11px; height: 11px; }

    .textarea {
      border: 1px solid var(--border); border-radius: var(--r-md);
      padding: 9px 12px; font-size: 13.5px; resize: vertical; min-height: 64px;
      background: var(--card); color: var(--text); outline: none;
    }
    .textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-100); }
    .editor-actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 8px; padding-top: 2px; }

    /* ---------- Sticky action bar ---------- */
    .actionbar {
      position: sticky; bottom: 0; z-index: 20;
      background: rgba(255,255,255,.9); backdrop-filter: blur(10px);
      border: 1px solid var(--border); border-radius: var(--r-lg);
      box-shadow: var(--shadow-md);
      padding: 13px 18px;
      display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    }
    .actionbar .status-text { font-size: 13px; color: var(--muted); font-weight: 600; display: flex; align-items: center; gap: 8px; }
    .actionbar .status-text .ic { width: 17px; height: 17px; }
    .actionbar .grow { flex: 1; }

    /* ============================================================
       Layout B — Focus mode
       ============================================================ */
    .focus-wrap { display: grid; grid-template-columns: 290px 1fr; gap: 16px; align-items: start; }
    .focus-rail {
      background: var(--card); border: 1px solid var(--border); border-radius: var(--r-lg);
      box-shadow: var(--shadow-sm); overflow: hidden; position: sticky; top: 128px;
    }
    .rail-head { padding: 13px 15px; border-bottom: 1px solid var(--border-2); display: flex; align-items: center; gap: 11px; }
    .ring { width: 42px; height: 42px; flex: none; position: relative; }
    .ring svg { transform: rotate(-90deg); }
    .ring .lbl { position: absolute; inset: 0; display: grid; place-items: center; font-size: 11px; font-weight: 800; }
    .rail-head .t { font-size: 13px; font-weight: 750; }
    .rail-head .t small { display: block; color: var(--muted); font-weight: 600; font-size: 11.5px; }

    .rail-list { display: flex; flex-direction: column; gap: 1px; padding: 7px; max-height: 470px; overflow: auto; }
    .rail-item {
      position: relative;
      display: flex; align-items: center; gap: 11px;
      padding: 9px 11px 9px 13px;
      border: 0; border-radius: 9px;
      text-align: left; background: transparent;
      transition: background .12s;
    }
    .rail-item:hover { background: var(--card-2); }
    .rail-item.active { background: var(--accent-50); }
    .rail-item.active::before {
      content: ''; position: absolute; left: 3px; top: 9px; bottom: 9px;
      width: 3px; border-radius: 3px; background: var(--accent);
    }
    .rail-item.is-kit { padding-left: 30px; }
    .rail-item.is-kit::after {
      content: ''; position: absolute; left: 19px; top: 50%; width: 7px; height: 1px; background: var(--border);
    }
    .rail-item .ri-state {
      width: 20px; height: 20px; border-radius: 50%; flex: none;
      display: grid; place-items: center; transition: all .12s;
    }
    .rail-item .ri-state.on { background: var(--success); color: #fff; }
    .rail-item .ri-state.off { border: 1.5px solid var(--border); color: transparent; }
    .rail-item:hover .ri-state.off { border-color: var(--muted-2); }
    .rail-item .ri-state.conflict { background: var(--danger); color: #fff; }
    .rail-item .ri-state .ic { width: 12px; height: 12px; }
    .rail-item .ri-main { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 2px; }
    .rail-item .ri-name { font-size: 13px; font-weight: 600; letter-spacing: -.005em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-2); }
    .rail-item.active .ri-name { color: var(--text); font-weight: 700; }
    .rail-item .ri-sn { font-size: 10.5px; color: var(--muted-2); font-family: 'JetBrains Mono', ui-monospace, monospace; letter-spacing: -.02em; }
    .rail-item .ri-dot { width: 8px; height: 8px; border-radius: 50%; flex: none; box-shadow: 0 0 0 3px var(--card); }
    .rail-foot { padding: 9px; border-top: 1px solid var(--border-2); }

    /* focus card */
    .focus-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--r-lg); box-shadow: var(--shadow-sm); overflow: hidden; }
    .fc-top { display: flex; gap: 20px; padding: 22px; border-bottom: 1px solid var(--border-2); flex-wrap: wrap; align-items: center; }
    .fc-photo {
      width: 132px; height: 132px; border-radius: var(--r-md); flex: none;
      background: repeating-linear-gradient(45deg, #eef0f3, #eef0f3 8px, #f6f7f9 8px, #f6f7f9 16px);
      border: 1px solid var(--border); display: grid; place-items: center; color: var(--muted-2);
      position: relative; overflow: hidden;
    }
    .fc-photo .ic { width: 34px; height: 34px; }
    .fc-photo .mono { position: absolute; bottom: 8px; font-size: 9.5px; font-family: ui-monospace, monospace; color: var(--muted-2); }
    .fc-head-main { flex: 1; min-width: 220px; display: flex; flex-direction: column; gap: 10px; }
    .fc-eyebrow { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .fc-head-main h3 { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -.02em; line-height: 1.15; }
    .fc-sn { font-family: ui-monospace, monospace; font-size: 13px; color: var(--text-2); background: var(--card-2); border: 1px solid var(--border); padding: 4px 10px; border-radius: 7px; display: inline-flex; align-items: center; gap: 7px; width: fit-content; }
    .fc-pos { font-size: 12px; color: var(--muted); font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .fc-body { padding: 22px; display: flex; flex-direction: column; gap: 22px; }
    .fc-section { display: flex; flex-direction: column; gap: 10px; }
    .fc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 22px; align-items: start; }
    .fc-foot {
      padding: 16px 22px; border-top: 1px solid var(--border-2); background: var(--card-2);
      display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    }

    /* ---------- Modal ---------- */
    .scrim {
      position: fixed; inset: 0; z-index: 60; background: rgba(17,24,39,.45);
      backdrop-filter: blur(2px); display: grid; place-items: center; padding: 20px;
      animation: fade .15s ease;
    }
    @keyframes fade { from { opacity: 0; } to { opacity: 1; } }
    .modal {
      width: 100%; max-width: 560px; max-height: 90vh; overflow: auto;
      background: var(--card); border-radius: var(--r-lg); box-shadow: var(--shadow-lg);
      animation: pop .18s cubic-bezier(.16,1,.3,1);
    }
    .modal.wide { max-width: 620px; }
    @keyframes pop { from { transform: scale(.96) translateY(8px); opacity: 0; } to { transform: none; opacity: 1; } }
    .modal-head { padding: 18px 20px 6px; }
    .modal-head h3 { margin: 0; font-size: 18px; font-weight: 800; letter-spacing: -.02em; display: flex; align-items: center; gap: 10px; }
    .modal-head p { margin: 6px 0 0; color: var(--muted); font-size: 13px; line-height: 1.5; }
    .modal-body { padding: 16px 20px; display: flex; flex-direction: column; gap: 14px; }
    .modal-foot { padding: 14px 20px 18px; display: flex; justify-content: flex-end; gap: 9px; flex-wrap: wrap; }

    /* settlement summary */
    .sum-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 0; border-bottom: 1px solid var(--border-2); }
    .sum-row:last-child { border-bottom: 0; }
    .sum-row .sk { font-size: 13.5px; color: var(--text-2); font-weight: 600; display: flex; align-items: center; gap: 8px; }
    .sum-row .sv { font-size: 15px; font-weight: 750; }
    .sum-row.total { border-top: 2px solid var(--border); margin-top: 4px; padding-top: 14px; }
    .sum-row.total .sv { font-size: 19px; color: var(--accent-700); }
    .money-input { display: flex; align-items: center; gap: 0; border: 1px solid var(--border); border-radius: var(--r-md); overflow: hidden; }
    .money-input .pfx { padding: 8px 11px; background: var(--card-2); border-right: 1px solid var(--border); font-size: 13px; color: var(--muted); font-weight: 700; }
    .money-input input { border: 0; outline: none; padding: 8px 11px; font-size: 14px; font-weight: 700; width: 100%; text-align: right; }
    .radio-cards { display: flex; flex-direction: column; gap: 8px; }
    .radio-card {
      display: flex; align-items: center; gap: 11px; padding: 11px 13px;
      border: 1.5px solid var(--border); border-radius: var(--r-md); transition: all .14s; text-align: left; background: var(--card);
    }
    .radio-card:hover { border-color: #cfd3d9; }
    .radio-card[aria-pressed="true"] { border-color: var(--accent); background: var(--accent-50); }
    .radio-card .rc-dot { width: 18px; height: 18px; border-radius: 50%; border: 2px solid var(--border); flex: none; display: grid; place-items: center; }
    .radio-card[aria-pressed="true"] .rc-dot { border-color: var(--accent); }
    .radio-card[aria-pressed="true"] .rc-dot::after { content: ''; width: 9px; height: 9px; border-radius: 50%; background: var(--accent); }
    .radio-card .rc-main { flex: 1; }
    .radio-card .rc-t { font-size: 13.5px; font-weight: 700; }
    .radio-card .rc-d { font-size: 12px; color: var(--muted); margin-top: 1px; }

    /* toast */
    .toasts { position: fixed; right: 18px; bottom: 18px; z-index: 80; display: flex; flex-direction: column; gap: 9px; max-width: 360px; }
    .toast {
      display: flex; gap: 11px; align-items: flex-start;
      background: var(--text); color: #fff; border-radius: var(--r-md); padding: 12px 14px;
      box-shadow: var(--shadow-lg); animation: slideIn .25s cubic-bezier(.16,1,.3,1);
    }
    .toast .ic { width: 19px; height: 19px; flex: none; margin-top: 1px; }
    .toast.success { background: #14532d; }
    .toast.warning { background: #78350f; }
    .toast.danger { background: #7f1d1d; }
    .toast .tt { font-size: 13.5px; font-weight: 700; }
    .toast .td { font-size: 12.5px; opacity: .85; margin-top: 2px; line-height: 1.4; }
    @keyframes slideIn { from { transform: translateX(20px); opacity: 0; } to { transform: none; opacity: 1; } }

    /* empty state */
    .empty { padding: 40px 20px; text-align: center; color: var(--muted); }
    .empty .ic { width: 38px; height: 38px; color: var(--muted-2); margin-bottom: 10px; }
    .empty .et { font-size: 14px; font-weight: 700; color: var(--text-2); }
    .empty .ed { font-size: 12.5px; margin-top: 3px; }

    /* misc */
    .hr { height: 1px; background: var(--border-2); border: 0; margin: 0; }
    .spin { animation: spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .muted { color: var(--muted); }
    .mono { font-family: ui-monospace, monospace; }

    /* responsive */
    @media (max-width: 860px) {
      .focus-wrap { grid-template-columns: 1fr; }
      .focus-rail { position: static; }
      .rail-list { max-height: 220px; }
      .editor { grid-template-columns: 1fr; }
    }
    @media (max-width: 560px) {
      .row { grid-template-columns: 38px 1fr; grid-template-areas: 'thumb main' 'right right'; }
      .row .thumb { grid-area: thumb; } .row .main { grid-area: main; } .row .right { grid-area: right; justify-content: space-between; padding-top: 4px; }
      .head-actions { width: 100%; }
      .info-grid { grid-template-columns: 1fr 1fr; }
    }


    /* ===== mobile breakpoint ===== */
    /* ============================================================
       Mobile breakpoint — Gearent Pickup & Return Operation
       Phone-first reflow of the SAME app. Nothing removed:
       every desktop control is reachable and touch-sized here.
       Loads after styles.css so these rules win where they overlap.
       ============================================================ */

    @keyframes sheetUp { from { transform: translateY(100%); } to { transform: none; } }

    /* The phone-only sticky action bar is hidden on desktop (desktop uses the toolbar). */
    .mobile-actionbar { display: none; }

    @media (max-width: 680px) {

      & { --pad: 15px; --row-pad: 12px; }

      /* ---------------- Top bar ---------------- */
      .topbar {
        gap: 10px;
        padding: 9px 14px;
      }
      .brand { font-size: 15px; gap: 8px; }
      .brand small { display: none; }                 /* keep just the "Gearent" wordmark */
      .topbar .seg.accent {
        margin-left: auto !important;
        flex: 0 0 auto;
      }
      .topbar .seg.accent button { padding: 8px 13px; }
      .topbar-spacer { display: none; }

      /* ---------------- Page ---------------- */
      .page {
        padding: 14px 14px calc(80px + env(safe-area-inset-bottom, 0px));
        gap: 14px;
      }
      .page-head { gap: 8px; }
      .page-title h1 { font-size: 20px; }
      .page-title .crumb { font-size: 12px; }

      /* ---------------- Operation toolbar ---------------- */
      /* not sticky on phone — it stacks into a compact control block,
         and the primary action becomes a fixed bottom CTA (below). */
      .op-toolbar {
        position: static;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        padding: 11px;
      }
      .op-toolbar .view-seg { display: none; }   /* Focus view is desktop-only — hide the List/Focus toggle */
      .op-toolbar .op-spacer { display: none; }

      .op-status {
        width: 100%;
        justify-content: center;
        padding: 8px;
        background: var(--card-2);
        border: 1px solid var(--border);
        border-radius: var(--r-md);
      }

      /* The desktop Send / Print / Edit / Validate cluster is replaced on phones by
         a single sticky bottom bar: a 3-dot "more" button inline with Validate. */
      .op-actions { display: none; }

      /* ---------------- Sticky bottom action bar (phone) ---------------- */
      .mobile-actionbar {
        display: flex;
        align-items: stretch;
        gap: 9px;
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 28;
        padding: 10px 12px calc(10px + env(safe-area-inset-bottom, 0px));
        background: var(--card);
        border-top: 1px solid var(--border);
        box-shadow: 0 -6px 22px rgba(17, 24, 39, .12);
      }
      .mobile-actionbar .mab-more {
        flex: 0 0 auto;
        width: 52px;
        padding: 0;
        justify-content: center;
        border-radius: var(--r-md);
      }
      .mobile-actionbar .mab-more .ic { width: 24px; height: 24px; stroke-width: 3; }
      .mobile-actionbar .mab-primary {
        flex: 1 1 auto;
        justify-content: center;
        padding: 14px;
        border-radius: var(--r-md);
        font-size: 15.5px;
      }
      .mobile-actionbar .mab-primary:disabled,
      .mobile-actionbar .mab-primary[aria-disabled="true"] { opacity: 1; background: #9ca3af; border-color: #9ca3af; }

      /* ---------------- Action sheet body (Send / Print / Edit) ---------------- */
      .act-sheet { display: flex; flex-direction: column; gap: 18px; }
      .act-group { display: flex; flex-direction: column; gap: 8px; }
      .act-label {
        display: flex; align-items: center; gap: 7px;
        font-size: 12px; font-weight: 700; letter-spacing: .03em; text-transform: uppercase;
        color: var(--muted-2);
      }
      .act-label .ic { width: 15px; height: 15px; }
      .act-item {
        display: flex; align-items: center; gap: 12px; width: 100%;
        text-align: left; cursor: pointer;
        padding: 12px; border: 1px solid var(--border); border-radius: var(--r-md);
        background: var(--card); color: var(--text);
      }
      .act-item:active { background: var(--card-2); }
      .act-ic {
        flex: none; width: 38px; height: 38px; border-radius: 10px;
        display: grid; place-items: center;
        background: var(--card-2); color: var(--accent);
      }
      .act-ic .ic { width: 19px; height: 19px; }
      .act-tx { display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1; }
      .act-tx b { font-size: 14.5px; font-weight: 700; }
      .act-tx small { font-size: 12.5px; color: var(--muted); line-height: 1.4; }
      .act-chev { width: 16px; height: 16px; color: var(--muted-2); flex: none; }

      /* ---------------- Rental info card ---------------- */
      .rental-id { padding: 11px; }
      .ri-trigger { padding: 6px; }
      .ri-avatar { width: 42px; height: 42px; font-size: 14px; }
      .ri-who .ri-name { font-size: 15px; }

      /* ---------------- Card heads ---------------- */
      .card-head { flex-wrap: wrap; gap: 8px; }

      /* ---------------- Conflict banner ---------------- */
      .cb-row { flex-wrap: wrap; }
      .cb-swap { width: 100%; justify-content: center; padding: 10px; margin-top: 2px; }

      /* ---------------- Scan action ---------------- */
      .scan-btn { padding: 13px 14px; }

      /* ---------------- Filter / progress toolbar ---------------- */
      .toolbar { gap: 11px; padding: 12px var(--pad); }
      .progress-wrap { flex: 1 1 100%; min-width: 0; }
      .chips { width: 100%; }
      .chips .chip { flex: 1; justify-content: center; padding: 8px 10px; }
      .toolbar .mark-all { width: 100%; margin-left: 0; justify-content: center; padding: 10px; }

      /* ---------------- Checklist rows (Layout A) ---------------- */
      .row {
        position: relative;
        grid-template-columns: 42px 1fr;
        grid-template-areas: 'thumb main' 'right right';
        gap: 12px;
        align-items: start;
      }
      .row .thumb { grid-area: thumb; }
      .row .main { grid-area: main; padding-top: 2px; }
      .row .right {
        grid-area: right;
        justify-content: flex-start;       /* group condition + check together, don't float the check icon */
        align-items: center;
        gap: 10px;
        width: 100%;
        padding-top: 10px;
        margin-top: 4px;
        border-top: 1px dashed var(--border-2);
      }
      /* actions + swap sit at the far right; check status stays beside the condition badge */
      .row .right .row-actions,
      .row .right > .btn-primary { margin-left: auto; }

      /* Kit rows read as a child of the unit above: indent + connector, one ↳ only. */
      .row.is-kit {
        padding-left: calc(var(--pad) + 22px);
        background: linear-gradient(90deg, var(--card-2), transparent 55%);
      }
      .row.is-kit::before {
        content: '';
        position: absolute;
        left: calc(var(--pad) + 7px);
        top: -2px;
        height: calc(var(--row-pad) + 22px);
        width: 11px;
        border-left: 1.5px solid var(--border);
        border-bottom: 1.5px solid var(--border);
        border-bottom-left-radius: 8px;
        pointer-events: none;
      }
      .row-actions { gap: 7px; }
      .row-actions .btn { min-height: 38px; }
      .row .right .btn-primary { min-height: 38px; }

      /* ---------------- Layout B — Focus mode ---------------- */
      .focus-wrap { grid-template-columns: 1fr; gap: 14px; }
      .focus-rail { position: static; }
      .rail-list { max-height: 250px; }
      .rail-item { padding-top: 11px; padding-bottom: 11px; }
      .fc-top { padding: 16px; gap: 14px; }
      .fc-photo { width: 88px; height: 88px; }
      .fc-photo .ic { width: 26px; height: 26px; }
      .fc-head-main { min-width: 140px; }
      .fc-head-main h3 { font-size: 19px; }
      .fc-body { padding: 16px; gap: 18px; }
      .fc-grid { grid-template-columns: 1fr; gap: 18px; }
      .fc-foot { padding: 13px 16px; }
      .fc-foot .btn-lg { flex: 1 1 100%; padding: 14px; }
      .cond-btn { min-width: 64px; padding: 12px 6px; }

      /* ---------------- Inline editor (Layout A expand uses Modal) ---------------- */
      .editor { grid-template-columns: 1fr; }

      /* ---------------- Modals → bottom sheets ---------------- */
      .scrim { align-items: flex-end; padding: 0; }
      .modal,
      .modal.wide {
        max-width: 100%;
        width: 100%;
        max-height: 92vh;
        border-radius: 20px 20px 0 0;
        animation: sheetUp .28s cubic-bezier(.2, .7, .3, 1);
      }
      .modal-head { padding: 18px 18px 4px; }
      .modal-body { padding: 14px 18px; }
      .modal-foot {
        position: sticky;
        bottom: 0;
        padding: 13px 18px calc(16px + env(safe-area-inset-bottom, 0px));
        background: var(--card);
        border-top: 1px solid var(--border-2);
        gap: 8px;
      }
      .modal-foot .btn { flex: 1; padding: 12px; }
      .modal-foot > span { display: none; }   /* flex spacer collapses; buttons fill the row */

      .cust-metrics { grid-template-columns: 1fr 1fr; }
      .cm-cell:nth-child(3) { grid-column: 1 / -1; border-left: 0; border-top: 1px solid var(--border); }

      /* ---------------- Toasts (clear the fixed CTA) ---------------- */
      .toasts {
        left: 12px;
        right: 12px;
        bottom: calc(76px + env(safe-area-inset-bottom, 0px));
        max-width: none;
      }
    }

    /* Extra-tight phones */
    @media (max-width: 380px) {
      .topbar .seg.accent button { padding: 8px 10px; }
      .op-actions .dropdown,
      .op-actions > .btn { flex-basis: 100%; }   /* one action per row */
    }

    }
    </style>
    <style>
        [x-cloak]{display:none!important;}
        @media (max-width:680px){ #op-console .page{ padding-bottom:calc(84px + env(safe-area-inset-bottom,0px))!important; } }
        /* Hide the global app bottom nav on this page — the operation's own sticky
           action bar replaces it (same approach as the Rental editor / view rental).
           Keyed off body.gr-compact (the canonical compact-chrome signal), NOT a
           max-width media query, so it also hides on PORTRAIT tablets (iPad, up to
           1024px) where the bottom bar likewise renders. The nav hook renders at
           body.end with `body.gr-compact … !important`, so we double the page classes
           (.fi-page.fi-page) to outrank it and reclaim its reserved bottom padding. */
        body.gr-compact .gr-bottombar { display: none !important; }
        body.gr-compact.fi-body.fi-body { padding-bottom: 0 !important; }
        body.gr-compact .fi-main.fi-main, body.gr-compact .fi-page.fi-page { padding-bottom: 0 !important; }

        /* Blend with Filament content — drop the inset "window" page background */
        #op-console { background: transparent; }
        /* Sticky operation toolbar hugs the top of the content area (was 60px) */
        #op-console .op-toolbar { top: 0; }
        /* Clickable breadcrumb */
        #op-console .page-title .crumb { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        #op-console .page-title .crumb a { color: var(--muted); text-decoration: none; }
        #op-console .page-title .crumb a:hover { color: var(--text); text-decoration: underline; }
        #op-console .page-title .crumb .sep { color: var(--muted-2); }

        /* ============================================================
           Dark theme — follows Filament's admin dark mode (.dark on <html>).
           Strategy: re-map the design tokens first (covers most of the
           surface that uses var()s), then patch the handful of places
           that hardcode light values. Mirrors the design's styles-dark.css.
           ============================================================ */
        .dark #op-console {
            color-scheme: dark;
            /* neutral surfaces (page → inset → surface) */
            --bg:       #0f1217;
            --card-2:   #181c22;
            --card:     #1f242c;
            --border:   #2e333d;
            --border-2: #262b33;
            --ring:     rgba(255,255,255,.05);
            --text:    #eef1f5;
            --text-2:  #c3cad4;
            --muted:   #8b929e;
            --muted-2: #646b76;
            /* semantic tints (translucent so they sit on any dark bg) */
            --success:    #22c55e;
            --success-bg: rgba(34,197,94,.13);
            --success-bd: rgba(34,197,94,.30);
            --warning:    #f59e0b;
            --warning-bg: rgba(245,158,11,.13);
            --warning-bd: rgba(245,158,11,.30);
            --danger:     #ef4444;
            --danger-bg:  rgba(239,68,68,.13);
            --danger-bd:  rgba(239,68,68,.32);
            --info:       #3b82f6;
            --info-bg:    rgba(59,130,246,.13);
            --info-bd:    rgba(59,130,246,.30);
            /* deeper shadows on dark */
            --shadow-sm: 0 1px 2px rgba(0,0,0,.40);
            --shadow-md: 0 6px 20px rgba(0,0,0,.50);
            --shadow-lg: 0 18px 50px rgba(0,0,0,.62);
        }

        /* ---- glass / sticky bars (hardcoded light rgba) ---- */
        .dark #op-console .topbar     { background: rgba(15,18,23,.80); }
        .dark #op-console .op-toolbar { background: rgba(17,20,26,.85); }
        .dark #op-console .actionbar  { background: rgba(15,18,23,.90); }

        /* ---- buttons / hover borders ---- */
        .dark #op-console .btn:hover { border-color: #3a414c; }
        .dark #op-console .dropdown button[aria-expanded="true"] { border-color: #3a414c; }
        .dark #op-console .scan-btn { border-color: var(--border); }

        /* ---- badges (lighten saturated text on dark tints) ---- */
        .dark #op-console .b-gray    { background: rgba(255,255,255,.06); color: #c3cad4; border-color: var(--border); }
        .dark #op-console .b-success { color: #4ade80; }
        .dark #op-console .b-warning { color: #fbbf24; }
        .dark #op-console .b-danger  { color: #f87171; }
        .dark #op-console .b-info    { color: #60a5fa; }

        /* ---- banners ---- */
        .dark #op-console .banner-danger  { color: #fca5a5; }
        .dark #op-console .banner-warning { color: #fcd34d; }
        .dark #op-console .banner-success { color: #86efac; }
        .dark #op-console .banner-info    { color: #93c5fd; }

        /* ---- conflict banner ---- */
        .dark #op-console .cb-headtx strong { color: #fca5a5; }
        .dark #op-console .cb-headtx span   { color: #d7a0a0; }
        .dark #op-console .cb-status        { color: #fca5a5; }

        /* ---- filter chips ---- */
        .dark #op-console .chip:hover { border-color: #3a414c; }
        .dark #op-console .chip .count { background: rgba(255,255,255,.09); }
        .dark #op-console .chip[aria-pressed="true"] { color: var(--bg); }
        .dark #op-console .chip[aria-pressed="true"] .count { background: rgba(0,0,0,.18); }

        /* ---- progress track ---- */
        .dark #op-console .progress-track { background: #2a2f38; }

        /* ---- thumbnails / focus photo placeholders ---- */
        .dark #op-console .row .thumb {
            background: repeating-linear-gradient(45deg, #20242c, #20242c 6px, #262b34 6px, #262b34 12px);
        }
        .dark #op-console .fc-photo {
            background: repeating-linear-gradient(45deg, #20242c, #20242c 8px, #262b34 8px, #262b34 16px);
        }
        .dark #op-console .row .thumb .cam-badge { border-color: var(--card); }

        /* ---- check status (hollow circle) ---- */
        .dark #op-console .check-ic.off { background: var(--card); }

        /* ---- condition mini badges ---- */
        .dark #op-console .cond.good   { color: #4ade80; }
        .dark #op-console .cond.minor  { color: #fbbf24; }
        .dark #op-console .cond.broken { color: #f87171; }
        .dark #op-console .cond.lost   { color: #9ca3af; }

        /* ---- condition segmented (big editor buttons) ---- */
        .dark #op-console .cond-btn[aria-pressed="true"].c-lost {
            border-color: #6b7280; background: rgba(255,255,255,.06); color: #cbd1d9;
        }

        /* ---- photo capture slots ---- */
        .dark #op-console .photo-slot { border-color: var(--border); }
        .dark #op-console .photo-slot.filled .fill {
            background: repeating-linear-gradient(135deg,
                rgba(59,130,246,.22), rgba(59,130,246,.22) 7px,
                rgba(59,130,246,.10) 7px, rgba(59,130,246,.10) 14px);
            color: #93c5fd;
        }

        /* ---- metrics ok value ---- */
        .dark #op-console .cm-cell .cm-v.ok { color: #4ade80; }

        /* ---- inputs ---- */
        .dark #op-console .money-input input { color: var(--text); }

        /* ---- toast + modal scrim ---- */
        .dark #op-console .toast { background: #2a2f38; }
        .dark #op-console .scrim { background: rgba(0,0,0,.60); }

        /* ---- mobile sticky action bar (phone breakpoint) ---- */
        @media (max-width: 680px) {
            .dark #op-console .mobile-actionbar { box-shadow: 0 -8px 26px rgba(0,0,0,.55); }
            .dark #op-console .mobile-actionbar .mab-primary:disabled,
            .dark #op-console .mobile-actionbar .mab-primary[aria-disabled="true"] {
                background: #3a414c; border-color: #3a414c; color: #8b929e;
            }
        }
    </style>

    <div id="op-console" data-density="comfortable"
         x-data="{ filter:'all', showProfile:false, showMore:false, showValidate:false, showPartial:false }">
        <div class="page" style="padding:0;max-width:none;">
            {{-- Page head --}}
            <div class="page-head">
                <div class="page-title">
                    <span class="crumb">
                        <a href="{{ \App\Filament\Resources\Rentals\RentalResource::getUrl('index') }}">Rentals</a>
                        <span class="sep">/</span>
                        <a href="{{ \App\Filament\Resources\Rentals\RentalResource::getUrl('view', ['record' => $rental]) }}">{{ $rental->rental_code }}</a>
                        <span class="sep">/</span>
                        <b>Return</b>
                    </span>
                    <h1>{!! $icon('refresh') !!}Return Operation</h1>
                </div>
            </div>

            {{-- Operation toolbar (desktop) --}}
            <div class="op-toolbar">
                <span class="op-spacer"></span>
                <span class="op-status {{ $allChecked ? 'ok' : '' }}">
                    {!! $icon($allChecked ? 'checkCircle' : 'alertCircle') !!}
                    {{ $allChecked ? 'All checked' : "{$remaining} left" }}
                </span>
                <div class="op-actions">
                    <div class="dropdown" x-data="{open:false}" @click.outside="open=false">
                        <button class="btn" @click="open=!open" :aria-expanded="open">{!! $icon('chat') !!}Send{!! $icon('chevron', 'btn-chev') !!}</button>
                        <div class="menu" x-show="open" x-cloak>
                            @if ($waUrl)
                                <a class="menu-item" href="{{ $waUrl }}" target="_blank" @click="open=false">{!! $icon('chat') !!}<span class="mi-main"><span class="mi-t">WhatsApp reminder</span><span class="mi-d">Return reminder with checklist link</span></span></a>
                            @else
                                <button class="menu-item" disabled style="opacity:.5;">{!! $icon('chat') !!}<span class="mi-main"><span class="mi-t">WhatsApp reminder</span><span class="mi-d">Customer phone missing / disabled</span></span></button>
                            @endif
                            <button class="menu-item" disabled style="opacity:.5;">{!! $icon('mail') !!}<span class="mi-main"><span class="mi-t">Email reminder</span><span class="mi-d">Coming soon</span></span></button>
                        </div>
                    </div>
                    <div class="dropdown" x-data="{open:false}" @click.outside="open=false">
                        <button class="btn" @click="open=!open" :aria-expanded="open">{!! $icon('printer') !!}Print{!! $icon('chevron', 'btn-chev') !!}</button>
                        <div class="menu" x-show="open" x-cloak>
                            <button class="menu-item" wire:click="downloadChecklist" @click="open=false">{!! $icon('list') !!}<span class="mi-main"><span class="mi-t">Checklist form</span><span class="mi-d">Item-by-item condition checklist</span></span></button>
                            <button class="menu-item" wire:click="downloadDeliveryNote" @click="open=false">{!! $icon('truck') !!}<span class="mi-main"><span class="mi-t">Delivery note</span><span class="mi-d">Return / hand-back document</span></span></button>
                        </div>
                    </div>
                    <a class="btn" href="{{ $this->deliveryDocsUrl() }}">{!! $icon('doc') !!}Delivery</a>
                    <button class="btn btn-success" @click="{{ $allChecked ? 'showValidate=true' : 'showPartial=true' }}">{!! $icon('truck') !!}{{ $allChecked ? 'Validate Return' : 'Partial Return' }}</button>
                </div>
            </div>

            {{-- Rental info card --}}
            <div class="card rental-card">
                <div class="rental-id">
                    <button class="ri-trigger" @click="showProfile=true" title="View customer profile">
                        <span class="ri-avatar">{{ $initials }}</span>
                        <span class="ri-who">
                            <span class="ri-name">{{ $cust->name }}{!! $icon('chevron', 'ri-chev') !!}</span>
                            <span class="ri-sub">
                                <span class="mono ri-code">{{ $rental->rental_code }}</span>
                                <span class="ri-phone">{!! $icon('phone') !!}{{ $cust->phone ?? '-' }}</span>
                            </span>
                        </span>
                    </button>
                    <span class="badge b-success"><span class="dot"></span>{{ ucfirst(str_replace('_', ' ', $rental->status)) }}</span>
                </div>
                <div class="rental-stats">
                    <div class="rs-cell">
                        <span class="rs-k">{!! $icon('truck') !!}Start</span>
                        <span class="rs-v">{{ $rental->start_date?->format('d M Y · H:i') }}</span>
                    </div>
                    <div class="rs-cell">
                        <span class="rs-k">{!! $icon('calendar') !!}Return</span>
                        <span class="rs-v">{{ $rental->end_date?->format('d M Y · H:i') }}@if ($isLate)<span class="badge b-danger">late</span>@endif</span>
                    </div>
                    <div class="rs-cell">
                        <span class="rs-k">{!! $icon('shield') !!}Deposit</span>
                        <span class="rs-v">Rp {{ number_format($rental->security_deposit_amount, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            {{-- Checklist --}}
            <div class="card">
                <div class="card-head">
                    <h2>{!! $icon('list') !!}Return Checklist</h2>
                </div>

                <div class="card-body" style="padding-bottom:12px;">
                    <button type="button" class="scan-btn" @click="$dispatch('open-unit-scanner')">
                        <span class="scan-ic">{!! $icon('scan') !!}</span>
                        <span class="scan-tx">Scan QR/Barcode</span>
                    </button>
                </div>

                <div class="toolbar">
                    <div class="progress-wrap">
                        <span class="progress-num"><b>{{ $checkedCount }}</b> / {{ $total }} checked</span>
                        <div class="progress-track">
                            <div class="progress-fill {{ $checkedCount < $total ? 'warn' : '' }}" style="width: {{ $total ? round($checkedCount / $total * 100) : 0 }}%"></div>
                        </div>
                    </div>
                    <div class="chips">
                        <button class="chip" :aria-pressed="filter==='all'" @click="filter='all'">All <span class="count">{{ $total }}</span></button>
                        <button class="chip" :aria-pressed="filter==='unchecked'" @click="filter='unchecked'">{!! $icon('filter') !!}Unchecked <span class="count">{{ $uncheckedCount }}</span></button>
                        <button class="chip {{ $issuesCount ? 'chip-danger' : '' }}" :aria-pressed="filter==='issues'" @click="filter='issues'">{!! $icon('alert') !!}Issues <span class="count">{{ $issuesCount }}</span></button>
                    </div>
                    <button class="btn btn-sm mark-all" wire:click="markAllChecked" @disabled($allChecked)>{!! $icon('checkCircle') !!}Mark all checked</button>
                </div>

                <div class="rows">
                    @forelse ($items as $item)
                        @php
                            $isKit = $item->rentalItemKit !== null;
                            $name = $this->itemLabel($item);
                            $sn = $isKit ? ($item->rentalItemKit->unitKit->serial_number ?? '-') : $item->rentalItem->productUnit->serial_number;
                            $issue = $isIssue($item);
                            $photoCount = is_array($item->photos) ? count($item->photos) : 0;
                            $tone = $item->condition ? ($conditionMeta[$item->condition]['tone'] ?? null) : null;
                            $condLabel = $item->condition ? ($conditionMeta[$item->condition]['label'] ?? ucfirst($item->condition)) : null;
                        @endphp
                        <div class="row {{ $isKit ? 'is-kit' : '' }} {{ $item->is_checked ? 'checked' : '' }}"
                             wire:key="row-{{ $item->id }}"
                             x-show="filter==='all' || (filter==='unchecked' && {{ $item->is_checked ? 'false' : 'true' }}) || (filter==='issues' && {{ $issue ? 'true' : 'false' }})">
                            <div class="thumb {{ $photoCount ? 'has-photo' : '' }}">
                                {!! $icon($isKit ? 'cube' : 'layers') !!}
                                @if ($photoCount)<span class="cam-badge">{!! $icon('camera') !!}</span>@endif
                            </div>
                            <div class="main">
                                <div class="name">
                                    @if ($isKit)<span class="muted">↳</span>@endif
                                    {{ $name }}
                                    <span class="badge b-{{ $isKit ? 'gray' : 'primary' }}">{{ $isKit ? 'Kit' : 'Unit' }}</span>
                                </div>
                                <div class="meta"><span class="sn">{{ $sn }}</span></div>
                            </div>
                            <div class="right">
                                @if ($condLabel)
                                    <span class="cond {{ $tone }}"><span class="dot"></span>{{ $condLabel }}</span>
                                @else
                                    <span class="cond none"><span class="dot"></span>Not checked</span>
                                @endif
                                <span class="check-ic {{ $item->is_checked ? 'on' : 'off' }}">{!! $icon($item->is_checked ? 'check' : 'x') !!}</span>
                                <div class="row-actions">
                                    <button class="btn btn-sm btn-icon" title="Edit condition & photos" wire:click="openEditor({{ $item->id }})">{!! $icon('edit') !!}</button>
                                    @if ($item->is_checked)
                                        <button class="btn btn-sm" title="Undo check" wire:click="uncheckItem({{ $item->id }})">Undo</button>
                                    @else
                                        <button class="btn btn-sm btn-success" wire:click="quickCheck({{ $item->id }})">{!! $icon('check') !!}Check</button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="empty">
                            {!! $icon('checkCircle') !!}
                            <div class="et">No items</div>
                            <div class="ed">This delivery has no items to check.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Editor modal --}}
        @if ($editItem)
            @php
                $isKit = $editItem->rentalItemKit !== null;
                $editName = $this->itemLabel($editItem);
                $editSn = $isKit ? ($editItem->rentalItemKit->unitKit->serial_number ?? '-') : $editItem->rentalItem->productUnit->serial_number;
            @endphp
            <div class="scrim" wire:click.self="closeEditor">
                <div class="modal" wire:key="editor-{{ $editingId }}">
                    <div class="modal-head">
                        <h3>{!! $icon('edit') !!}{{ $editName }}</h3>
                        <p>{{ $editSn }} · Record condition on return</p>
                    </div>
                    <div class="modal-body">
                        <div class="field full">
                            <span class="field-label">Condition (on return)</span>
                            <div class="cond-seg">
                                @foreach ($conditionOptions as $key => $label)
                                    @php $t = $conditionMeta[$key]['tone'] ?? 'good'; @endphp
                                    <button type="button" class="cond-btn c-{{ $t }}" aria-pressed="{{ $editCondition === $key ? 'true' : 'false' }}" wire:click="$set('editCondition', '{{ $key }}')">
                                        {!! $icon($conditionMeta[$key]['icon'] ?? 'check') !!}{{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="field full">
                            <span class="field-label">Evidence photos @if (count($editExistingPhotos))<span class="muted">· {{ count($editExistingPhotos) }}</span>@endif</span>
                            <div class="photos">
                                @foreach ($editExistingPhotos as $i => $path)
                                    <div class="photo-slot filled" wire:key="photo-{{ $i }}-{{ md5($path) }}">
                                        <div class="fill" style="background:none;"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($path) }}" style="width:100%;height:100%;object-fit:cover;" alt="evidence" /></div>
                                        <button type="button" class="rm" title="Remove" wire:click="removeExistingPhoto({{ $i }})">{!! $icon('x') !!}</button>
                                    </div>
                                @endforeach
                                <label class="photo-slot" title="Capture / upload evidence photo">
                                    <div wire:loading.remove wire:target="editPhotos">{!! $icon('camera') !!}</div>
                                    <div wire:loading wire:target="editPhotos">{!! $icon('refresh', 'spin') !!}</div>
                                    <input type="file" accept="image/*" capture="environment" multiple wire:model="editPhotos" style="display:none;" />
                                </label>
                            </div>
                            @error('editPhotos.*')<span class="muted" style="font-size:12px;color:var(--danger);">{{ $message }}</span>@enderror
                            @if (count($editPhotos))<span class="muted" style="font-size:12px;">{{ count($editPhotos) }} new photo(s) ready to save.</span>@endif
                        </div>

                        <div class="field full">
                            <span class="field-label">Notes</span>
                            <textarea class="textarea" placeholder="Detail about condition, accessories, serial mismatch…" wire:model="editNotes"></textarea>
                        </div>
                    </div>
                    <div class="modal-foot">
                        @if ($editItem->is_checked)
                            <button class="btn" wire:click="uncheckItem({{ $editingId }})">{!! $icon('x') !!}Undo check</button>
                        @endif
                        <span style="flex:1"></span>
                        <button class="btn" wire:click="closeEditor">Cancel</button>
                        <button class="btn btn-success" wire:click="saveEditor" wire:loading.attr="disabled" wire:target="saveEditor,editPhotos">
                            {!! $icon('check') !!}{{ $editItem->is_checked ? 'Save changes' : 'Save & check' }}
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Settlement modal (all items checked) --}}
        <template x-if="showValidate">
            <div class="scrim" @click.self="showValidate=false" @keydown.escape.window="showValidate=false">
                <div class="modal wide"
                     x-data="{
                        lateFee: {{ (float) $fin['late_fee'] }},
                        deposit: {{ (float) $fin['deposit'] }},
                        depAction: 'refund',
                        refund: {{ (float) $fin['deposit'] }},
                        maintCount: 0,
                        fmt(n){ return 'Rp ' + (Number(n)||0).toLocaleString('id-ID'); },
                        get depositOut(){ return this.depAction==='refund' ? this.deposit : (this.depAction==='partial' ? Number(this.refund) : 0); },
                        get net(){ return this.depositOut - Number(this.lateFee); }
                     }"
                     x-init="$wire.maintenanceSummary().then(r => { maintCount = r.count; })">
                    <div class="modal-head">
                        <h3>{!! $icon('cash') !!}Confirm return &amp; settlement</h3>
                        <p>Recognize revenue, settle the late fee and security deposit, then complete the rental.</p>
                    </div>
                    <div class="modal-body">
                        <template x-if="maintCount > 0">
                            <div class="banner banner-danger">{!! $icon('alert') !!}<div class="body"><strong x-text="maintCount + (maintCount === 1 ? ' item' : ' items') + ' returned broken/lost.'"></strong> Affected units auto-moved to Maintenance.</div></div>
                        </template>

                        <div>
                            <span class="field-label" style="display:block;margin-bottom:6px;">Late fee</span>
                            <div class="money-input"><span class="pfx">Rp</span><input type="number" x-model.number="lateFee" /></div>
                            <span class="muted" style="font-size:12px;">Auto-calculated {{ 'Rp ' . number_format($fin['late_fee'], 0, ',', '.') }}. Adjust if waived.</span>

                            @php($lfb = $fin['late_fee_breakdown'] ?? null)
                            @if ($lfb && $lfb['is_late'] && count($lfb['lines']))
                                <details open style="margin-top:8px;border:1px solid var(--border-2);border-radius:10px;padding:8px 12px;">
                                    <summary style="cursor:pointer;font-size:12px;font-weight:600;color:var(--text-2);">Rincian perhitungan late fee</summary>
                                    <div style="margin-top:10px;">
                                        <p class="muted" style="font-size:11.5px;line-height:1.5;margin:0 0 10px;">
                                            Telat <strong>{{ $lfb['hours_late'] }} jam</strong> (dibulatkan ke atas = {{ $lfb['overdue_days'] }} hari penuh) · jatuh tempo {{ $lfb['end_date'] }} → kini {{ $lfb['now'] }}.<br>
                                            Metode: <strong>{{ $lfb['mode_label'] }}</strong>@if ($lfb['amount_setting'] > 0) · nominal setelan Rp {{ number_format($lfb['amount_setting'], 0, ',', '.') }}@endif.
                                        </p>

                                        @foreach ($lfb['lines'] as $line)
                                            <div style="display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid var(--border-2);">
                                                <span style="font-size:12.5px;color:var(--text-2);">
                                                    {{ $line['label'] }}<br>
                                                    <span class="muted" style="font-size:11px;">{{ $line['detail'] }}</span>
                                                </span>
                                                <span style="font-size:13px;font-weight:700;white-space:nowrap;">Rp {{ number_format($line['amount'], 0, ',', '.') }}</span>
                                            </div>
                                        @endforeach

                                        @if ($lfb['summary'])
                                            <p class="muted" style="font-size:11px;font-style:italic;margin:8px 0 0;">{{ $lfb['summary'] }}</p>
                                        @endif

                                        <div style="display:flex;justify-content:space-between;gap:12px;margin-top:8px;padding-top:8px;border-top:2px solid var(--border);">
                                            <span style="font-size:12.5px;font-weight:700;color:var(--text-2);">Total auto late fee</span>
                                            <span style="font-size:14px;font-weight:800;color:var(--accent-700);">Rp {{ number_format($lfb['fee'], 0, ',', '.') }}</span>
                                        </div>
                                    </div>
                                </details>
                            @endif
                        </div>

                        @if ($fin['can_settle_deposit'])
                            <div>
                                <span class="field-label" style="display:block;margin-bottom:6px;">Security deposit · {{ 'Rp ' . number_format($fin['deposit'], 0, ',', '.') }} held</span>
                                <div class="radio-cards">
                                    <button type="button" class="radio-card" :aria-pressed="depAction==='refund'" @click="depAction='refund'"><span class="rc-dot"></span><span class="rc-main"><span class="rc-t">Full refund</span><span class="rc-d">Return the entire deposit to the customer.</span></span></button>
                                    <button type="button" class="radio-card" :aria-pressed="depAction==='forfeit'" @click="depAction='forfeit'"><span class="rc-dot"></span><span class="rc-main"><span class="rc-t">Forfeit</span><span class="rc-d">Keep deposit as penalty / revenue.</span></span></button>
                                    <button type="button" class="radio-card" :aria-pressed="depAction==='partial'" @click="depAction='partial'"><span class="rc-dot"></span><span class="rc-main"><span class="rc-t">Partial refund</span><span class="rc-d">Refund part, forfeit the rest.</span></span></button>
                                </div>
                                <div style="margin-top:8px;" x-show="depAction==='partial'" x-cloak>
                                    <div class="money-input"><span class="pfx">Rp</span><input type="number" x-model.number="refund" max="{{ (float) $fin['deposit'] }}" /></div>
                                    <span class="muted" style="font-size:12px;">Refund amount (max {{ 'Rp ' . number_format($fin['deposit'], 0, ',', '.') }}).</span>
                                </div>
                            </div>
                        @endif

                        <hr class="hr" />
                        <div>
                            <div class="sum-row"><span class="sk">Rental revenue recognized</span><span class="sv">{{ 'Rp ' . number_format($fin['rental_revenue'], 0, ',', '.') }}</span></div>
                            <div class="sum-row"><span class="sk">Late fee charged</span><span class="sv" x-text="Number(lateFee)>0 ? '+ '+fmt(lateFee) : '—'"></span></div>
                            <div class="sum-row"><span class="sk">Deposit refunded</span><span class="sv" x-text="depositOut>0 ? fmt(depositOut) : '—'"></span></div>
                            <div class="sum-row total"><span class="sk">Net to customer</span><span class="sv" x-text="net>=0 ? fmt(net) : '('+fmt(Math.abs(net))+' due)'"></span></div>
                        </div>
                    </div>
                    <div class="modal-foot">
                        <span style="flex:1"></span>
                        <button class="btn" @click="showValidate=false">Cancel</button>
                        <button class="btn btn-success" @click="$wire.validateReturn({ manual_late_fee: lateFee, final_deposit_action: depAction, refund_amount: refund }); showValidate=false">{!! $icon('checkCircle') !!}Validate return</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Partial return confirm modal --}}
        <template x-if="showPartial">
            <div class="scrim" @click.self="showPartial=false" @keydown.escape.window="showPartial=false">
                <div class="modal">
                    <div class="modal-head"><h3>{!! $icon('layers') !!}Process partial return</h3><p>{{ $checkedCount }} of {{ $total }} items checked. Unchecked items move to a new return checklist; the rental stays in Partial Return.</p></div>
                    <div class="modal-body">
                        <div class="banner banner-warning">{!! $icon('alert') !!}<div class="body"><strong>Partial return.</strong> Settlement runs only when every item is checked. The remaining {{ $remaining }} item(s) will be carried over to a new checklist.</div></div>
                    </div>
                    <div class="modal-foot">
                        <span style="flex:1"></span>
                        <button class="btn" @click="showPartial=false">Cancel</button>
                        <button class="btn btn-primary" @click="$wire.validateReturn({}); showPartial=false">{!! $icon('checkCircle') !!}Process partial return</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Customer profile modal --}}
        <template x-if="showProfile">
            <div class="scrim" @click.self="showProfile=false" @keydown.escape.window="showProfile=false">
                <div class="modal wide">
                    <div class="modal-head">
                        <div class="cust-head">
                            <span class="cust-avatar">{{ $initials }}</span>
                            <div class="cust-id">
                                <div class="cust-name">{{ $cust->name }}</div>
                                <div class="cust-tags"><span class="badge b-success"><span class="dot"></span>{{ ucfirst(str_replace('_', ' ', $rental->status)) }}</span></div>
                                <div class="cust-since">#{{ $cust->id }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-body">
                        <div class="cust-contact">
                            <div class="cc-row"><span class="cc-ic">{!! $icon('phone') !!}</span><span class="cc-k">Phone</span><span class="cc-v">{{ $cust->phone ?? '-' }}</span></div>
                            <div class="cc-row"><span class="cc-ic">{!! $icon('mail') !!}</span><span class="cc-k">Email</span><span class="cc-v">{{ $cust->email ?? '-' }}</span></div>
                            <div class="cc-row"><span class="cc-ic">{!! $icon('pin') !!}</span><span class="cc-k">Address</span><span class="cc-v">{{ $cust->address ?? '-' }}</span></div>
                        </div>
                        <div class="cust-hist">
                            <div class="ch-head">{!! $icon('clock') !!}Rental history<span class="ch-count">{{ $history->count() }}</span></div>
                            <div class="ch-list">
                                @foreach ($history as $h)
                                    <a class="ch-item" href="{{ \App\Filament\Resources\Rentals\RentalResource::getUrl('view', ['record' => $h]) }}">
                                        <div class="ch-main">
                                            <span class="ch-code mono">{{ $h->rental_code }}</span>
                                            <span class="ch-meta">{{ $h->start_date?->format('d M Y') }} · {{ $h->items_count }} item{{ $h->items_count > 1 ? 's' : '' }}</span>
                                        </div>
                                        <span class="ch-amt">Rp {{ number_format($h->total, 0, ',', '.') }}</span>
                                        <span class="badge b-{{ $histTone[$h->status] ?? 'gray' }}"><span class="dot"></span>{{ ucfirst(str_replace('_', ' ', $h->status)) }}</span>
                                        {!! $icon('chevron', 'ch-go') !!}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="modal-foot">
                        <span style="flex:1"></span>
                        <a class="btn" href="{{ $this->customerUrl() }}">{!! $icon('user') !!}Open customer</a>
                        <button class="btn btn-primary" @click="showProfile=false">Close</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Mobile sticky action bar --}}
        <div class="mobile-actionbar">
            <button class="btn mab-more" aria-label="More actions" @click="showMore=true">{!! $icon('dots') !!}</button>
            <button class="btn btn-success mab-primary" @click="{{ $allChecked ? 'showValidate=true' : 'showPartial=true' }}">{!! $icon('truck') !!}{{ $allChecked ? 'Validate Return' : 'Partial Return' }}</button>
        </div>

        {{-- Mobile action sheet --}}
        <template x-if="showMore">
            <div class="scrim" @click.self="showMore=false" @keydown.escape.window="showMore=false">
                <div class="modal">
                    <div class="modal-head"><h3>{!! $icon('bolt') !!}Operation actions</h3><p>Rental {{ $rental->rental_code }} · Return</p></div>
                    <div class="modal-body">
                        <div class="act-sheet">
                            <div class="act-group">
                                <div class="act-label">{!! $icon('chat') !!}Send to customer</div>
                                @if ($waUrl)
                                    <a class="act-item" href="{{ $waUrl }}" target="_blank" @click="showMore=false"><span class="act-ic">{!! $icon('chat') !!}</span><span class="act-tx"><b>WhatsApp reminder</b><small>Return reminder with checklist link</small></span>{!! $icon('arrowRight', 'act-chev') !!}</a>
                                @endif
                            </div>
                            <div class="act-group">
                                <div class="act-label">{!! $icon('printer') !!}Print documents</div>
                                <button class="act-item" wire:click="downloadChecklist" @click="showMore=false"><span class="act-ic">{!! $icon('list') !!}</span><span class="act-tx"><b>Checklist form</b><small>Item-by-item condition checklist</small></span>{!! $icon('arrowRight', 'act-chev') !!}</button>
                                <button class="act-item" wire:click="downloadDeliveryNote" @click="showMore=false"><span class="act-ic">{!! $icon('truck') !!}</span><span class="act-tx"><b>Delivery note</b><small>Return / hand-back document</small></span>{!! $icon('arrowRight', 'act-chev') !!}</button>
                            </div>
                            <div class="act-group">
                                <a class="act-item" href="{{ $this->deliveryDocsUrl() }}"><span class="act-ic">{!! $icon('doc') !!}</span><span class="act-tx"><b>Delivery documents</b><small>Surat jalan &amp; related docs</small></span>{!! $icon('arrowRight', 'act-chev') !!}</a>
                            </div>
                        </div>
                    </div>
                    <div class="modal-foot"><button class="btn" @click="showMore=false">Close</button></div>
                </div>
            </div>
        </template>

        {{-- Camera scanner popup (desktop modal + mobile sheet) --}}
        @include('filament.resources.rentals.pages.partials.scanner-popup', ['mode' => 'return'])
    </div>
</x-filament-panels::page>
