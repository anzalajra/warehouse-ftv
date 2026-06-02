@php
    use Illuminate\Support\Facades\Storage;

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

    // Coupon discount (mirrors old view + edit logic)
    if ($rental->discount_type === 'percent') {
        $baseDiscount = ($rental->subtotal ?? 0) * (($rental->discount ?? 0) / 100);
    } else {
        $baseDiscount = $rental->discount ?? 0;
    }

    $rp = fn ($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
@endphp

<x-filament-panels::page>
    <div class="rent-app rent-view">
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

            @media (max-width: 760px) {
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
        </style>

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
                        @if($customerUrl)
                            <a href="{{ $customerUrl }}" class="iv-link">{{ $customer?->name ?? '—' }}</a>
                        @else
                            {{ $customer?->name ?? '—' }}
                        @endif
                        @if($customer && method_exists($customer, 'isRedNotice') && $customer->isRedNotice())
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
                @if($baseDiscount > 0)
                    <div class="frow disc">
                        <span class="fl">Kupon Diskon</span>
                        <span class="fv">− {{ $rp($baseDiscount) }}</span>
                    </div>
                @endif
                @if($rental->daily_discount_amount > 0)
                    <div class="frow disc">
                        <span class="fl">Diskon Promo Harian</span>
                        <span class="fv">− {{ $rp($rental->daily_discount_amount) }}</span>
                    </div>
                @endif
                @if($rental->date_promotion_amount > 0)
                    <div class="frow disc">
                        <span class="fl">Diskon Promo Tanggal</span>
                        <span class="fv">− {{ $rp($rental->date_promotion_amount) }}</span>
                    </div>
                @endif
                <div class="frow grand">
                    <span class="fl">Total</span>
                    <span class="fv">{{ $rp($rental->total) }}</span>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
