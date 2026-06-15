// global-scanner.js — Alpine component for the GLOBAL admin QR/barcode scanner
// in the Filament topbar. Shares the camera/permission machinery + look of the
// Pickup/Return unit scanner (resources/js/unit-scanner.js), but is single-shot:
// decode → resolve via /admin/scan-resolve → redirect to the product's catalog
// edit page (or open a same-origin document URL directly).
//
// Registered against Filament's Alpine via the `alpine:init` event.

import { BrowserMultiFormatReader } from '@zxing/browser';
import { DecodeHintType, BarcodeFormat } from '@zxing/library';

const SCAN_HINTS = new Map();
SCAN_HINTS.set(DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.QR_CODE, BarcodeFormat.CODE_128]);
SCAN_HINTS.set(DecodeHintType.TRY_HARDER, true);

function globalScanner(config = {}) {
    return {
        // ---- config ----
        resolveUrl: config.resolveUrl || '',

        // ---- ui state ----
        open: false,
        phase: 'prompt',             // prompt | requesting | live | denied | blocked | nocamera | manual
        detectedName: null,          // green success label
        errorHint: null,             // transient red hint over the camera
        flash: false,
        torch: false,
        torchSupported: false,
        manualVal: '',
        manualErr: '',
        busy: false,                 // a resolve request is in flight

        // ---- internals ----
        stream: null,
        reader: null,
        controls: null,
        lastCode: null,
        lastAt: 0,
        _hintTimer: null,

        init() {
            this.$watch('open', (v) => { if (!v) this.stopAll(); });
        },

        openScanner() {
            this.detectedName = null;
            this.errorHint = null;
            this.manualVal = '';
            this.manualErr = '';
            this.busy = false;
            this.phase = 'prompt';
            this.open = true;
        },

        close() {
            this.open = false;       // watcher stops the camera
            this.phase = 'prompt';
            this.detectedName = null;
            this.errorHint = null;
        },

        // ---- camera ----
        async requestCamera() {
            this.phase = 'requesting';
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.phase = 'nocamera';
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1920 },
                        height: { ideal: 1080 },
                        focusMode: 'continuous',
                        advanced: [{ focusMode: 'continuous' }],
                    },
                    audio: false,
                });
                this.phase = 'live';
                this.$nextTick(() => this.startDecode());
            } catch (e) {
                const n = (e && e.name) || '';
                if (['NotFoundError', 'DevicesNotFoundError', 'OverconstrainedError'].includes(n)) {
                    this.phase = 'nocamera';
                } else if (['NotAllowedError', 'PermissionDeniedError', 'SecurityError'].includes(n)) {
                    this.phase = (await this.probeBlocked()) ? 'blocked' : 'denied';
                } else {
                    this.phase = 'denied';
                }
            }
        },

        async probeBlocked() {
            try {
                if (navigator.permissions && navigator.permissions.query) {
                    const st = await navigator.permissions.query({ name: 'camera' });
                    return st.state === 'denied';
                }
            } catch (e) { /* unsupported */ }
            return false;
        },

        startDecode() {
            const video = this.$refs.video;
            if (!video || !this.stream) return;

            const track = this.stream.getVideoTracks ? this.stream.getVideoTracks()[0] : null;
            const caps = (track && track.getCapabilities) ? track.getCapabilities() : {};
            this.torchSupported = !!caps.torch;
            this.applyFocus(track, caps);

            if (!this.reader) {
                this.reader = new BrowserMultiFormatReader(SCAN_HINTS, {
                    delayBetweenScanAttempts: 100,
                    delayBetweenScanSuccess: 300,
                });
            }

            this.reader
                .decodeFromStream(this.stream, video, (result) => {
                    if (result) this.onDecode(result.getText());
                })
                .then((controls) => { this.controls = controls; })
                .catch(() => { /* decode loop failed to start */ });
        },

        async applyFocus(track, caps) {
            if (!track || !track.applyConstraints) return;
            const modes = (caps && caps.focusMode) || [];
            const want = modes.includes('continuous') ? 'continuous'
                : (modes.includes('auto') ? 'auto' : 'continuous');
            try {
                await track.applyConstraints({ advanced: [{ focusMode: want }] });
            } catch (e) { /* fixed-focus camera */ }
        },

        stopDecode() {
            try { if (this.controls) this.controls.stop(); } catch (e) { /* noop */ }
            this.controls = null;
        },

        stopAll() {
            clearTimeout(this._hintTimer);
            this.stopDecode();
            if (this.stream) {
                try { this.stream.getTracks().forEach((t) => t.stop()); } catch (e) { /* noop */ }
                this.stream = null;
            }
            this.torch = false;
        },

        async toggleTorch() {
            const track = this.stream && this.stream.getVideoTracks ? this.stream.getVideoTracks()[0] : null;
            if (!track || !track.applyConstraints) return;
            this.torch = !this.torch;
            try {
                await track.applyConstraints({ advanced: [{ torch: this.torch }] });
            } catch (e) { this.torch = false; }
        },

        goManual() {
            this.stopDecode();
            this.manualErr = '';
            this.phase = 'manual';
            this.$nextTick(() => { if (this.$refs.manualInput) this.$refs.manualInput.focus(); });
        },

        backToCamera() {
            if (this.stream) {
                this.phase = 'live';
                this.$nextTick(() => this.startDecode());
            } else {
                this.phase = 'prompt';
            }
        },

        // ---- decode handling ----
        onDecode(text) {
            if (!text || this.busy) return;
            const now = Date.now();
            if (text === this.lastCode && now - this.lastAt < 2500) return;
            this.lastCode = text;
            this.lastAt = now;

            // Same-origin http(s) URL → open it directly (document QR), like before.
            try {
                const url = new URL(text);
                if (url.protocol === 'http:' || url.protocol === 'https:') {
                    if (url.origin === window.location.origin) {
                        this.succeed('Membuka dokumen…', text);
                    } else {
                        this.showError('QR dari sistem lain (origin berbeda).');
                    }
                    return;
                }
            } catch (e) { /* not a URL → unit code below */ }

            // Otherwise treat as a unit/kit code (PREFIX:serial) and resolve server-side.
            this.resolveCode(text);
        },

        resolveCode(code) {
            if (!this.resolveUrl) { this.showError('Resolver tidak tersedia.'); return; }
            this.busy = true;
            fetch(this.resolveUrl + '?code=' + encodeURIComponent(code), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((r) => r.json().then((data) => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.ok && data.url) {
                        this.succeed(data.label || 'Membuka produk…', data.url);
                    } else {
                        this.busy = false;
                        this.showError((data && data.message) || 'QR tidak dikenali.');
                    }
                })
                .catch(() => { this.busy = false; this.showError('Gagal menghubungi server.'); });
        },

        succeed(label, href) {
            this.busy = true;
            this.errorHint = null;
            this.detectedName = label;
            this.flash = true;
            setTimeout(() => { this.flash = false; }, 420);
            setTimeout(() => { window.location.href = href; }, 750);
        },

        showError(msg) {
            clearTimeout(this._hintTimer);
            this.errorHint = msg;
            this._hintTimer = setTimeout(() => { this.errorHint = null; }, 2200);
        },

        // ---- manual entry ----
        submitManual() {
            const q = (this.manualVal || '').trim();
            if (!q || this.busy) return;
            if (!this.resolveUrl) { this.manualErr = 'Resolver tidak tersedia.'; return; }
            this.busy = true;
            this.manualErr = '';
            // Manual entry may be a same-origin URL too; let onDecode logic handle it.
            try {
                const url = new URL(q);
                if (url.protocol === 'http:' || url.protocol === 'https:') {
                    if (url.origin === window.location.origin) { window.location.href = q; return; }
                    this.busy = false; this.manualErr = 'URL dari sistem lain.'; return;
                }
            } catch (e) { /* not a URL */ }

            fetch(this.resolveUrl + '?code=' + encodeURIComponent(q), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((r) => r.json().then((data) => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.ok && data.url) {
                        window.location.href = data.url;
                    } else {
                        this.busy = false;
                        this.manualErr = (data && data.message) || 'Tidak ditemukan.';
                    }
                })
                .catch(() => { this.busy = false; this.manualErr = 'Gagal menghubungi server.'; });
        },
    };
}

const register = () => {
    if (window.Alpine && !window.__globalScannerRegistered) {
        window.__globalScannerRegistered = true;
        window.Alpine.data('globalScanner', globalScanner);
    }
};

document.addEventListener('alpine:init', register);
register();
