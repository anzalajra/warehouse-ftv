{{--
    Global admin QR / barcode scanner (Filament topbar button).
    Same look & feel as the Pickup/Return unit scanner — shares the .scn-* styles
    (filament.scanner-styles) and the ZXing camera machinery (global-scanner.js).
    Single-shot: decode → /admin/scan-resolve → open the product's catalog edit
    page (or a same-origin document URL directly).
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

@once
    {{-- Guarded so a not-yet-built manifest (before `npm run build`) degrades the
         scanner gracefully instead of fataling every admin page. --}}
    @php
        try {
            echo app(\Illuminate\Foundation\Vite::class)('resources/js/global-scanner.js');
        } catch (\Throwable $e) {
            // Assets not built yet — the topbar scanner button stays inert until built.
        }
    @endphp
@endonce

@include('filament.scanner-styles')

<div class="scn-host"
     x-data="globalScanner({ resolveUrl: @js(route('admin.scan-resolve')) })"
     x-cloak>

    {{-- Topbar trigger --}}
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
                        <video x-ref="video" class="scn-video" playsinline muted autoplay></video>

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
