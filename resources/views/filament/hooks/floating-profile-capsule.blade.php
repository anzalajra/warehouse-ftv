{{--
    Floating profile capsule — custom replacement for Filament's built-in
    sidebar-footer "Dynamic Island" (user menu + notifications). Rendered via
    render hook `panels::body.end` (sidebar mode only; phones keep the top-nav
    user menu). Adapted from the Zewalo project.

    Responsive:
      - Sidebar / landscape (body:not(.gr-compact)): floating bottom-left, full
        pill (avatar + name/role + bell + QR). The old .fi-sidebar-footer island
        is hidden here.
      - Compact / tablet-portrait + narrow (body.gr-compact): the capsule stays at
        the bottom-left but is lifted to sit just ABOVE the fixed bottom navigation
        bar, and shrinks to avatar + icons. It expands rightward and its popups
        open upward (same as the desktop anchor). (.fi-sidebar-footer is already
        hidden in compact by the responsive-navigation hook.)
--}}
@php
    $user = auth()->user();
@endphp
@if ($user)
@php
    $name = $user->name ?? 'User';
    $first = explode(' ', $name)[0];
    $initials = strtoupper(collect(explode(' ', $name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));

    // Role label — first role name if present, else "Admin"
    $roleLabel = 'Admin';
    try {
        if (method_exists($user, 'getRoleNames')) {
            $roleLabel = $user->getRoleNames()->first() ?? 'Admin';
            $roleLabel = ucwords(str_replace('_', ' ', $roleLabel));
        }
    } catch (\Throwable $e) {
        // ignore
    }

    $tenantName = \App\Models\Setting::get('site_name') ?? config('app.name');

    // Unread DB notifications
    try {
        $unreadCount = $user->unreadNotifications()->count();
        $latestNotifs = $user->notifications()->latest()->limit(5)->get()->map(function ($n) {
            $data = $n->data ?? [];
            return [
                'id' => $n->id,
                'title' => $data['title'] ?? ($data['subject'] ?? 'Notifikasi'),
                'body' => $data['body'] ?? ($data['message'] ?? ''),
                'unread' => $n->read_at === null,
                'time' => $n->created_at?->diffForHumans() ?? '',
                'url' => $data['url'] ?? null,
            ];
        })->all();
    } catch (\Throwable $e) {
        $unreadCount = 0;
        $latestNotifs = [];
    }

    $logoutUrl = filament()->getLogoutUrl();

    try {
        $profileUrl = filament()->getProfileUrl();
    } catch (\Throwable $e) {
        $profileUrl = null;
    }

    try {
        $settingsUrl = \App\Filament\Clusters\Settings\Pages\GeneralSettings::getUrl();
    } catch (\Throwable $e) {
        $settingsUrl = null;
    }
@endphp

<div
    x-data="{
        open: false,
        notifOpen: false,
        expanded: false,
        _t: null,
        isCompact() { return window.matchMedia('(orientation: portrait)').matches || window.innerWidth < 1024; },
        scheduleMinimize() {
            if (! this.isCompact()) return;
            clearTimeout(this._t);
            this._t = setTimeout(() => { if (! this.open && ! this.notifOpen) this.expanded = false; }, 4000);
        },
        expand() { if (this.isCompact()) { this.expanded = true; this.scheduleMinimize(); } },
        avatarClick() {
            if (this.isCompact() && ! this.expanded) { this.expand(); return; }
            this.toggleProfile();
        },
        toggleProfile() { this.open = !this.open; this.notifOpen = false; this.scheduleMinimize(); },
        toggleNotif()   { this.notifOpen = !this.notifOpen; this.open = false; this.scheduleMinimize(); },
        closeAll()      { this.open = false; this.notifOpen = false; this.expanded = false; clearTimeout(this._t); },
    }"
    @click.outside="closeAll()"
    @keydown.escape.window="closeAll()"
    class="zw-capsule-root"
>
    {{-- Profile popup --}}
    <div
        x-show="open"
        x-transition:enter="zw-pop-enter"
        x-transition:enter-start="zw-pop-enter-from"
        x-transition:enter-end="zw-pop-enter-to"
        x-cloak
        class="zw-capsule__profile"
    >
        <div class="zw-capsule__profile-head">
            <div class="zw-capsule__profile-name">{{ $name }}</div>
            <div class="zw-capsule__profile-tenant">{{ $tenantName }}</div>
        </div>

        {{-- Theme switcher (light / dark / system) — reuses Filament's own
             `theme-changed` window event (dark-mode.js persists to localStorage +
             toggles the .dark class on <html>). `theme` tracks the raw selection
             for the active-button state. --}}
        <div class="zw-capsule__theme">
            <span class="zw-capsule__theme-label">Tema</span>
            <div class="zw-capsule__theme-seg"
                 x-data="{ theme: null }"
                 x-init="$watch('theme', () => $dispatch('theme-changed', theme)); theme = localStorage.getItem('theme') || @js(filament()->getDefaultThemeMode()->value)">
                <button type="button" @click="theme = 'light'" :class="{ 'is-active': theme === 'light' }" aria-label="Terang">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                </button>
                <button type="button" @click="theme = 'dark'" :class="{ 'is-active': theme === 'dark' }" aria-label="Gelap">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                </button>
                <button type="button" @click="theme = 'system'" :class="{ 'is-active': theme === 'system' }" aria-label="Sistem">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                </button>
            </div>
        </div>

        <div class="zw-capsule__profile-list">
            @if ($profileUrl)
            <a href="{{ $profileUrl }}" class="zw-capsule__profile-item" wire:navigate>
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                <span>My Profile</span>
            </a>
            @endif
            <form method="POST" action="{{ $logoutUrl }}" class="zw-capsule__profile-form">
                @csrf
                <button type="submit" class="zw-capsule__profile-item zw-capsule__profile-item--danger">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                    <span>Keluar</span>
                </button>
            </form>
        </div>
    </div>

    {{-- Notifications popup --}}
    <div
        x-show="notifOpen"
        x-transition:enter="zw-pop-enter"
        x-transition:enter-start="zw-pop-enter-from"
        x-transition:enter-end="zw-pop-enter-to"
        x-cloak
        class="zw-capsule__notif"
    >
        <div class="zw-capsule__notif-head">
            <span class="zw-capsule__notif-title">Notifikasi</span>
            @if ($unreadCount > 0)
                <span class="zw-capsule__notif-action">{{ $unreadCount }} belum dibaca</span>
            @endif
        </div>

        @forelse ($latestNotifs as $n)
            <a href="{{ $n['url'] ?? '/admin' }}"
               class="zw-capsule__notif-item @if ($n['unread']) zw-capsule__notif-item--unread @endif"
               wire:navigate>
                <span class="zw-capsule__notif-dot @if ($n['unread']) zw-capsule__notif-dot--on @endif"></span>
                <div class="zw-capsule__notif-body">
                    <div class="zw-capsule__notif-text">{{ $n['title'] }}</div>
                    @if ($n['body'])
                        <div class="zw-capsule__notif-detail">{{ \Illuminate\Support\Str::limit(strip_tags($n['body']), 60) }}</div>
                    @endif
                    <div class="zw-capsule__notif-time">{{ $n['time'] }}</div>
                </div>
            </a>
        @empty
            <div class="zw-capsule__notif-empty">Belum ada notifikasi</div>
        @endforelse
    </div>

    {{-- Capsule itself. On compact (tablet/mobile) it starts minimized as just the
         avatar circle + a left chevron hint; tapping expands it into the full pill
         (animated), then it auto-minimizes. On desktop it is always fully expanded. --}}
    <div class="zw-capsule" :class="{ 'is-expanded': expanded }" role="region" aria-label="Profile capsule">
        <button type="button" @click="expand()" class="zw-capsule__hint" aria-label="Buka menu" tabindex="-1">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </button>
        <button type="button" @click="avatarClick()" class="zw-capsule__avatar-btn" aria-label="Open profile menu">
            <span class="zw-capsule__avatar">{{ $initials ?: '?' }}</span>
        </button>
        <button type="button" @click="avatarClick()" class="zw-capsule__id">
            <div class="zw-capsule__name">{{ $first }}</div>
            <div class="zw-capsule__role">{{ $roleLabel }}</div>
        </button>
        <div class="zw-capsule__actions">
            <button type="button" @click="toggleNotif()" class="zw-capsule__icon-btn" aria-label="Notifications">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                </svg>
                @if ($unreadCount > 0)
                    <span class="zw-capsule__notif-badge"></span>
                @endif
            </button>
            @if ($settingsUrl)
            <a href="{{ $settingsUrl }}" class="zw-capsule__icon-btn" aria-label="Pengaturan" title="Pengaturan" wire:navigate>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.03 7.03 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.241.437-.613.43-.992a7.03 7.03 0 0 1 0-.255c.007-.378-.138-.75-.43-.991l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.281Z"/><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
            </a>
            @endif
            <button type="button" class="zw-capsule__icon-btn" aria-label="QR Scanner"
                    @click="scheduleMinimize()"
                    onclick="window.dispatchEvent(new CustomEvent('zw:scanner-open'))">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5ZM6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z"/>
                </svg>
            </button>
        </div>
    </div>
</div>

@once
<style>
    /* ───────────────────────────────────────────────────────
       FLOATING PROFILE CAPSULE
       ─────────────────────────────────────────────────────── */

    [x-cloak] { display: none !important; }

    .zw-capsule-root {
        position: fixed; bottom: 20px; left: 20px; z-index: 9999;
        display: flex; flex-direction: column; align-items: flex-start; gap: 10px;
        font-family: 'Figtree', ui-sans-serif, system-ui, sans-serif;
    }

    /* Hide Filament's built-in sidebar-footer island in sidebar/expanded mode.
       In compact mode it is already hidden by the responsive-navigation hook,
       so we scope this to :not(.gr-compact) to avoid fighting that rule.
       `.fi-body` / `.fi-body-has-topbar` live on the <body> element itself
       (so they must be chained on `body`, not used as a descendant), matching
       theme.css's island rule `.fi-body:not(.fi-body-has-topbar) .fi-sidebar-footer`
       (which sets `display:flex !important`, specificity 0,3,0). Adding
       `body...:not(.gr-compact)` lifts ours to 0,4,1 so `display:none` wins. */
    body.fi-body:not(.gr-compact):not(.fi-body-has-topbar) .fi-sidebar-footer,
    body:not(.gr-compact) .fi-sidebar-footer {
        display: none !important;
    }

    .zw-capsule {
        background: #1f2937;
        border-radius: 999px;
        padding: 7px 10px 7px 8px;
        display: flex; align-items: center; gap: 10px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.25);
        border: 1px solid rgba(255,255,255,0.08);
        user-select: none;
        min-width: 264px;
    }
    .zw-capsule__avatar-btn { background:none; border:none; padding:0; cursor:pointer; flex-shrink:0; }
    .zw-capsule__avatar {
        display:grid; place-items:center;
        width: 36px; height: 36px; border-radius: 999px;
        background: var(--primary-600, #0284c7); color: #fff;
        font-size: 13px; font-weight: 800;
        transition: opacity 150ms;
    }
    .zw-capsule__avatar-btn:hover .zw-capsule__avatar { opacity: 0.85; }

    .zw-capsule__id {
        background:none; border:none; padding:0; cursor:pointer;
        flex: 1; min-width: 0; text-align: left; color: inherit;
    }
    .zw-capsule__name { font-size: 13px; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .zw-capsule__role { font-size: 10.5px; color: rgba(255,255,255,0.5); margin-top: 1px; }

    .zw-capsule__icon-btn {
        width: 34px; height: 34px; border-radius: 999px;
        border: none; cursor: pointer;
        background: rgba(255,255,255,0.08);
        color: rgba(255,255,255,0.7);
        display: grid; place-items: center;
        position: relative; flex-shrink: 0;
        transition: background 150ms;
        text-decoration: none;
    }
    .zw-capsule__icon-btn:hover { background: rgba(255,255,255,0.16); color: #fff; }
    .zw-capsule__notif-badge {
        position: absolute; top: 6px; right: 6px;
        width: 8px; height: 8px; border-radius: 999px;
        background: #ef4444; border: 1.5px solid #1f2937;
    }

    /* Actions group (bell / settings / QR). Inline on desktop; collapses on compact. */
    .zw-capsule__actions { display: flex; align-items: center; gap: 10px; }

    /* Left chevron hint — only shown on compact while minimized (see gr-compact below). */
    .zw-capsule__hint { display: none; }

    .zw-capsule__profile {
        background: #1f2937; border-radius: 16px;
        box-shadow: 0 8px 40px rgba(0,0,0,0.35);
        padding: 6px 0; width: 240px; overflow: hidden;
        border: 1px solid rgba(255,255,255,0.08);
    }
    .zw-capsule__profile-head { padding: 14px 18px 12px; border-bottom: 1px solid rgba(255,255,255,0.08); }
    .zw-capsule__profile-name { font-size: 14px; font-weight: 700; color: #fff; }
    .zw-capsule__profile-tenant { font-size: 11.5px; color: rgba(255,255,255,0.45); margin-top: 1px; }

    /* Theme switcher row */
    .zw-capsule__theme {
        padding: 10px 18px; display: flex; align-items: center; justify-content: space-between; gap: 10px;
        border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .zw-capsule__theme-label { font-size: 12.5px; font-weight: 500; color: rgba(255,255,255,0.7); }
    .zw-capsule__theme-seg {
        display: inline-flex; gap: 2px;
        background: rgba(255,255,255,0.06); border-radius: 999px; padding: 3px;
    }
    .zw-capsule__theme-seg button {
        width: 28px; height: 28px; border: none; border-radius: 999px;
        background: transparent; color: rgba(255,255,255,0.55); cursor: pointer;
        display: grid; place-items: center; transition: background 150ms, color 150ms;
    }
    .zw-capsule__theme-seg button:hover { color: #fff; }
    .zw-capsule__theme-seg button.is-active { background: var(--primary-600, #0284c7); color: #fff; }
    .zw-capsule__profile-list { padding: 6px 0; }
    .zw-capsule__profile-form { margin: 0; padding: 0; }
    .zw-capsule__profile-form button { width: 100%; background: none; border: none; cursor: pointer; font-family: inherit; }
    .zw-capsule__profile-item {
        padding: 11px 18px; display: flex; align-items: center; gap: 12px;
        cursor: pointer; transition: background 100ms;
        color: rgba(255,255,255,0.85);
        font-size: 13.5px; font-weight: 500; text-decoration: none;
    }
    .zw-capsule__profile-item:hover { background: rgba(255,255,255,0.06); color: #fff; }
    .zw-capsule__profile-item--danger { color: #f87171; }
    .zw-capsule__profile-item--danger:hover { background: rgba(248,113,113,0.1); color: #fca5a5; }

    .zw-capsule__notif {
        background: #fff; border-radius: 16px;
        box-shadow: 0 8px 40px rgba(0,0,0,0.18);
        border: 1px solid #e5e7eb;
        width: 320px; max-height: 70vh; overflow-y: auto;
    }
    .dark .zw-capsule__notif { background: #1f2937; border-color: #374151; }
    .zw-capsule__notif-head {
        padding: 14px 18px 10px; border-bottom: 1px solid #f3f4f6;
        display: flex; justify-content: space-between; align-items: center;
        position: sticky; top: 0; background: inherit; z-index: 1;
    }
    .dark .zw-capsule__notif-head { border-bottom-color: #374151; }
    .zw-capsule__notif-title { font-size: 13px; font-weight: 700; color: #111827; }
    .dark .zw-capsule__notif-title { color: #f9fafb; }
    .zw-capsule__notif-action { font-size: 11px; color: var(--primary-600, #0284c7); font-weight: 600; cursor: pointer; text-decoration: none; }
    .zw-capsule__notif-item {
        padding: 11px 18px; display: flex; gap: 10px; align-items: flex-start;
        border-bottom: 1px solid #f9fafb; text-decoration: none; color: inherit;
    }
    .dark .zw-capsule__notif-item { border-bottom-color: #1f2937; }
    .zw-capsule__notif-item:last-child { border-bottom: none; }
    .zw-capsule__notif-item:hover { background: #f9fafb; }
    .dark .zw-capsule__notif-item:hover { background: #111827; }
    .zw-capsule__notif-item--unread { background: #f0f9ff; }
    .dark .zw-capsule__notif-item--unread { background: rgba(12,74,110,0.18); }
    .zw-capsule__notif-dot { width: 7px; height: 7px; border-radius: 999px; flex-shrink: 0; margin-top: 6px; }
    .zw-capsule__notif-dot--on { background: var(--primary-600, #0284c7); }
    .zw-capsule__notif-body { flex: 1; min-width: 0; }
    .zw-capsule__notif-text { font-size: 12.5px; color: #1f2937; font-weight: 600; line-height: 1.4; }
    .dark .zw-capsule__notif-text { color: #f9fafb; }
    .zw-capsule__notif-detail { font-size: 11.5px; color: #6b7280; margin-top: 2px; line-height: 1.4; }
    .zw-capsule__notif-time { font-size: 10.5px; color: #9ca3af; margin-top: 4px; }
    .zw-capsule__notif-empty { padding: 28px 18px; text-align: center; font-size: 12.5px; color: #9ca3af; }

    .zw-pop-enter { transition: opacity 180ms cubic-bezier(0.34,1.56,0.64,1), transform 180ms cubic-bezier(0.34,1.56,0.64,1); }
    .zw-pop-enter-from { opacity: 0; transform: translateY(10px) scale(0.97); }
    .zw-pop-enter-to   { opacity: 1; transform: translateY(0) scale(1); }

    /* ───────────────────────────────────────────────────────
       RESPONSIVE: tablet portrait + mobile (compact mode) — COLLAPSIBLE capsule
       In gr-compact the capsule stays at the bottom-left but is lifted to sit just
       ABOVE the fixed bottom navigation bar (.gr-bottombar — 64px phones / 76px
       tablets, + safe-area). It starts MINIMIZED as just the avatar circle + a
       pulsing right chevron hint; tapping expands it (animated) rightward into the
       full pill with the icon actions, then it auto-minimizes (4s, JS). Popups open
       upward, same as the desktop anchor.
       ─────────────────────────────────────────────────────── */
    body.gr-compact .zw-capsule-root {
        top: auto;
        right: auto;
        left: 0.75rem;
        /* Clear the 64px bottom bar (+ safe area) with a small gap. */
        bottom: calc(64px + env(safe-area-inset-bottom, 0px) + 12px);
        flex-direction: column;
        align-items: flex-start;
    }
    /* Portrait tablets (>=768px) get the taller 76px bottom bar — lift to match. */
    @media (min-width: 768px) {
        body.gr-compact .zw-capsule-root {
            bottom: calc(76px + env(safe-area-inset-bottom, 0px) + 12px);
        }
    }
    body.gr-compact .zw-capsule {
        min-width: 0;
        transition: padding 240ms ease, gap 240ms ease;
    }
    body.gr-compact .zw-capsule__id { display: none; }
    body.gr-compact .zw-capsule__avatar { width: 32px; height: 32px; font-size: 12px; }
    body.gr-compact .zw-capsule__icon-btn { width: 32px; height: 32px; }
    body.gr-compact .zw-capsule__profile,
    body.gr-compact .zw-capsule__notif {
        max-width: calc(100vw - 1.5rem);
    }

    /* Minimized vs expanded shape */
    body.gr-compact .zw-capsule:not(.is-expanded) { gap: 3px; padding: 4px 6px 4px 5px; }
    body.gr-compact .zw-capsule.is-expanded { gap: 6px; padding: 5px 6px 5px 5px; }

    /* Action group collapses (width + fade) when minimized, expands when open */
    body.gr-compact .zw-capsule__actions {
        max-width: 0; opacity: 0; overflow: hidden; gap: 6px;
        transition: max-width 280ms cubic-bezier(0.34, 1.4, 0.64, 1), opacity 200ms ease;
    }
    body.gr-compact .zw-capsule.is-expanded .zw-capsule__actions {
        max-width: 160px; opacity: 1;
    }

    /* Chevron hint: visible + pulsing while minimized; collapses away on expand */
    body.gr-compact .zw-capsule__hint {
        display: inline-flex; align-items: center; justify-content: center;
        width: 16px; height: 32px; padding: 0; border: none; background: transparent;
        color: rgba(255, 255, 255, 0.75); cursor: pointer; flex-shrink: 0;
        overflow: hidden;
        transition: width 220ms ease, opacity 160ms ease;
        animation: zw-hint-pulse 1.4s ease-in-out infinite;
    }
    body.gr-compact .zw-capsule.is-expanded .zw-capsule__hint {
        width: 0; opacity: 0; pointer-events: none; animation: none;
    }

    @keyframes zw-hint-pulse {
        0%, 100% { transform: translateX(0); opacity: 0.5; }
        50%      { transform: translateX(3px); opacity: 1; }
    }
</style>
@endonce
@endif
