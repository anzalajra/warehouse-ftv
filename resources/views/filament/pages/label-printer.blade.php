<x-filament-panels::page>
    <div
        x-data="labelPrinter(@js($queue))"
        x-init="init()"
        class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6"
    >
        {{-- ===================== LEFT: DESIGNER + PREVIEW ===================== --}}
        <div class="fi-section rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 p-5 space-y-4">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Desain Label</h2>

            {{-- Unsupported browser banner --}}
            <div x-show="!supported" x-cloak class="rounded-lg bg-danger-50 dark:bg-danger-500/10 text-danger-700 dark:text-danger-400 text-sm p-3 ring-1 ring-danger-600/20">
                ⚠️ Browser ini <b>tidak mendukung Web Bluetooth</b><span x-show="isiOS"> — iPhone/iPad tidak bisa.</span>.
                Gunakan <b>Chrome</b> atau <b>Edge</b> di Android, Windows, atau Mac. (Wajib HTTPS atau localhost.)
            </div>

            {{-- Size --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-gray-500 dark:text-gray-400">Lebar cetak (dots)</label>
                    <input type="number" min="8" max="1024" x-model.number="widthDots" @input.debounce.150ms="render()"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    <p class="text-[11px] text-gray-400 mt-1">L12 & seri mini = 96 (12mm)</p>
                </div>
                <div>
                    <label class="text-xs text-gray-500 dark:text-gray-400">Panjang label (mm)</label>
                    <select x-model="lengthPreset" @change="render()"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="30">30 mm</option>
                        <option value="40">40 mm</option>
                        <option value="50">50 mm</option>
                        <option value="custom">Custom…</option>
                    </select>
                    <input x-show="lengthPreset === 'custom'" type="number" min="5" max="300" x-model.number="lengthCustom" @input.debounce.150ms="render()"
                        class="mt-1.5 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
            </div>

            {{-- Text --}}
            <div>
                <label class="text-xs text-gray-500 dark:text-gray-400">Teks (boleh beberapa baris)</label>
                <textarea rows="2" x-model="text" @input.debounce.150ms="render()"
                    class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="text-xs text-gray-500 dark:text-gray-400">Ukuran teks</label>
                    <input type="number" min="6" max="64" x-model.number="fontSize" @input.debounce.150ms="render()"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label class="text-xs text-gray-500 dark:text-gray-400">Perataan</label>
                    <select x-model="align" @change="render()"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="left">Kiri</option>
                        <option value="center">Tengah</option>
                        <option value="right">Kanan</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500 dark:text-gray-400">Tebal</label>
                    <select x-model="bold" @change="render()"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="0">Normal</option>
                        <option value="1">Bold</option>
                    </select>
                </div>
            </div>

            {{-- QR --}}
            <div>
                <label class="text-xs text-gray-500 dark:text-gray-400">QR Code (payload — kosongkan bila tak perlu)</label>
                <input type="text" x-model="qr" @input.debounce.150ms="render()"
                    class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm font-mono shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <p class="text-[11px] text-gray-400 mt-1">Format sistem tertutup <span class="font-mono">PREFIX:serial</span> agar terbaca scanner pickup/return.</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-gray-500 dark:text-gray-400">Ukuran QR (dots)</label>
                    <input type="number" min="20" max="400" x-model.number="qrSize" @input.debounce.150ms="render()"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label class="text-xs text-gray-500 dark:text-gray-400">Posisi QR</label>
                    <select x-model="qrPos" @change="render()"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="bottom">Bawah teks</option>
                        <option value="top">Atas teks</option>
                    </select>
                </div>
            </div>

            {{-- Image --}}
            <div>
                <label class="text-xs text-gray-500 dark:text-gray-400">Gambar (opsional — menggantikan QR/teks)</label>
                <input type="file" accept="image/*" x-ref="imgFile" @change="onImage($event)"
                    class="mt-1 w-full text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 dark:file:bg-gray-700 file:px-3 file:py-1.5 file:text-sm">
                <div class="flex items-center gap-3 mt-2">
                    <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <input type="checkbox" x-model="dither" @change="render()" class="rounded"> Dithering (untuk foto)
                    </label>
                    <button type="button" @click="clearImage()" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">Hapus gambar</button>
                </div>
            </div>

            {{-- Preview --}}
            <div class="pt-2 border-t border-gray-100 dark:border-white/10 flex flex-col items-center gap-2">
                <div class="rounded-lg p-3 ring-1 ring-gray-200 dark:ring-white/10" style="background:repeating-conic-gradient(#eef2f7 0% 25%,#fff 0% 50%) 0/16px 16px">
                    <canvas x-ref="preview" style="image-rendering:pixelated;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.12)"></canvas>
                </div>
                <p class="text-[11px] text-gray-400">Preview (diperbesar 3×). Hitam = tercetak.</p>
            </div>
        </div>

        {{-- ===================== RIGHT: QUEUE + CONNECT + PRINT ===================== --}}
        <div class="space-y-6">
            {{-- Queue --}}
            <div x-show="queue.length" x-cloak class="fi-section rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 p-5">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">
                    Antrian Label (<span x-text="queue.length"></span>)
                </h2>
                <div class="space-y-1.5 max-h-64 overflow-auto -mx-1 px-1">
                    <template x-for="(item, i) in queue" :key="i">
                        <button type="button" @click="selectQueueItem(i)"
                            class="w-full text-left rounded-lg px-3 py-2 ring-1 transition"
                            :class="i === selectedIndex
                                ? 'bg-primary-50 dark:bg-primary-500/10 ring-primary-500/40'
                                : 'bg-gray-50 dark:bg-white/5 ring-transparent hover:bg-gray-100 dark:hover:bg-white/10'">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.name"></div>
                            <div class="text-xs font-mono text-gray-500 dark:text-gray-400 truncate" x-text="item.serial"></div>
                        </button>
                    </template>
                </div>
                <button type="button" @click="printAll()" x-bind:disabled="!connected || busy"
                    class="mt-3 w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold px-4 py-2.5">
                    🖨️ Cetak semua antrian
                </button>
            </div>

            {{-- Connect & print --}}
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Koneksi & Cetak</h2>
                    <span class="text-[11px] px-2 py-0.5 rounded-full"
                        :class="connected ? 'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-400' : 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400'"
                        x-text="connected ? 'Terhubung' : 'Belum terhubung'"></span>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="connect()" x-bind:disabled="!supported || connected"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold px-3 py-2">
                        🔌 Hubungkan
                    </button>
                    <button type="button" @click="disconnect()" x-bind:disabled="!connected"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-100 dark:bg-white/5 ring-1 ring-gray-200 dark:ring-white/10 disabled:opacity-50 text-gray-700 dark:text-gray-200 text-sm font-semibold px-3 py-2">
                        Putus
                    </button>
                </div>
                <p class="text-[11px] text-gray-400" x-html="modelInfo"></p>

                <div>
                    <label class="text-xs text-gray-500 dark:text-gray-400">Kepekatan (density)</label>
                    <div class="grid grid-cols-3 gap-2 mt-1">
                        <template x-for="(lbl, lvl) in {0:'Tipis',1:'Normal',2:'Tebal'}" :key="lvl">
                            <button type="button" @click="density = Number(lvl)"
                                class="rounded-lg px-2 py-1.5 text-xs ring-1 transition"
                                :class="density === Number(lvl) ? 'bg-primary-600 text-white ring-primary-600' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 ring-gray-200 dark:ring-white/10'"
                                x-text="lbl"></button>
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-500 dark:text-gray-400">Jumlah salinan</label>
                        <input type="number" min="1" max="50" x-model.number="copies"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 dark:text-gray-400">Threshold (1-254)</label>
                        <input type="number" min="1" max="254" x-model.number="threshold"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                </div>

                <button type="button" @click="print()" x-bind:disabled="!connected || busy"
                    class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold px-4 py-2.5">
                    🖨️ Cetak label ini
                </button>
                <button type="button" @click="requestStatus()" x-bind:disabled="!connected"
                    class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-gray-100 dark:bg-white/5 ring-1 ring-gray-200 dark:ring-white/10 disabled:opacity-50 text-gray-700 dark:text-gray-200 text-sm font-semibold px-4 py-2">
                    Cek status printer
                </button>
                <p class="text-[11px] text-gray-500 dark:text-gray-400" x-text="statusText"></p>
            </div>

            {{-- Log --}}
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 p-5">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Log</h2>
                <pre x-ref="log" class="text-[11px] leading-relaxed bg-gray-950 text-cyan-200 rounded-lg p-3 h-40 overflow-auto whitespace-pre-wrap" x-text="logText"></pre>
            </div>
        </div>
    </div>

    <script type="module">
        import {
            isSupported, connectLuckPrinter, LuckPrinter,
            createLabelCanvas, drawText, drawQR, drawImage, mmToDots, buildRaster,
        } from '/vendor/luckprinter/luckprinter.js';

        const ZOOM = 3;

        const registerLabelPrinter = () => {
            window.Alpine.data('labelPrinter', (queue = []) => ({
                    // --- support ---
                    supported: isSupported(),
                    isiOS: /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1),

                    // --- connection ---
                    printer: null,
                    connected: false,
                    busy: false,
                    modelInfo: '',
                    statusText: '',
                    logText: '',

                    // --- queue ---
                    queue,
                    selectedIndex: -1,

                    // --- designer state ---
                    widthDots: 96,
                    lengthPreset: '40',
                    lengthCustom: 40,
                    text: '',
                    fontSize: 18,
                    align: 'center',
                    bold: '0',
                    qr: '',
                    qrSize: 72,
                    qrPos: 'bottom',
                    dither: true,
                    density: 1,
                    copies: 1,
                    threshold: 128,
                    loadedImage: null,

                    init() {
                        if (this.queue.length) {
                            this.selectQueueItem(0);
                        } else {
                            this.text = 'FILM TELEVISI';
                            this.render();
                        }
                    },

                    log(msg) {
                        this.logText += msg + '\n';
                        this.$nextTick(() => { const el = this.$refs.log; if (el) el.scrollTop = el.scrollHeight; });
                    },

                    lengthMm() {
                        return this.lengthPreset === 'custom' ? Number(this.lengthCustom) : Number(this.lengthPreset);
                    },

                    selectQueueItem(i) {
                        const item = this.queue[i];
                        if (!item) return;
                        this.selectedIndex = i;
                        this.text = item.name + '\n' + item.serial;
                        this.qr = item.payload;
                        this.loadedImage = null;
                        this.render();
                    },

                    // Build a label canvas for given text/qr (defaults to current designer state).
                    buildLabelCanvas(text = this.text, qrText = this.qr) {
                        const width = Number(this.widthDots);
                        const height = mmToDots(this.lengthMm(), 203);
                        const { canvas, ctx } = createLabelCanvas(width, height);
                        const pad = 4, gap = 6;
                        let y = pad;

                        const txt = String(text || '').trim();
                        const qr = String(qrText || '').trim();
                        const fontSize = Number(this.fontSize);
                        const align = this.align;
                        const bold = this.bold === '1';
                        const qrSize = Math.min(Number(this.qrSize), width - 2 * pad);
                        const qrTop = this.qrPos === 'top';

                        const drawTheText = () => {
                            if (txt) { y = drawText(ctx, txt, { x: pad, y, size: fontSize, align, bold, maxWidth: width - 2 * pad }); y += gap; }
                        };
                        const drawTheQR = () => {
                            if (qr) { const r = drawQR(ctx, qr, { x: (width - qrSize) / 2, y, size: qrSize }); y += r.size + gap; }
                        };

                        if (this.loadedImage) {
                            drawImage(ctx, this.loadedImage, { x: pad, y, w: width - 2 * pad, h: height - y - pad, fit: 'contain', dither: this.dither });
                        } else if (qrTop) { drawTheQR(); drawTheText(); }
                        else { drawTheText(); drawTheQR(); }

                        return canvas;
                    },

                    render() {
                        const src = this.buildLabelCanvas();
                        const pv = this.$refs.preview;
                        if (!pv) return;
                        pv.width = src.width * ZOOM;
                        pv.height = src.height * ZOOM;
                        const ctx = pv.getContext('2d');
                        ctx.imageSmoothingEnabled = false;
                        ctx.clearRect(0, 0, pv.width, pv.height);
                        ctx.drawImage(src, 0, 0, pv.width, pv.height);
                    },

                    async onImage(e) {
                        const file = e.target.files[0];
                        if (!file) return;
                        const url = URL.createObjectURL(file);
                        this.loadedImage = await new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = url; });
                        this.render();
                    },
                    clearImage() {
                        this.loadedImage = null;
                        if (this.$refs.imgFile) this.$refs.imgFile.value = '';
                        this.render();
                    },

                    async connect() {
                        try {
                            this.printer = new LuckPrinter();
                            this.printer.addEventListener('log', (e) => this.log(e.detail));
                            this.printer.addEventListener('disconnected', () => { this.connected = false; this.log('Printer terputus.'); });
                            this.printer.addEventListener('status', (e) => this.showStatus(e.detail));
                            this.printer.addEventListener('progress', (e) => this.log(`Progress: salinan ${e.detail.copy}/${e.detail.copies}`));

                            const { name, model } = await connectLuckPrinter({ scope: 'label', printer: this.printer });
                            this.connected = true;
                            this.modelInfo = `Terhubung: <b>${name || '?'}</b>` + (model ? ` — model <b>${model.name}</b> (${model.printWidthDots} dots)` : ' — model tidak dikenal (protokol default)');
                            if (model) { this.widthDots = model.printWidthDots; this.render(); }
                        } catch (err) {
                            this.log('❌ ' + err.message);
                        }
                    },
                    disconnect() { this.printer?.disconnect(); },

                    async printCanvas(canvas) {
                        const raster = buildRaster(canvas, { threshold: Number(this.threshold) });
                        await this.printer.printRaster(raster, { copies: Number(this.copies), mode: 'label' });
                    },

                    async print() {
                        if (!this.printer || this.busy) return;
                        this.busy = true;
                        try {
                            await this.printer.cmdDensity(Number(this.density));
                            await this.printCanvas(this.buildLabelCanvas());
                            this.log('✅ Selesai mencetak.');
                        } catch (err) { this.log('❌ ' + err.message); }
                        this.busy = false;
                    },

                    async printAll() {
                        if (!this.printer || this.busy || !this.queue.length) return;
                        this.busy = true;
                        try {
                            await this.printer.cmdDensity(Number(this.density));
                            for (let i = 0; i < this.queue.length; i++) {
                                const item = this.queue[i];
                                this.selectedIndex = i;
                                const canvas = this.buildLabelCanvas(item.name + '\n' + item.serial, item.payload);
                                await this.printCanvas(canvas);
                                this.log(`✅ ${i + 1}/${this.queue.length}: ${item.serial}`);
                            }
                            this.log('✅ Semua antrian selesai dicetak.');
                        } catch (err) { this.log('❌ ' + err.message); }
                        this.busy = false;
                    },

                    requestStatus() { this.printer?.requestStatus(); },
                    showStatus(s) {
                        const parts = [];
                        if (s.isCoverOpen) parts.push('penutup terbuka');
                        if (s.isLackPaper) parts.push('kehabisan kertas');
                        if (s.isLowBattery) parts.push('baterai lemah');
                        if (s.isOverheat) parts.push('panas berlebih');
                        if (s.isCharging) parts.push('mengisi daya');
                        this.statusText = 'Status: ' + (parts.length ? parts.join(', ') : 'normal') + ` (0x${s.raw.toString(16)})`;
                    },
                }));
        };

        // Admin panel is not in SPA mode, so this runs on every full page load.
        // Register immediately if Alpine already started (defensive), else on init.
        if (window.Alpine) registerLabelPrinter();
        else document.addEventListener('alpine:init', registerLabelPrinter);
    </script>
</x-filament-panels::page>
