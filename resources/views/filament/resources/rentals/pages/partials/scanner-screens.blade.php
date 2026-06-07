{{--
    Shared scanner screens: header + camera/live view + foot + the permission
    state screens (prompt/requesting/denied/blocked/nocamera) + manual entry.
    Driven by the parent `unitScanner` Alpine component.

    Expects:
      $closeAction — Alpine expression for the close/back button (e.g. 'close()')
      $icon        — inherited inline icon helper from the operation blade
--}}

{{-- Header --}}
<div class="scn-head">
    <template x-if="variant==='desktop'">
        <div class="scn-iconbtn" aria-hidden="true" style="cursor:default;color:var(--scn-accent-2)">{!! $icon('scan') !!}</div>
    </template>
    <div class="scn-title">
        <span class="scn-sub" :class="modeKey" x-text="(isReturn?'Return':'Pickup') + ' · ' + rentalCode"></span>
        <b x-text="'Scan to ' + modeWord"></b>
    </div>
    <button class="scn-iconbtn" @click="{{ $closeAction }}" aria-label="Close scanner">{!! $icon('x') !!}</button>
</div>

{{-- Live camera view --}}
<div class="scn-cam" x-show="phase==='live'" x-cloak>
    <video x-ref="video" class="scn-video" playsinline muted autoplay></video>

    <div class="scn-mode-pill" :class="modeKey"><span class="swatch"></span><span x-text="isReturn?'Return':'Pickup'"></span></div>
    <div class="scn-live-chips">
        <span class="scn-chip"><span class="live-dot"></span>AUTO</span>
        <button class="scn-chip" :class="torch?'on':''" x-show="torchSupported" @click="toggleTorch()" aria-label="Toggle torch">{!! $icon('zap') !!}</button>
    </div>

    <div class="scn-finder">
        <div class="scn-frame" :class="detectedName?'ok':''">
            <span class="corner tl"></span><span class="corner tr"></span><span class="corner bl"></span><span class="corner br"></span>
            <span class="scn-line" x-show="!detectedName"></span>
            <span class="scn-checkbadge" x-show="detectedName" x-cloak>{!! $icon('check') !!}</span>
        </div>
    </div>

    <div class="scn-hint">
        <template x-if="detectedName">
            <span style="display:inline-flex;align-items:center;gap:8px"><span style="color:var(--scn-success);display:inline-flex">{!! $icon('checkCircle') !!}</span><span x-text="detectedName"></span></span>
        </template>
        <template x-if="!detectedName && remaining===0">
            <span style="display:inline-flex;align-items:center;gap:8px"><span style="color:var(--scn-success);display:inline-flex">{!! $icon('checkCircle') !!}</span>All units scanned</span>
        </template>
        <template x-if="!detectedName && remaining>0">
            <span style="display:inline-flex;align-items:center;gap:8px"><span class="dot"></span>Point at the unit barcode or QR</span>
        </template>
    </div>

    <div class="scn-flash" x-show="flash" x-cloak></div>
</div>

{{-- Foot (under camera, live only) --}}
<div class="scn-foot" x-show="phase==='live'" x-cloak>
    <div class="scn-foot-status">
        <span class="fs-k" x-text="(isReturn?'Return':'Pickup') + ' progress'"></span>
        <span class="fs-v">{!! $icon('checkCircle') !!}<span><b x-text="scanned"></b> / <span x-text="total"></span> units scanned</span></span>
    </div>
    <label class="scn-incl" title="Auto-check small accessories when their parent unit is scanned">
        <input type="checkbox" x-model="cascade"><span>Accessories</span>
    </label>
    <button class="scn-textbtn" @click="goManual()">{!! $icon('keyboard') !!}Enter code</button>
</div>

{{-- prompt --}}
<div class="scn-perm" x-show="phase==='prompt'" x-cloak>
    <div class="scn-perm-ic accent">{!! $icon('camera') !!}</div>
    <h3>Allow camera access</h3>
    <p x-text="'We need your camera to scan unit barcodes and QR codes for this ' + (isReturn?'return':'pickup') + '.'"></p>
    <div class="scn-actions">
        <button class="scn-btn primary" @click="requestCamera()">{!! $icon('camera') !!}Allow camera</button>
        <button class="scn-btn ghost" @click="goManual()">{!! $icon('keyboard') !!}Enter code manually</button>
    </div>
    <span class="scn-priv">{!! $icon('lock') !!}Video stays on this device — nothing is uploaded.</span>
</div>

{{-- requesting --}}
<div class="scn-perm" x-show="phase==='requesting'" x-cloak>
    <div class="scn-perm-ic accent"><span class="ping"></span>{!! $icon('camera') !!}</div>
    <h3>Requesting camera…</h3>
    <p>Tap <b>Allow</b> in your browser’s permission prompt to start scanning.</p>
    <div class="scn-actions">
        <button class="scn-btn ghost" disabled style="opacity:.7"><span class="scn-spin" style="width:18px;height:18px;display:inline-flex">{!! $icon('spinner') !!}</span>Waiting for permission</button>
        <button class="scn-btn link" @click="goManual()">Enter code manually instead</button>
    </div>
</div>

{{-- denied --}}
<div class="scn-perm" x-show="phase==='denied'" x-cloak>
    <div class="scn-perm-ic danger">{!! $icon('cameraOff') !!}</div>
    <h3>Camera access denied</h3>
    <p>You dismissed the permission request. Allow access to scan, or continue another way.</p>
    <div class="scn-actions">
        <button class="scn-btn primary" @click="requestCamera()">{!! $icon('refresh') !!}Try again</button>
        <button class="scn-btn link" @click="goManual()">Enter code manually</button>
    </div>
</div>

{{-- blocked --}}
<div class="scn-perm" x-show="phase==='blocked'" x-cloak>
    <div class="scn-perm-ic warn">{!! $icon('lock') !!}</div>
    <h3>Camera is blocked</h3>
    <p>Camera access is turned off for this site. Re-enable it to scan.</p>
    <div class="scn-steps">
        <div class="st"><span class="n">1</span><span>Tap the lock icon in the address bar</span>{!! $icon('lock') !!}</div>
        <div class="st"><span class="n">2</span><span>Set <b>Camera</b> to <b>Allow</b></span>{!! $icon('camera') !!}</div>
        <div class="st"><span class="n">3</span><span>Reload and scan again</span>{!! $icon('refresh') !!}</div>
    </div>
    <div class="scn-actions">
        <button class="scn-btn primary" @click="requestCamera()">{!! $icon('refresh') !!}Try again</button>
        <button class="scn-btn link" @click="goManual()">Enter code manually</button>
    </div>
</div>

{{-- nocamera --}}
<div class="scn-perm" x-show="phase==='nocamera'" x-cloak>
    <div class="scn-perm-ic muted">{!! $icon('cameraOff') !!}</div>
    <h3>No camera found</h3>
    <p>We couldn’t detect a camera on this device. You can still log units by entering their code.</p>
    <div class="scn-actions">
        <button class="scn-btn primary" @click="goManual()">{!! $icon('keyboard') !!}Enter code manually</button>
        <button class="scn-btn link" @click="requestCamera()">{!! $icon('refresh') !!}Check again</button>
    </div>
</div>

{{-- manual entry --}}
<div class="scn-manual" x-show="phase==='manual'" x-cloak>
    <div class="ttl">{!! $icon('keyboard') !!}Enter unit code</div>
    <div>
        <div class="scn-field">
            <input x-ref="manualInput" class="scn-input" placeholder="Serial / SKU — e.g. SN-A7IV-001"
                   x-model="manualVal" @input="manualErr=''" @keydown.enter.prevent="submitManual()">
            <button class="scn-go" :disabled="!manualVal.trim()" @click="submitManual()">Add</button>
        </div>
        <div class="scn-manual-err">
            <template x-if="manualErr">
                <span style="display:inline-flex;align-items:center;gap:6px">{!! $icon('alert') !!}<span x-text="manualErr"></span></span>
            </template>
        </div>
    </div>
    <div class="lbl" x-text="'Units in this ' + (isReturn?'return':'pickup')"></div>
    <div class="scn-suggest">
        <template x-for="it in items" :key="it.id">
            <button class="sg" :class="it.checked?'done':''" @click="submitManual(it.serial || it.name)">
                <div class="sg-main">
                    <span class="sg-name" x-text="it.name"></span>
                    <span class="sg-sn" x-text="it.serial || '—'"></span>
                </div>
                <span class="sg-state" :class="it.checked?'on':'off'"><template x-if="it.checked">{!! $icon('check') !!}</template></span>
            </button>
        </template>
    </div>
    <button class="scn-btn ghost" @click="backToCamera()">{!! $icon('camera') !!}Back to camera</button>
</div>
