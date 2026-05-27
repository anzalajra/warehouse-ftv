@php
    $iconPath = \App\Models\Setting::get('pwa_admin_icon');
    $iconUrl = $iconPath ? asset('storage/' . $iconPath) : asset('favicon.ico');
    $themeColor = \App\Models\Setting::get('pwa_admin_theme_color', '#0ea5e9');
    $appName = \App\Models\Setting::get('pwa_admin_name', 'Warehouse FTV');
    $publicKey = config('webpush.vapid.public_key');
    $pushEnabled = (bool) \App\Models\Setting::get('pwa_admin_push_enabled', true);
@endphp

<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="manifest" href="{{ url('/admin/manifest.webmanifest') }}">
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
                    if (PUSH_ENABLED && PUBLIC_KEY) {
                        maybeSubscribePush(reg);
                    }
                })
                .catch(function (err) {
                    console.warn('[Admin PWA] SW registration failed', err);
                });
        });
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(base64);
        const output = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; ++i) output[i] = raw.charCodeAt(i);
        return output;
    }

    async function maybeSubscribePush(registration) {
        try {
            if (!('PushManager' in window)) return;
            if (Notification.permission === 'denied') return;

            // Only auto-prompt when running as installed PWA, OR explicitly requested.
            // First-time visitors in browser don't get a popup spam.
            const existing = await registration.pushManager.getSubscription();
            if (existing) {
                await postSubscription(existing);
                return;
            }

            if (Notification.permission !== 'granted') {
                if (!isStandalone()) return; // wait until they install
                const perm = await Notification.requestPermission();
                if (perm !== 'granted') return;
            }

            const sub = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(PUBLIC_KEY),
            });
            await postSubscription(sub);
        } catch (e) {
            console.warn('[Admin PWA] Push subscribe failed', e);
        }
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

    window.wftvRequestPushPermission = async function () {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            alert('Browser ini tidak mendukung notifikasi push.');
            return;
        }
        const reg = await navigator.serviceWorker.ready;
        const perm = await Notification.requestPermission();
        if (perm !== 'granted') {
            alert('Izin notifikasi ditolak. Aktifkan di pengaturan browser/aplikasi.');
            return;
        }
        await maybeSubscribePush(reg);
        alert('Notifikasi diaktifkan!');
    };

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
