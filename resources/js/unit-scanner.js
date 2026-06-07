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
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } },
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

            if (!this.reader) this.reader = new BrowserMultiFormatReader();

            this.reader
                .decodeFromStream(this.stream, video, (result) => {
                    if (result) this.onDecode(result.getText());
                })
                .then((controls) => { this.controls = controls; })
                .catch(() => { /* decode loop failed to start */ });
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
