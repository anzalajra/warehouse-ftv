{{--
    Global loading indicator for the admin panel.

    Two surfaces, both driven by Livewire's `commit` hook so they fire on *every*
    Livewire network round-trip across the whole panel (no per-component wiring):

      1. A thin NProgress-style bar pinned to the very top of the viewport.
      2. A small spinning ring (no text) in the bottom-right corner.

    Lives in `panels::body.end` (outside every Livewire component root) so it is
    never touched by Livewire's DOM morph — that's why we drive it from the JS hook
    instead of `wire:loading`, which can stick "on" when a re-rendered element is
    morphed in with the server's default visible state.

    A ~120ms show delay keeps fast requests from flashing; only requests that take
    longer than that ever paint.
--}}
<div id="gr-progress" aria-hidden="true"></div>

<div id="gr-spinner" aria-hidden="true"></div>

<style>
    #gr-progress {
        position: fixed;
        top: 0;
        left: 0;
        height: 3px;
        width: 0;
        opacity: 0;
        z-index: 99999;
        background: linear-gradient(90deg,
            var(--primary-500, #3b82f6),
            var(--primary-400, #60a5fa));
        box-shadow: 0 0 8px 0 var(--primary-500, #3b82f6);
        border-top-right-radius: 3px;
        border-bottom-right-radius: 3px;
        pointer-events: none;
        transition: width .2s ease, opacity .35s ease;
    }

    #gr-spinner {
        position: fixed;
        right: 20px;
        bottom: 20px;
        z-index: 99999;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: 3px solid var(--primary-200, #bfdbfe);
        border-top-color: var(--primary-500, #3b82f6);
        background: rgba(255, 255, 255, .6);
        box-shadow: 0 4px 14px rgba(0, 0, 0, .12);
        opacity: 0;
        transform: scale(.85);
        pointer-events: none;
        transition: opacity .2s ease, transform .2s ease;
        animation: gr-spin .6s linear infinite;
    }
    #gr-spinner.is-on {
        opacity: 1;
        transform: scale(1);
    }
    .dark #gr-spinner {
        border-color: var(--gray-600, #52525b);
        border-top-color: var(--primary-400, #60a5fa);
        background: rgba(24, 24, 27, .6);
    }

    @keyframes gr-spin {
        to { transform: rotate(360deg); }
    }

    @media (prefers-reduced-motion: reduce) {
        #gr-progress { transition: opacity .35s ease; }
        #gr-spinner { animation-duration: 1.2s; }
    }
</style>

<script>
    (function () {
        // Guard against double-registration (Livewire SPA navigation re-runs scripts).
        if (window.__grLoadingInit) return;
        window.__grLoadingInit = true;

        var active = 0;       // in-flight Livewire commits
        var shown = false;    // bar/spinner currently painted
        var progress = 0;     // 0..1 trickle position
        var showTimer = null;
        var trickleTimer = null;
        var hideTimer = null;
        var _bar = null;
        var _spinner = null;

        function bar() { return _bar || (_bar = document.getElementById('gr-progress')); }
        function spinner() { return _spinner || (_spinner = document.getElementById('gr-spinner')); }

        function paint() {
            var b = bar(), s = spinner();
            if (!b) return;
            shown = true;
            clearTimeout(hideTimer);
            progress = 0.08;
            b.style.transition = 'none';
            b.style.width = '0%';
            b.style.opacity = '1';
            // force reflow so the next width change animates from 0
            void b.offsetWidth;
            b.style.transition = 'width .2s ease, opacity .35s ease';
            b.style.width = '8%';
            if (s) s.classList.add('is-on');
            trickle();
        }

        function trickle() {
            clearTimeout(trickleTimer);
            trickleTimer = setTimeout(function () {
                var b = bar();
                if (!b) return;
                progress = Math.min(0.9, progress + (1 - progress) * 0.12);
                b.style.width = (progress * 100) + '%';
                trickle();
            }, 280);
        }

        function start() {
            active++;
            if (active > 1) return;
            clearTimeout(showTimer);
            // Delay so quick (<120ms) round-trips never flash.
            showTimer = setTimeout(paint, 120);
        }

        function finish() {
            active = Math.max(0, active - 1);
            if (active > 0) return;
            clearTimeout(showTimer);
            clearTimeout(trickleTimer);
            if (!shown) return; // request finished before the bar ever painted
            shown = false;
            var b = bar(), s = spinner();
            if (s) s.classList.remove('is-on');
            if (!b) return;
            b.style.width = '100%';
            hideTimer = setTimeout(function () {
                b.style.opacity = '0';
                setTimeout(function () {
                    if (!shown) b.style.width = '0%';
                }, 300);
            }, 180);
        }

        function registerHook() {
            if (!window.Livewire || !Livewire.hook || window.__grHookDone) return;
            window.__grHookDone = true;
            Livewire.hook('commit', function (payload) {
                start();
                // `respond` fires once the server has answered (success or fail),
                // i.e. the network round-trip — what we actually wait on — is done.
                if (typeof payload.respond === 'function') {
                    payload.respond(finish);
                } else {
                    // Fallback for older signatures: settle on succeed/fail.
                    if (typeof payload.succeed === 'function') payload.succeed(finish);
                    if (typeof payload.fail === 'function') payload.fail(finish);
                }
            });
        }

        // Register as early and as robustly as possible — `livewire:init` may have
        // already fired by the time this inline script runs, so also try right now.
        document.addEventListener('livewire:init', registerHook);
        registerHook();

        // Full-page (wire:navigate) transitions: show the bar during navigation too.
        document.addEventListener('livewire:navigate', start);
        document.addEventListener('livewire:navigated', finish);

        // Safety net: never let the indicator get stuck on if something throws.
        window.addEventListener('pagehide', function () {
            active = 0;
            finish();
        });
    })();
</script>
