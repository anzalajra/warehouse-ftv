@php
    $appName = \App\Models\Setting::get('pwa_admin_name', 'Warehouse FTV');
    $iconPath = \App\Models\Setting::get('pwa_admin_icon');
    $iconUrl = $iconPath ? asset('storage/' . $iconPath) : asset('favicon.ico');
@endphp
// Warehouse FTV Admin PWA Service Worker
const SW_VERSION = 'admin-pwa-v1';
const DEFAULT_TITLE = {!! json_encode($appName) !!};
const DEFAULT_ICON = {!! json_encode($iconUrl) !!};

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

// No fetch cache: Filament/Livewire is too dynamic to cache safely.
// An installed PWA still works because the browser caches normally and the
// presence of a fetch handler keeps the app installable.
self.addEventListener('fetch', (event) => {
    // pass-through
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: DEFAULT_TITLE, body: event.data ? event.data.text() : '' };
    }

    const title = data.title || DEFAULT_TITLE;
    const options = {
        body: data.body || '',
        icon: data.icon || DEFAULT_ICON,
        badge: data.badge || DEFAULT_ICON,
        tag: data.tag || 'wftv-' + Date.now(),
        renotify: true,
        data: { url: data.url || '/admin' },
        vibrate: [120, 60, 120],
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) || '/admin';

    event.waitUntil(
        (async () => {
            const clientsList = await self.clients.matchAll({
                type: 'window',
                includeUncontrolled: true,
            });
            for (const client of clientsList) {
                if (client.url.includes('/admin') && 'focus' in client) {
                    await client.focus();
                    if ('navigate' in client) {
                        try { await client.navigate(targetUrl); } catch (e) {}
                    }
                    return;
                }
            }
            if (self.clients.openWindow) {
                await self.clients.openWindow(targetUrl);
            }
        })()
    );
});
