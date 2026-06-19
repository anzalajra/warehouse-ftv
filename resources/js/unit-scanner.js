// unit-scanner.js — Alpine component powering the camera scanner popup on the
// Pickup / Return operation pages.
//
// Phases mirror the design handoff (scanner-core.jsx):
//   prompt → requesting → live | denied | blocked | nocamera | manual
//
// Small-QR detection strategy (labels are 12 mm, so the QR is tiny):
//   1. ROI crop + upscale — decode only a centered square (the finder frame)
//      drawn upscaled to an offscreen canvas, so the QR fills the decoded image
//      ("digital zoom" that works on every browser, incl. iOS Safari).
//   2. Hardware zoom — magnify at the sensor where the camera exposes a `zoom`
//      capability (Android Chrome); buttons/pinch fall back to software crop
//      (softZoom) where it isn't (iOS).
//   3. Native BarcodeDetector — use the OS detector (Android Chrome) when
//      available: far more robust on small/blurry codes and lighter on CPU than
//      the JS decoder. Falls back to @zxing/browser everywhere else (iOS, etc.).
//   4. Tap-to-focus on the camera area.
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

// Classify the device by the (best-effort) capability signals the browser
// exposes, so we can dial camera resolution + decode cadence to what the
// hardware can actually keep up with. Low-spec Androids (e.g. Samsung A14)
// choke on a 1080p stream decoded 10×/sec; high-end phones don't.
//   - 'low'  : ≤4 GB RAM or ≤4 logical cores  → 960×720, 180ms between scans
//   - 'high' : ≥8 GB RAM and ≥6 logical cores → 1920×1080, 100ms (unchanged)
//   - 'mid'  : everything else, incl. browsers that hide deviceMemory (iOS) → 1280×720, 100ms
function deviceTier() {
    const mem = typeof navigator !== 'undefined' ? navigator.deviceMemory : undefined;   // GB, Chrome-only, coarse
    const cores = typeof navigator !== 'undefined' ? navigator.hardwareConcurrency : undefined;

    if ((mem !== undefined && mem <= 4) || (cores !== undefined && cores <= 4)) return 'low';
    if (mem !== undefined && cores !== undefined && mem >= 8 && cores >= 6) return 'high';
    return 'mid';
}

const TIER_PROFILES = {
    low:  { width: 960,  height: 720,  delayBetweenScanAttempts: 180 },
    mid:  { width: 1280, height: 720,  delayBetweenScanAttempts: 100 },
    high: { width: 1920, height: 1080, delayBetweenScanAttempts: 100 },
};

// Software-zoom (ROI crop) bounds. softZoom = how tightly we crop the centred
// square before decoding; 1.5 ≈ the finder frame, up to 3× for tiny codes.
const SOFT_ZOOM_MIN = 1.0;
const SOFT_ZOOM_MAX = 3.0;
const SOFT_ZOOM_DEFAULT = 1.5;

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

        // ---- zoom ----
        zoomSupported: false,        // true → camera exposes a hardware `zoom` capability
        zoomMin: 1,
        zoomMax: 1,
        zoomStep: 0,
        zoom: 1,                     // current hardware zoom
        softZoom: SOFT_ZOOM_DEFAULT, // ROI crop factor (digital zoom)

        // ---- internals ----
        stream: null,
        reader: null,                // ZXing reader (zxing engine only)
        engine: 'zxing',             // 'native' | 'zxing'
        _detector: null,             // BarcodeDetector instance (native engine)
        _scanning: false,
        _loop: null,
        _canvas: null,
        _ctx: null,
        lastCode: null,
        lastAt: 0,
        _detectTimer: null,
        _profile: TIER_PROFILES.mid,   // resolved in init() from deviceTier()
        _pinchStart: 0,
        _pinchBase: 0,

        // ---- derived ----
        get remaining() { return this.items.filter((i) => !i.checked).length; },
        get scanned() { return this.items.length - this.remaining; },
        get total() { return this.items.length; },
        get isReturn() { return this.mode === 'return'; },
        get modeWord() { return this.isReturn ? 'Return' : 'Pick Up'; },
        get modeKey() { return this.isReturn ? 'return' : 'pickup'; },
        get zoomLabel() {
            const z = this.zoomSupported ? this.zoom : this.softZoom;
            return (Math.round(z * 10) / 10) + '×';
        },

        // ---- lifecycle ----
        init() {
            this._profile = TIER_PROFILES[deviceTier()] || TIER_PROFILES.mid;
            this.detectEngine();   // async, no stream needed — cache the result early
            this.$watch('open', (v) => { if (!v) this.stopAll(); });
        },

        // Pick the best decode engine once: the OS-native BarcodeDetector when it
        // supports our formats (Android Chrome), else fall back to ZXing JS.
        async detectEngine() {
            this.engine = 'zxing';
            this._detector = null;
            try {
                if ('BarcodeDetector' in window && typeof window.BarcodeDetector.getSupportedFormats === 'function') {
                    const fmts = await window.BarcodeDetector.getSupportedFormats();
                    const want = ['qr_code', 'code_128'].filter((f) => fmts.includes(f));
                    if (want.length) {
                        this._detector = new window.BarcodeDetector({ formats: want });
                        this.engine = 'native';
                    }
                }
            } catch (e) {
                this.engine = 'zxing';
                this._detector = null;
            }
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
                // Ask for continuous autofocus + a resolution sized to the
                // device tier (low-spec phones get 720p so the decode loop
                // isn't drawing huge frames). `focusMode`/`advanced` are
                // ignored by browsers that don't support them (no throw), and we
                // re-apply focus after the stream starts (see applyFocus()).
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: this._profile.width },
                        height: { ideal: this._profile.height },
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

            // Capability probe: torch + hardware zoom.
            const track = this._track();
            const caps = (track && track.getCapabilities) ? track.getCapabilities() : {};
            this.torchSupported = !!caps.torch;
            this.setupZoom(track, caps);

            // Force continuous autofocus so small QR codes come into focus. The
            // initial getUserMedia hint is often ignored; applyConstraints after
            // the track is live is the reliable path.
            this.applyFocus(track, caps);

            // ZXing reader is only needed for the fallback engine.
            if (this.engine === 'zxing' && !this.reader) {
                this.reader = new BrowserMultiFormatReader(SCAN_HINTS);
            }

            // Attach the stream to the <video> ourselves (we drive the decode
            // loop manually rather than via reader.decodeFromStream, so we can
            // crop + upscale each frame before decoding).
            try {
                if (video.srcObject !== this.stream) video.srcObject = this.stream;
                const p = video.play();
                if (p && p.catch) p.catch(() => { /* autoplay blocked — playsinline+muted should avoid this */ });
            } catch (e) { /* noop */ }

            this._scanning = true;
            this.runLoop();
        },

        // Probe + initialise hardware zoom. Auto-applies a gentle 2× so small
        // codes already fill more of the frame on devices that support it.
        setupZoom(track, caps) {
            const z = caps && caps.zoom;
            if (z && typeof z.max === 'number' && z.max > (z.min || 1)) {
                this.zoomSupported = true;
                this.zoomMin = z.min || 1;
                this.zoomMax = z.max;
                this.zoomStep = z.step || Math.max(0.1, (this.zoomMax - this.zoomMin) / 10);
                const target = Math.min(this.zoomMax, Math.max(this.zoomMin, 2));
                this.setZoom(target);
            } else {
                this.zoomSupported = false;
            }
        },

        // The decode loop: grab the centred ROI, upscale it, decode with the
        // active engine. Re-schedules itself at the tier cadence; setTimeout
        // recursion (not setInterval) so an async native detect never overlaps.
        async runLoop() {
            if (!this._scanning) return;
            const video = this.$refs.video;
            if (video && video.readyState >= 2 && video.videoWidth) {
                try {
                    const text = await this.grabAndDecode(video);
                    if (text) this.onDecode(text);
                } catch (e) { /* no code in this frame */ }
            }
            if (this._scanning) {
                this._loop = setTimeout(() => this.runLoop(), this._profile.delayBetweenScanAttempts);
            }
        },

        // Draw the centred square ROI (sized by softZoom) into the offscreen
        // canvas, upscaling tight crops so the QR has more module resolution,
        // then decode it with the active engine. Returns the text or null.
        async grabAndDecode(video) {
            const vW = video.videoWidth, vH = video.videoHeight;
            if (!vW || !vH) return null;

            const side = Math.min(vW, vH) / Math.min(SOFT_ZOOM_MAX, Math.max(SOFT_ZOOM_MIN, this.softZoom));
            const sx = (vW - side) / 2;
            const sy = (vH - side) / 2;
            // Bound the canvas: upscale tight crops to ≥480 (more px per module),
            // cap large ones at 1024 (CPU). Square, since the ROI is square.
            const target = Math.min(1024, Math.max(480, Math.round(side)));

            if (!this._canvas) {
                this._canvas = document.createElement('canvas');
                this._ctx = this._canvas.getContext('2d', { willReadFrequently: true });
            }
            if (this._canvas.width !== target) { this._canvas.width = target; this._canvas.height = target; }
            this._ctx.drawImage(video, sx, sy, side, side, 0, 0, target, target);

            if (this.engine === 'native' && this._detector) {
                const codes = await this._detector.detect(this._canvas);
                if (codes && codes.length) return codes[0].rawValue || null;
                return null;
            }

            // ZXing: decodeFromCanvas throws NotFoundException when no code is
            // present — that's the common case, caught by runLoop().
            const result = this.reader.decodeFromCanvas(this._canvas);
            return result ? result.getText() : null;
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

        // Tap-to-focus: point the autofocus at where the operator tapped, then
        // hand back to continuous so it keeps tracking. Best-effort — many
        // devices ignore pointsOfInterest / single-shot (must never throw).
        async focusAt(e) {
            const track = this._track();
            const el = this.$refs.video;
            if (!track || !track.applyConstraints || !el) return;
            const r = el.getBoundingClientRect();
            const t = (e.touches && e.touches[0]) || e;
            const x = Math.min(1, Math.max(0, ((t.clientX ?? (r.left + r.width / 2)) - r.left) / r.width));
            const y = Math.min(1, Math.max(0, ((t.clientY ?? (r.top + r.height / 2)) - r.top) / r.height));
            try {
                await track.applyConstraints({ advanced: [{ focusMode: 'single-shot', pointsOfInterest: [{ x, y }] }] });
                setTimeout(() => {
                    track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
                }, 1600);
            } catch (e2) { /* device ignores POI / single-shot focus */ }
        },

        stopDecode() {
            this._scanning = false;
            clearTimeout(this._loop);
            this._loop = null;
        },

        stopAll() {
            clearTimeout(this._detectTimer);
            this.stopDecode();
            if (this.stream) {
                try { this.stream.getTracks().forEach((t) => t.stop()); } catch (e) { /* noop */ }
                this.stream = null;
            }
            const v = this.$refs.video;
            if (v) { try { v.srcObject = null; } catch (e) { /* noop */ } }
            this.torch = false;
        },

        _track() {
            return this.stream && this.stream.getVideoTracks ? this.stream.getVideoTracks()[0] : null;
        },

        // ---- zoom controls (hardware where supported, else software ROI) ----
        async setZoom(v) {
            if (this.zoomSupported) {
                const z = Math.min(this.zoomMax, Math.max(this.zoomMin, v));
                this.zoom = Math.round(z * 10) / 10;
                const track = this._track();
                if (track && track.applyConstraints) {
                    try { await track.applyConstraints({ advanced: [{ zoom: this.zoom }] }); } catch (e) { /* noop */ }
                }
            } else {
                this.softZoom = Math.min(SOFT_ZOOM_MAX, Math.max(SOFT_ZOOM_MIN, Math.round(v * 100) / 100));
            }
        },

        zoomIn() {
            if (this.zoomSupported) {
                this.setZoom(this.zoom + (this.zoomStep || 0.5));
            } else {
                this.softZoom = Math.min(SOFT_ZOOM_MAX, Math.round((this.softZoom + 0.25) * 100) / 100);
            }
        },

        zoomOut() {
            if (this.zoomSupported) {
                this.setZoom(this.zoom - (this.zoomStep || 0.5));
            } else {
                this.softZoom = Math.max(SOFT_ZOOM_MIN, Math.round((this.softZoom - 0.25) * 100) / 100);
            }
        },

        // Pinch-to-zoom on the camera area.
        onCamTouchStart(e) {
            if (e.touches && e.touches.length === 2) {
                this._pinchStart = this._touchDist(e.touches);
                this._pinchBase = this.zoomSupported ? this.zoom : this.softZoom;
            }
        },

        onCamTouchMove(e) {
            if (e.touches && e.touches.length === 2 && this._pinchStart) {
                e.preventDefault();
                const ratio = this._touchDist(e.touches) / this._pinchStart;
                if (this.zoomSupported) {
                    this.setZoom(this._pinchBase * ratio);
                } else {
                    this.softZoom = Math.min(SOFT_ZOOM_MAX, Math.max(SOFT_ZOOM_MIN, Math.round(this._pinchBase * ratio * 100) / 100));
                }
            }
        },

        _touchDist(t) {
            const dx = t[0].clientX - t[1].clientX, dy = t[0].clientY - t[1].clientY;
            return Math.hypot(dx, dy);
        },

        async toggleTorch() {
            const track = this._track();
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
                // scanByCode already re-rendered the page checklist in this same
                // round-trip; update our local list from checked_ids instead of
                // firing a second scannableList() request.
                this.applyChecked(res.checked_ids);
            } else if (res.status === 'already') {
                this.showDetected(res.label, 'already');
            }
            // 'notfound' / 'foreign' / 'unavailable' → silently keep scanning
        },

        // Mark the given DeliveryItem ids checked in the local list (no server
        // round-trip). Falls back to refresh() only if the backend didn't send
        // ids (e.g. older server build).
        applyChecked(ids) {
            if (Array.isArray(ids) && ids.length) {
                const set = new Set(ids);
                this.items = this.items.map((it) => (set.has(it.id) ? { ...it, checked: true } : it));
            } else {
                this.refresh();
            }
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
                this.applyChecked(res.checked_ids);
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
