// unit-scanner.js — Alpine component powering the camera scanner popup on the
// Pickup / Return operation pages. Real QR + Code128 decode via @zxing/browser.
//
// Phases mirror the design handoff (scanner-core.jsx):
//   prompt → requesting → live | denied | blocked | nocamera | manual
//
// Closed-system contract: every label encodes "PREFIX:serial". The client does
// a fast prefix check to ignore foreign codes, then hands the raw code to the
// Livewire page (`scanByCode`) which strips the prefix and checks the match.
//
// Registered against Filament's Alpine instance via the `alpine:init` event.

import { BrowserMultiFormatReader } from '@zxing/browser';
import { DecodeHintType, BarcodeFormat } from '@zxing/library';

// Restrict the decoder to the two formats we actually print (QR + Code128) and
// turn on TRY_HARDER. Hinting the formats makes 1D (barcode) detection from a
// live camera far more reliable — an unhinted MultiFormatReader barely locks
// onto Code128 frames.
const SCAN_HINTS = new Map();
SCAN_HINTS.set(DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.QR_CODE, BarcodeFormat.CODE_128]);
SCAN_HINTS.set(DecodeHintType.TRY_HARDER, true);

function unitScanner(config = {}) {
    return {
        // ---- config ----
        mode: config.mode || 'pickup',
        rentalCode: config.rentalCode || '',
        prefix: (config.prefix || '').toLowerCase(),
        items: Array.isArray(config.items) ? config.items : [],

        // ---- ui state ----
        open: false,
        variant: 'desktop',          // 'desktop' | 'mobile'
        view: 'cam',                 // mobile only: 'list' | 'cam'
        phase: 'prompt',             // prompt | requesting | live | denied | blocked | nocamera | manual
        cascade: true,               // "Include accessories" switch

        detectedName: null,
        detectedTone: 'ok',          // 'ok' | 'already'
        flash: false,
        torch: false,
        torchSupported: false,
        manualVal: '',
        manualErr: '',

        // ---- internals ----
        stream: null,
        reader: null,
        controls: null,
        lastCode: null,
        lastAt: 0,
        _detectTimer: null,

        // ---- derived ----
        get remaining() { return this.items.filter((i) => !i.checked).length; },
        get scanned() { return this.items.length - this.remaining; },
        get total() { return this.items.length; },
        get isReturn() { return this.mode === 'return'; },
        get modeWord() { return this.isReturn ? 'Return' : 'Pick Up'; },
        get modeKey() { return this.isReturn ? 'return' : 'pickup'; },

        // ---- lifecycle ----
        init() {
            this.$watch('open', (v) => { if (!v) this.stopAll(); });
        },

        openScanner() {
            this.variant = window.matchMedia('(max-width: 680px)').matches ? 'mobile' : 'desktop';
            this.detectedName = null;
            this.manualVal = '';
            this.manualErr = '';
            this.refresh();
            this.open = true;
            if (this.variant === 'mobile') {
                this.view = 'list';
                this.phase = 'prompt';
            } else {
                this.view = 'cam';
                this.phase = 'prompt';
            }
        },

        openCamSheet() {           // mobile: from the list page → camera sheet
            this.view = 'cam';
            this.phase = 'prompt';
        },

        backToList() {             // mobile: camera sheet → list page
            this.stopAll();
            this.detectedName = null;
            this.view = 'list';
        },

        close() {
            this.open = false;     // watcher stops everything
            this.phase = 'prompt';
            this.detectedName = null;
        },

        async refresh() {
            try {
                const list = await this.$wire.scannableList();
                if (Array.isArray(list)) this.items = list;
            } catch (e) { /* keep current list */ }
        },

        // ---- camera ----
        async requestCamera() {
            this.phase = 'requesting';
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.phase = 'nocamera';
                return;
            }
            try {
                // Ask for continuous autofocus + a high resolution so small QR
                // codes keep enough pixels to decode. `focusMode`/`advanced` are
                // ignored by browsers that don't support them (no throw), and we
                // re-apply focus after the stream starts (see applyFocus()).
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

            // torch capability probe
            const track = this.stream.getVideoTracks ? this.stream.getVideoTracks()[0] : null;
            const caps = (track && track.getCapabilities) ? track.getCapabilities() : {};
            this.torchSupported = !!caps.torch;

            // Force continuous autofocus so small QR codes come into focus. The
            // initial getUserMedia hint is often ignored; applyConstraints after
            // the track is live is the reliable path. Prefer 'continuous', fall
            // back to whatever the device supports.
            this.applyFocus(track, caps);

            if (!this.reader) {
                // zxing throttles decode attempts to 500ms by default → only 2
                // tries/sec, so a code can sit in frame for seconds before a
                // sharp frame happens to land on a poll. Poll ~10×/sec instead
                // for near-instant lock-on.
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
            // Some devices expose focusMode capability, some don't list it but
            // still honour the constraint — try regardless.
            const want = modes.includes('continuous') ? 'continuous'
                : (modes.includes('auto') ? 'auto' : 'continuous');
            try {
                await track.applyConstraints({ advanced: [{ focusMode: want }] });
            } catch (e) { /* device has no controllable focus — fixed-focus cam */ }
        },

        stopDecode() {
            try { if (this.controls) this.controls.stop(); } catch (e) { /* noop */ }
            this.controls = null;
        },

        stopAll() {
            clearTimeout(this._detectTimer);
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
            } catch (e) {
                this.torch = false;
            }
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
            if (!text) return;
            const now = Date.now();
            if (text === this.lastCode && now - this.lastAt < 2500) return;

            // fast client-side ignore of foreign codes
            if (this.prefix && !text.toLowerCase().startsWith(this.prefix + ':')) return;

            this.lastCode = text;
            this.lastAt = now;
            this.handleCode(text);
        },

        async handleCode(raw) {
            let res;
            try {
                res = await this.$wire.scanByCode(raw, this.cascade);
            } catch (e) { return; }
            if (!res) return;

            if (res.status === 'ok') {
                this.showDetected(res.label, 'ok');
                await this.refresh();
            } else if (res.status === 'already') {
                this.showDetected(res.label, 'already');
            }
            // 'notfound' / 'foreign' / 'unavailable' → silently keep scanning
        },

        showDetected(name, tone) {
            clearTimeout(this._detectTimer);
            this.detectedName = name;
            this.detectedTone = tone || 'ok';
            this.flash = true;
            setTimeout(() => { this.flash = false; }, 420);
            this._detectTimer = setTimeout(() => { this.detectedName = null; }, 1300);
        },

        // ---- manual entry ----
        async submitManual(val) {
            const q = (val != null ? val : this.manualVal).trim();
            if (!q) return;
            let res;
            try {
                res = await this.$wire.scanByCode(q, this.cascade, true);
            } catch (e) { return; }
            if (!res) return;

            if (res.status === 'notfound') { this.manualErr = 'No unit matches that code'; return; }
            if (res.status === 'already') { this.manualErr = (res.label || 'Item') + ' is already scanned'; return; }
            if (res.status === 'unavailable') { this.manualErr = (res.label || 'Unit') + ' is unavailable — swap it first'; return; }
            if (res.status === 'ok') {
                this.manualVal = '';
                this.manualErr = '';
                await this.refresh();
            }
        },
    };
}

const register = () => {
    if (window.Alpine && !window.__unitScannerRegistered) {
        window.__unitScannerRegistered = true;
        window.Alpine.data('unitScanner', unitScanner);
    }
};

document.addEventListener('alpine:init', register);
register();
