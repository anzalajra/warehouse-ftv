@php
    $iconPath = \App\Models\Setting::get('pwa_admin_icon');
    $iconUrl = $iconPath ? asset('storage/' . $iconPath) : asset('favicon.ico');
    $themeColor = \App\Models\Setting::get('pwa_admin_theme_color', '#0ea5e9');
    $appName = \App\Models\Setting::get('pwa_admin_name', 'Warehouse FTV');
    $publicKey = config('webpush.vapid.public_key');
    $pushEnabled = (bool) \App\Models\Setting::get('pwa_admin_push_enabled', true);
@endphp

<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="manifest" href="{{ url('/admin/manifest.json') }}">
<meta name="theme-color" content="{{ $themeColor }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $appName }}">
<meta name="mobile-web-app-capable" content="yes">
<link rel="apple-touch-icon" href="{{ $iconUrl }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ $iconUrl }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ $iconUrl }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ $iconUrl }}">

<style>
    #wftv-install-banner {
        position: fixed;
        left: 50%;
        bottom: 20px;
        transform: translateX(-50%);
        z-index: 9999;
        max-width: 92vw;
        width: 380px;
        background: #ffffff;
        color: #0f172a;
        border-radius: 16px;
        box-shadow: 0 12px 32px rgba(0,0,0,0.18);
        padding: 14px 16px;
        display: none;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        border: 1px solid #e2e8f0;
    }
    .dark #wftv-install-banner { background: #1e293b; color: #f1f5f9; border-color: #334155; }
    #wftv-install-banner.show { display: block; animation: wftv-slide 0.25s ease-out; }
    @keyframes wftv-slide { from { transform: translate(-50%, 20px); opacity: 0; } to { transform: translate(-50%, 0); opacity: 1; } }
    #wftv-install-banner .wftv-row { display: flex; align-items: center; gap: 12px; }
    #wftv-install-banner img { width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0; }
    #wftv-install-banner .wftv-title { font-weight: 600; font-size: 14px; margin-bottom: 2px; }
    #wftv-install-banner .wftv-sub { font-size: 12px; opacity: 0.75; line-height: 1.4; }
    #wftv-install-banner .wftv-actions { margin-top: 12px; display: flex; gap: 8px; justify-content: flex-end; }
    #wftv-install-banner button {
        border: none; cursor: pointer; padding: 8px 14px; border-radius: 8px;
        font-size: 13px; font-weight: 500;
    }
    #wftv-install-banner .wftv-primary { background: {{ $themeColor }}; color: white; }
    #wftv-install-banner .wftv-secondary { background: transparent; color: inherit; opacity: 0.7; }
    #wftv-ios-instructions { font-size: 12px; margin-top: 8px; padding: 8px; background: #f1f5f9; border-radius: 8px; display: none; }
    .dark #wftv-ios-instructions { background: #0f172a; }
    #wftv-ios-instructions.show { display: block; }
    #wftv-ios-instructions kbd { background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 1px 5px; font-family: inherit; }
    .dark #wftv-ios-instructions kbd { background: #334155; border-color: #475569; }
</style>

<div id="wftv-install-banner" role="dialog" aria-label="Install app">
    <div class="wftv-row">
        <img src="{{ $iconUrl }}" alt="{{ $appName }}">
        <div style="flex:1; min-width:0;">
            <div class="wftv-title">Install {{ $appName }}</div>
            <div class="wftv-sub">Pasang di home screen untuk akses lebih cepat & notifikasi langsung di HP.</div>
        </div>
    </div>
    <div id="wftv-ios-instructions">
        Tap tombol <strong>Share</strong> <kbd>&#x2191;</kbd> di Safari, lalu pilih <strong>Add to Home Screen</strong>.
    </div>
    <div class="wftv-actions">
        <button type="button" class="wftv-secondary" id="wftv-dismiss">Nanti</button>
        <button type="button" class="wftv-primary" id="wftv-install-btn">Install</button>
    </div>
</div>

<div id="wftv-enable-push-banner" role="dialog" aria-label="Enable notifications" style="position:fixed;left:50%;bottom:20px;transform:translateX(-50%);z-index:9999;max-width:92vw;width:380px;background:#fff;color:#0f172a;border-radius:16px;box-shadow:0 12px 32px rgba(0,0,0,0.18);padding:14px 16px;display:none;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;border:1px solid #e2e8f0;">
    <div style="display:flex;align-items:center;gap:12px;">
        <img src="{{ $iconUrl }}" alt="" style="width:44px;height:44px;border-radius:10px;flex-shrink:0;">
        <div style="flex:1;min-width:0;">
            <div style="font-weight:600;font-size:14px;margin-bottom:2px;">Aktifkan Notifikasi</div>
            <div style="font-size:12px;opacity:0.75;line-height:1.4;">Dapatkan notifikasi rental, booking, & alert langsung di HP.</div>
        </div>
    </div>
    <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;">
        <button type="button" id="wftv-push-skip" style="border:none;cursor:pointer;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:500;background:transparent;color:inherit;opacity:0.7;">Nanti</button>
        <button type="button" id="wftv-push-enable" style="border:none;cursor:pointer;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:500;background:{{ $themeColor }};color:white;">Aktifkan</button>
    </div>
</div>

<script>
(function () {
    const PUBLIC_KEY = @json($publicKey);
    const PUSH_ENABLED = @json($pushEnabled);
    const APP_NAME = @json($appName);

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true
            || document.referrer.startsWith('android-app://');
    }

    // ---- Service worker registration ----
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ url('/admin/sw.js') }}', { scope: '/admin' })
                .then(function (reg) {
                    window.__wftvAdminReg = reg;
                    if (window.wftvDebug) console.log('[WFTV] SW registered', reg.scope);
                    if (PUSH_ENABLED && PUBLIC_KEY) {
                        maybeSubscribePush(reg);
                    } else if (window.wftvDebug) {
                        console.warn('[WFTV] push skipped: enabled=' + PUSH_ENABLED + ' key=' + (!!PUBLIC_KEY));
                    }
                })
                .catch(function (err) {
                    console.warn('[Admin PWA] SW registration failed', err);
                    if (window.wftvDebug) alert('SW gagal: ' + err.message);
                });
        });
    }

    // Enable verbose debug by appending ?wftvdebug=1 to URL once.
    if (location.search.indexOf('wftvdebug=1') >= 0) {
        window.wftvDebug = true;
        try { localStorage.setItem('wftvDebug', '1'); } catch (e) {}
    }
    try { if (localStorage.getItem('wftvDebug') === '1') window.wftvDebug = true; } catch (e) {}

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(base64);
        const output = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; ++i) output[i] = raw.charCodeAt(i);
        return output;
    }

    // Auto-resubscribe an existing permission. Does NOT call requestPermission()
    // because Safari/iOS rejects that call when it's not in a user-gesture handler.
    async function maybeSubscribePush(registration) {
        try {
            if (!('PushManager' in window)) return;
            if (Notification.permission === 'denied') return;

            const existing = await registration.pushManager.getSubscription();
            if (existing) {
                await postSubscription(existing);
                return;
            }

            if (Notification.permission !== 'granted') {
                // Need user gesture -> show the "Enable Notifications" banner
                // when running as installed PWA. In a regular browser tab we
                // wait until install instead of spamming the prompt.
                if (isStandalone()) showEnablePushBanner();
                return;
            }

            // Permission already granted (e.g. previous session) but no
            // subscription yet -> subscribe silently.
            const sub = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(PUBLIC_KEY),
            });
            await postSubscription(sub);
        } catch (e) {
            console.warn('[Admin PWA] Push subscribe failed', e);
        }
    }

    // MUST be called from a user-gesture handler (click/tap) for iOS Safari.
    async function requestPushPermissionFromGesture() {
        try {
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                alert('Browser ini tidak mendukung notifikasi push.');
                return false;
            }
            // Always subscribe through the ADMIN service worker. `serviceWorker.ready`
            // can resolve to the storefront SW (scope '/') which also controls /admin —
            // subscribing there would make a second SW deliver the same push (double).
            const reg = window.__wftvAdminReg
                || await navigator.serviceWorker.getRegistration('{{ url('/admin') }}')
                || await navigator.serviceWorker.ready;

            const perm = await Notification.requestPermission();
            if (perm !== 'granted') {
                if (perm === 'denied') {
                    alert('Izin notifikasi ditolak. Aktifkan manual di Settings > Notifications.');
                }
                return false;
            }

            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(PUBLIC_KEY),
            });
            await postSubscription(sub);
            return true;
        } catch (e) {
            console.error('[Admin PWA] Enable push failed', e);
            alert('Gagal mengaktifkan notifikasi: ' + (e && e.message ? e.message : e));
            return false;
        }
    }

    const PUSH_SKIP_KEY = 'wftv_push_skipped_at';
    function pushRecentlySkipped() {
        const ts = parseInt(localStorage.getItem(PUSH_SKIP_KEY) || '0', 10);
        if (!ts) return false;
        return (Date.now() - ts) / (1000 * 60 * 60 * 24) < 3; // 3 days
    }

    function showEnablePushBanner() {
        if (pushRecentlySkipped()) return;
        const el = document.getElementById('wftv-enable-push-banner');
        if (!el) return;
        el.style.display = 'block';

        const enableBtn = document.getElementById('wftv-push-enable');
        const skipBtn = document.getElementById('wftv-push-skip');
        enableBtn.onclick = async () => {
            enableBtn.disabled = true;
            enableBtn.textContent = 'Memproses...';
            const ok = await requestPushPermissionFromGesture();
            el.style.display = 'none';
            if (ok) {
                alert('Notifikasi aktif! Test kirim dari admin desktop.');
            } else {
                localStorage.setItem(PUSH_SKIP_KEY, Date.now().toString());
            }
        };
        skipBtn.onclick = () => {
            localStorage.setItem(PUSH_SKIP_KEY, Date.now().toString());
            el.style.display = 'none';
        };
    }

    async function postSubscription(sub) {
        const csrf = document.querySelector('meta[name="csrf-token"]');
        const token = csrf ? csrf.getAttribute('content') : '';
        await fetch('{{ url('/admin/push/subscribe') }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(sub.toJSON()),
        });
    }

    // Public hook so other UI (e.g. a button in settings) can trigger the prompt.
    window.wftvRequestPushPermission = requestPushPermissionFromGesture;

    // ---- Install banner ----
    const DISMISS_KEY = 'wftv_install_dismissed_at';
    const DISMISS_DAYS = 7;

    function recentlyDismissed() {
        const ts = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
        if (!ts) return false;
        const ageDays = (Date.now() - ts) / (1000 * 60 * 60 * 24);
        return ageDays < DISMISS_DAYS;
    }

    if (isStandalone()) {
        // Already installed -> never show banner
        document.documentElement.classList.add('wftv-pwa-standalone');
        return;
    }

    const ua = navigator.userAgent || '';
    const isIos = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    const isMobile = /Mobi|Android|iPhone|iPad|iPod/i.test(ua);

    if (!isMobile) return; // only show on mobile browsers
    if (recentlyDismissed()) return;

    const banner = document.getElementById('wftv-install-banner');
    const installBtn = document.getElementById('wftv-install-btn');
    const dismissBtn = document.getElementById('wftv-dismiss');
    const iosBox = document.getElementById('wftv-ios-instructions');

    function showBanner() {
        if (!banner) return;
        banner.classList.add('show');
    }

    function dismiss() {
        localStorage.setItem(DISMISS_KEY, Date.now().toString());
        banner.classList.remove('show');
    }

    dismissBtn.addEventListener('click', dismiss);

    let deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        showBanner();
    });

    installBtn.addEventListener('click', async function () {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;
            deferredPrompt = null;
            if (choice && choice.outcome === 'accepted') {
                banner.classList.remove('show');
            } else {
                dismiss();
            }
        } else if (isIos) {
            iosBox.classList.add('show');
            installBtn.textContent = 'OK';
        } else {
            dismiss();
        }
    });

    // iOS Safari never fires beforeinstallprompt -> show banner manually after delay
    if (isIos) {
        setTimeout(showBanner, 2500);
    }

    window.addEventListener('appinstalled', function () {
        banner.classList.remove('show');
        localStorage.removeItem(DISMISS_KEY);
    });
})();
</script>
