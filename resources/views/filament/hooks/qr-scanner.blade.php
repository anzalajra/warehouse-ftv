{{--
    Global admin QR / barcode scanner (Filament topbar / sidebar-footer button).
    Same look & feel as the Pickup/Return unit scanner — shares the .scn-* styles
    (filament.scanner-styles). Decoding uses html5-qrcode loaded from CDN (no Vite
    build needed), wrapped in an inline Alpine component. Single-shot: decode →
    /admin/scan-resolve → open the product's catalog edit page (or a same-origin
    document URL directly).
--}}
@php
    // Inline icon helper (same glyph set as the operation blades).
    $icon = function (string $name): string {
        $paths = [
            'scan'        => 'M4 7V5a1 1 0 011-1h2M20 7V5a1 1 0 00-1-1h-2M4 17v2a1 1 0 001 1h2M20 17v2a1 1 0 01-1 1h-2M3 12h18',
            'x'           => 'M6 6l12 12M18 6L6 18',
            'camera'      => 'M3 8a2 2 0 012-2h1.5l1-1.5h5l1 1.5H18a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2zM12 16.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z',
            'cameraOff'   => 'M3 3l18 18M9.5 5h4l1 1.5H19a2 2 0 012 2v7M3 8.5V17a2 2 0 002 2h11',
            'keyboard'    => 'M3 6h18v12H3zM7 10h.01M11 10h.01M15 10h.01M8 14h8',
            'lock'        => 'M5 11h14v9H5zM8 11V8a4 4 0 018 0v3',
            'refresh'     => 'M4 12a8 8 0 0114-5l2 2M20 12a8 8 0 01-14 5l-2-2M18 4v5h-5M6 20v-5h5',
            'spinner'     => 'M12 3a9 9 0 109 9',
            'check'       => 'M4.5 12.5l5 5 10-11',
            'checkCircle' => 'M9 12.5l2.2 2.2L15.5 10M12 21a9 9 0 100-18 9 9 0 000 18z',
            'alert'       => 'M12 9v4m0 4h.01M10.3 4.3L2.6 18a2 2 0 001.7 3h15.4a2 2 0 001.7-3L13.7 4.3a2 2 0 00-3.4 0z',
            'zap'         => 'M13 3L4 14h7l-1 7 9-11h-7z',
        ];
        $d = $paths[$name] ?? $paths['scan'];
        $segs = array_filter(explode('M', $d));
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
        foreach ($segs as $s) { $svg .= '<path d="M' . $s . '"></path>'; }
        return $svg . '</svg>';
    };
@endphp

@include('filament.scanner-styles')

<style>
    /* html5-qrcode injects a <video> into #scn-reader; make it fill the .scn-cam
       stage and hide the library's own scan-region overlay so only our finder shows. */
    .scn-cam #scn-reader { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
    .scn-cam #scn-reader video { width: 100% !important; height: 100% !important; object-fit: cover !important; display: block; }
    .scn-cam #scn-reader img { display: none !important; }
</style>

@once
<script>
(() => {
    const factory = (cfg) => ({
        resolveUrl: (cfg && cfg.resolveUrl) || '',
        open: false,
        phase: 'prompt',          // prompt | requesting | live | denied | blocked | nocamera | manual
        detectedName: null,
        errorHint: null,
        flash: false,
        torch: false,
        torchSupported: false,
        manualVal: '',
        manualErr: '',
        busy: false,
        h5: null,
        lastCode: null,
        lastAt: 0,
        _hintTimer: null,

        init() { this.$watch('open', (v) => { if (!v) this.stopCam(); }); },

        openScanner() {
            this.detectedName = null; this.errorHint = null;
            this.manualVal = ''; this.manualErr = ''; this.busy = false;
            this.phase = 'prompt'; this.open = true;
        },
        close() { this.stopCam(); this.open = false; this.phase = 'prompt'; this.detectedName = null; this.errorHint = null; },

        loadLib() {
            return new Promise((resolve, reject) => {
                if (window.Html5Qrcode) return resolve();
                const s = document.createElement('script');
                s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js';
                s.onload = () => resolve();
                s.onerror = () => reject(new Error('lib'));
                document.head.appendChild(s);
            });
        },

        async requestCamera() {
            this.phase = 'requesting';
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { this.phase = 'nocamera'; return; }
            try { await this.loadLib(); } catch (e) { this.phase = 'denied'; return; }
            this.phase = 'live';
            this.$nextTick(async () => {
                try {
                    const fmts = window.Html5QrcodeSupportedFormats
                        ? [Html5QrcodeSupportedFormats.QR_CODE, Html5QrcodeSupportedFormats.CODE_128]
                        : undefined;
                    this.h5 = new Html5Qrcode('scn-reader', fmts ? { verbose: false, formatsToSupport: fmts } : { verbose: false });
                    await this.h5.start(
                        { facingMode: { ideal: 'environment' } },
                        { fps: 10, videoConstraints: {
                            facingMode: { ideal: 'environment' },
                            width: { ideal: 1920 }, height: { ideal: 1080 },
                            focusMode: 'continuous', advanced: [{ focusMode: 'continuous' }],
                        } },
                        (txt) => this.onDecode(txt),
                        () => {}
                    );
                    try { const caps = this.h5.getRunningTrackCapabilities(); this.torchSupported = !!(caps && caps.torch); } catch (e) {}
                } catch (e) {
                    const n = ((e && e.name) || '') + ' ' + ((e && e.message) || '');
                    const low = n.toLowerCase();
                    if (low.includes('notfound') || low.includes('no camera') || low.includes('overconstrained')) this.phase = 'nocamera';
                    else if (low.includes('notallowed') || low.includes('permission') || low.includes('denied') || low.includes('security')) this.phase = (await this.probeBlocked()) ? 'blocked' : 'denied';
                    else this.phase = 'denied';
                }
            });
        },
        async probeBlocked() {
            try { if (navigator.permissions && navigator.permissions.query) { const st = await navigator.permissions.query({ name: 'camera' }); return st.state === 'denied'; } } catch (e) {}
            return false;
        },
        async stopCam() {
            if (this.h5) { try { await this.h5.stop(); } catch (e) {} try { this.h5.clear(); } catch (e) {} this.h5 = null; }
            this.torch = false; this.torchSupported = false;
        },
        async toggleTorch() {
            if (!this.h5) return;
            this.torch = !this.torch;
            try { await this.h5.applyVideoConstraints({ advanced: [{ torch: this.torch }] }); } catch (e) { this.torch = false; }
        },
        goManual() { this.stopCam(); this.manualErr = ''; this.phase = 'manual'; this.$nextTick(() => { if (this.$refs.manualInput) this.$refs.manualInput.focus(); }); },
        backToCamera() { this.requestCamera(); },

        onDecode(text) {
            if (!text || this.busy) return;
            const now = Date.now();
            if (text === this.lastCode && now - this.lastAt < 2500) return;
            this.lastCode = text; this.lastAt = now;
            try {
                const url = new URL(text);
                if (url.protocol === 'http:' || url.protocol === 'https:') {
                    if (url.origin === window.location.origin) this.succeed('Membuka dokumen…', text);
                    else this.showError('QR dari sistem lain (origin berbeda).');
                    return;
                }
            } catch (e) { /* not a URL → unit code */ }
            this.resolveCode(text);
        },
        resolveCode(code) {
            if (!this.resolveUrl) { this.showError('Resolver tidak tersedia.'); return; }
            this.busy = true;
            fetch(this.resolveUrl + '?code=' + encodeURIComponent(code), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((r) => r.json().then((data) => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.ok && data.url) this.succeed(data.label || 'Membuka produk…', data.url);
                    else { this.busy = false; this.showError((data && data.message) || 'QR tidak dikenali.'); }
                })
                .catch(() => { this.busy = false; this.showError('Gagal menghubungi server.'); });
        },
        succeed(label, href) {
            this.busy = true; this.errorHint = null; this.detectedName = label;
            this.flash = true; setTimeout(() => { this.flash = false; }, 420);
            setTimeout(() => { window.location.href = href; }, 750);
        },
        showError(msg) { clearTimeout(this._hintTimer); this.errorHint = msg; this._hintTimer = setTimeout(() => { this.errorHint = null; }, 2200); },
        submitManual() {
            const q = (this.manualVal || '').trim();
            if (!q || this.busy) return;
            if (!this.resolveUrl) { this.manualErr = 'Resolver tidak tersedia.'; return; }
            this.busy = true; this.manualErr = '';
            try {
                const url = new URL(q);
                if (url.protocol === 'http:' || url.protocol === 'https:') {
                    if (url.origin === window.location.origin) { window.location.href = q; return; }
                    this.busy = false; this.manualErr = 'URL dari sistem lain.'; return;
                }
            } catch (e) { /* not a URL */ }
            fetch(this.resolveUrl + '?code=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((r) => r.json().then((data) => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.ok && data.url) window.location.href = data.url;
                    else { this.busy = false; this.manualErr = (data && data.message) || 'Tidak ditemukan.'; }
                })
                .catch(() => { this.busy = false; this.manualErr = 'Gagal menghubungi server.'; });
        },
    });

    const register = () => {
        if (window.Alpine && !window.__globalScannerRegistered) {
            window.__globalScannerRegistered = true;
            window.Alpine.data('globalScanner', factory);
        }
    };
    document.addEventListener('alpine:init', register);
    register();
})();
</script>
@endonce

{{-- $hideTrigger: in sidebar mode the floating profile capsule provides the QR
     button (and dispatches `zw:scanner-open`), so the inline trigger is hidden.
     In top-nav (phone) mode the trigger is shown in the topbar. --}}
<div class="scn-host flex items-center"
     x-data="globalScanner({ resolveUrl: @js(route('admin.scan-resolve')) })"
     x-on:zw:scanner-open.window="openScanner()">

    @unless ($hideTrigger ?? false)
    {{-- Trigger (always visible; modal is gated by x-if so no x-cloak needed here) --}}
    <button
        @click="openScanner()"
        type="button"
        class="flex items-center justify-center w-10 h-10 text-gray-500 transition hover:text-primary-500 focus:outline-none dark:text-gray-400 dark:hover:text-primary-400"
        title="Scan QR / Barcode"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z" />
        </svg>
    </button>
    @endunless

    {{-- Scanner modal (same chrome as Pickup/Return) --}}
    <template x-if="open">
        <div class="scn-root is-desktop">
            <div class="scn-scrim" @click.self="close()" @keydown.escape.window="close()">
                <div class="scn-modal">

                    {{-- Header --}}
                    <div class="scn-head">
                        <div class="scn-iconbtn" aria-hidden="true" style="cursor:default;color:var(--scn-accent-2)">{!! $icon('scan') !!}</div>
                        <div class="scn-title">
                            <span class="scn-sub pickup">Inventaris</span>
                            <b>Scan QR / Barcode</b>
                        </div>
                        <button class="scn-iconbtn" @click="close()" aria-label="Tutup scanner">{!! $icon('x') !!}</button>
                    </div>

                    {{-- Live camera --}}
                    <div class="scn-cam" x-show="phase==='live'" x-cloak>
                        <div id="scn-reader"></div>

                        <div class="scn-live-chips">
                            <span class="scn-chip"><span class="live-dot"></span>AUTO</span>
                            <button class="scn-chip" :class="torch?'on':''" x-show="torchSupported" @click="toggleTorch()" aria-label="Senter">{!! $icon('zap') !!}</button>
                        </div>

                        <div class="scn-finder">
                            <div class="scn-frame" :class="detectedName?'ok':''">
                                <span class="corner tl"></span><span class="corner tr"></span><span class="corner bl"></span><span class="corner br"></span>
                                <span class="scn-line" x-show="!detectedName"></span>
                                <span class="scn-checkbadge" x-show="detectedName" x-cloak>{!! $icon('check') !!}</span>
                            </div>
                        </div>

                        <div class="scn-hint" :class="errorHint ? 'err' : ''">
                            <template x-if="detectedName">
                                <span style="display:inline-flex;align-items:center;gap:8px"><span style="color:var(--scn-success);display:inline-flex">{!! $icon('checkCircle') !!}</span><span x-text="detectedName"></span></span>
                            </template>
                            <template x-if="!detectedName && errorHint">
                                <span style="display:inline-flex;align-items:center;gap:8px">{!! $icon('alert') !!}<span x-text="errorHint"></span></span>
                            </template>
                            <template x-if="!detectedName && !errorHint">
                                <span style="display:inline-flex;align-items:center;gap:8px"><span class="dot"></span>Arahkan ke QR / barcode unit</span>
                            </template>
                        </div>

                        <div class="scn-flash" x-show="flash" x-cloak></div>
                    </div>

                    {{-- Foot (live only) --}}
                    <div class="scn-foot" x-show="phase==='live'" x-cloak>
                        <div class="scn-foot-status">
                            <span class="fs-k">Scanner</span>
                            <span class="fs-v">{!! $icon('checkCircle') !!}<span>Otomatis membuka produk saat terdeteksi</span></span>
                        </div>
                        <button class="scn-textbtn" @click="goManual()">{!! $icon('keyboard') !!}Ketik kode</button>
                    </div>

                    {{-- prompt --}}
                    <div class="scn-perm" x-show="phase==='prompt'" x-cloak>
                        <div class="scn-perm-ic accent">{!! $icon('camera') !!}</div>
                        <h3>Izinkan akses kamera</h3>
                        <p>Kami butuh kamera untuk memindai QR / barcode unit dan membuka produknya.</p>
                        <div class="scn-actions">
                            <button class="scn-btn primary" @click="requestCamera()">{!! $icon('camera') !!}Izinkan kamera</button>
                            <button class="scn-btn ghost" @click="goManual()">{!! $icon('keyboard') !!}Ketik kode manual</button>
                        </div>
                        <span class="scn-priv">{!! $icon('lock') !!}Video tetap di perangkat ini — tidak diunggah.</span>
                    </div>

                    {{-- requesting --}}
                    <div class="scn-perm" x-show="phase==='requesting'" x-cloak>
                        <div class="scn-perm-ic accent"><span class="ping"></span>{!! $icon('camera') !!}</div>
                        <h3>Meminta izin kamera…</h3>
                        <p>Tekan <b>Izinkan</b> pada permintaan izin browser untuk mulai memindai.</p>
                        <div class="scn-actions">
                            <button class="scn-btn ghost" disabled style="opacity:.7"><span class="scn-spin" style="width:18px;height:18px;display:inline-flex">{!! $icon('spinner') !!}</span>Menunggu izin</button>
                            <button class="scn-btn link" @click="goManual()">Ketik kode manual saja</button>
                        </div>
                    </div>

                    {{-- denied --}}
                    <div class="scn-perm" x-show="phase==='denied'" x-cloak>
                        <div class="scn-perm-ic danger">{!! $icon('cameraOff') !!}</div>
                        <h3>Akses kamera ditolak</h3>
                        <p>Izin kamera ditolak. Izinkan untuk memindai, atau lanjut dengan cara lain.</p>
                        <div class="scn-actions">
                            <button class="scn-btn primary" @click="requestCamera()">{!! $icon('refresh') !!}Coba lagi</button>
                            <button class="scn-btn link" @click="goManual()">Ketik kode manual</button>
                        </div>
                    </div>

                    {{-- blocked --}}
                    <div class="scn-perm" x-show="phase==='blocked'" x-cloak>
                        <div class="scn-perm-ic warn">{!! $icon('lock') !!}</div>
                        <h3>Kamera diblokir</h3>
                        <p>Akses kamera dimatikan untuk situs ini. Aktifkan kembali untuk memindai.</p>
                        <div class="scn-steps">
                            <div class="st"><span class="n">1</span><span>Tekan ikon gembok di address bar</span>{!! $icon('lock') !!}</div>
                            <div class="st"><span class="n">2</span><span>Atur <b>Kamera</b> ke <b>Izinkan</b></span>{!! $icon('camera') !!}</div>
                            <div class="st"><span class="n">3</span><span>Muat ulang lalu pindai lagi</span>{!! $icon('refresh') !!}</div>
                        </div>
                        <div class="scn-actions">
                            <button class="scn-btn primary" @click="requestCamera()">{!! $icon('refresh') !!}Coba lagi</button>
                            <button class="scn-btn link" @click="goManual()">Ketik kode manual</button>
                        </div>
                    </div>

                    {{-- nocamera --}}
                    <div class="scn-perm" x-show="phase==='nocamera'" x-cloak>
                        <div class="scn-perm-ic muted">{!! $icon('cameraOff') !!}</div>
                        <h3>Kamera tidak ditemukan</h3>
                        <p>Tidak terdeteksi kamera di perangkat ini. Anda masih bisa mengetik kode unit.</p>
                        <div class="scn-actions">
                            <button class="scn-btn primary" @click="goManual()">{!! $icon('keyboard') !!}Ketik kode manual</button>
                            <button class="scn-btn link" @click="requestCamera()">{!! $icon('refresh') !!}Cek lagi</button>
                        </div>
                    </div>

                    {{-- manual entry --}}
                    <div class="scn-manual" x-show="phase==='manual'" x-cloak>
                        <div class="ttl">{!! $icon('keyboard') !!}Ketik kode unit</div>
                        <div>
                            <div class="scn-field">
                                <input x-ref="manualInput" class="scn-input" placeholder="Serial / kode — mis. SN-A7IV-001"
                                       x-model="manualVal" @input="manualErr=''" @keydown.enter.prevent="submitManual()">
                                <button class="scn-go" :disabled="!manualVal.trim() || busy" @click="submitManual()">Buka</button>
                            </div>
                            <div class="scn-manual-err">
                                <template x-if="manualErr">
                                    <span style="display:inline-flex;align-items:center;gap:6px">{!! $icon('alert') !!}<span x-text="manualErr"></span></span>
                                </template>
                            </div>
                        </div>
                        <button class="scn-btn ghost" @click="backToCamera()">{!! $icon('camera') !!}Kembali ke kamera</button>
                    </div>

                </div>
            </div>
        </div>
    </template>
</div>
