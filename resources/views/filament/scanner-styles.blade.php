{{--
    Shared scanner styles (.scn-*) — ported from the design handoff (scanner.css).
    Used by BOTH the Pickup/Return unit scanner popup and the global admin QR
    scanner hook so they share an identical look & feel. Light defaults; dark
    overrides under `.dark`. Camera feed stays dark in both themes.

    Wrapped in @once so it emits a single time per request even when included by
    both the Pickup/Return popup and the global QR scanner hook on the same page.
--}}
@once
<style>
.scn-root {
    --scn-camera:    #0c0f14;     /* camera feed — always dark */
    --scn-bg:        #ffffff;
    --scn-surface:   #ffffff;
    --scn-surface-2: #f3f4f6;
    --scn-border:    #e5e7eb;
    --scn-text:      #111827;
    --scn-text-2:    #4b5563;
    --scn-muted:     #6b7280;
    --scn-accent:    #dc2626;
    --scn-accent-2:  #dc2626;
    --scn-success:   #16a34a;
    --scn-warning:   #d97706;
    --scn-scrim:     rgba(17,24,39,0.45);
    --scn-modal-shadow: 0 24px 70px rgba(17,24,39,0.22), 0 0 0 1px rgba(17,24,39,0.05);
    --scn-field:     #f9fafb;
    --scn-font:      'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
    --scn-mono:      'JetBrains Mono', ui-monospace, Menlo, monospace;
}
.dark .scn-root {
    --scn-bg:        #0c0f14;
    --scn-surface:   #161b22;
    --scn-surface-2: #1d242d;
    --scn-border:    #2a313b;
    --scn-text:      #eef1f5;
    --scn-text-2:    #aab2bd;
    --scn-muted:     #7b838f;
    --scn-accent:    #ef4444;
    --scn-accent-2:  #f87171;
    --scn-success:   #22c55e;
    --scn-warning:   #f59e0b;
    --scn-scrim:     rgba(4,6,9,0.66);
    --scn-modal-shadow: 0 24px 70px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.02) inset;
    --scn-field:     #0c0f14;
}

.scn-root, .scn-root * { box-sizing: border-box; }
.scn-root { font-family: var(--scn-font); -webkit-font-smoothing: antialiased; }
.scn-root svg { display: block; }

/* ---------- Desktop: scrim + modal ---------- */
.scn-scrim {
    position: fixed; inset: 0; z-index: 9999;
    display: grid; place-items: center; padding: 26px;
    /* solid scrim (no backdrop-filter blur — too expensive to re-render every
       frame on low-spec mobile GPUs while the camera feed is live) */
    background: var(--scn-scrim);
}
.scn-modal {
    width: 100%; max-width: 432px; max-height: 92vh;
    display: flex; flex-direction: column; overflow: hidden;
    background: var(--scn-surface);
    border: 1px solid var(--scn-border);
    border-radius: 18px;
    box-shadow: var(--scn-modal-shadow);
    color: var(--scn-text);
    animation: scn-pop .22s cubic-bezier(.16,1,.3,1);
}
@keyframes scn-pop { from { transform: scale(.96) translateY(10px); } to { transform: none; } }

/* ---------- Mobile: full-screen sheet ---------- */
.scn-sheet {
    position: fixed; inset: 0; z-index: 9999;
    display: flex; flex-direction: column; overflow: hidden;
    background: var(--scn-surface);
    color: var(--scn-text);
    animation: scn-slide .26s cubic-bezier(.2,.8,.2,1);
}
@keyframes scn-slide { from { transform: translateY(2.5%); } to { transform: none; } }

/* ---------- Header ---------- */
.scn-head { position: relative; z-index: 6; display: flex; align-items: center; gap: 12px; padding: 14px 16px; }
.scn-modal .scn-head { border-bottom: 1px solid var(--scn-border); }
.scn-sheet .scn-head { padding-top: 22px; }
.scn-sheet.on-cam .scn-head { background: linear-gradient(to bottom, rgba(0,0,0,0.6), rgba(0,0,0,0)); }
.scn-sheet.on-cam .scn-head .scn-title b { color: #fff; }
.scn-sheet.on-cam .scn-head .scn-title .scn-sub.pickup { color: #fca5a5; }
.scn-sheet.on-cam .scn-head .scn-title .scn-sub.return { color: #93c5fd; }
.scn-sheet.on-surface .scn-head { border-bottom: 1px solid var(--scn-border); }
.scn-head .scn-title { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
.scn-head .scn-title b { font-size: 15.5px; font-weight: 750; letter-spacing: -.01em; }
.scn-sheet .scn-head .scn-title { align-items: center; text-align: center; }
.scn-head .scn-title .scn-sub { font-size: 11.5px; font-weight: 650; letter-spacing: .04em; text-transform: uppercase; display: inline-flex; align-items: center; gap: 6px; }
.scn-head .scn-title .scn-sub.pickup { color: var(--scn-accent-2); }
.scn-head .scn-title .scn-sub.return { color: #60a5fa; }
.scn-iconbtn {
    width: 36px; height: 36px; flex: none; border-radius: 10px;
    border: 1px solid var(--scn-border); background: var(--scn-surface-2);
    color: var(--scn-text-2); display: grid; place-items: center; cursor: pointer;
    transition: background .14s, color .14s, border-color .14s;
}
.scn-iconbtn:hover { color: var(--scn-text); }
.scn-iconbtn svg { width: 18px; height: 18px; }
.scn-sheet.on-cam .scn-iconbtn { background: rgba(255,255,255,0.12); border-color: transparent; color: #fff; border-radius: 999px; }

/* ---------- Camera stage ---------- */
.scn-cam { position: relative; overflow: hidden; background: var(--scn-camera); }
.scn-modal .scn-cam { aspect-ratio: 4 / 3; }
.scn-sheet .scn-cam { flex: 1; }
.scn-video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }

.scn-finder { position: absolute; inset: 0; z-index: 3; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.scn-frame { position: relative; width: min(58%, 220px); aspect-ratio: 1; border-radius: 18px; box-shadow: 0 0 0 9999px rgba(0,0,0,0.46); transition: box-shadow .3s; }
.scn-sheet .scn-frame { width: min(66%, 280px); }
.scn-frame .corner { position: absolute; width: 28px; height: 28px; border: 3px solid #fff; transition: border-color .25s; }
.scn-frame .tl { top: -1px; left: -1px; border-right: 0; border-bottom: 0; border-top-left-radius: 16px; }
.scn-frame .tr { top: -1px; right: -1px; border-left: 0; border-bottom: 0; border-top-right-radius: 16px; }
.scn-frame .bl { bottom: -1px; left: -1px; border-right: 0; border-top: 0; border-bottom-left-radius: 16px; }
.scn-frame .br { bottom: -1px; right: -1px; border-left: 0; border-top: 0; border-bottom-right-radius: 16px; }
.scn-line {
    position: absolute; left: 10px; right: 10px; height: 2px; border-radius: 2px;
    background: linear-gradient(90deg, transparent, var(--scn-accent), transparent);
    box-shadow: 0 0 14px rgba(239,68,68,.85), 0 0 30px rgba(239,68,68,.5);
    top: 10px; animation: scn-sweep 1.9s cubic-bezier(.4,0,.4,1) infinite;
}
@keyframes scn-sweep {
    0% { top: 10px; opacity: 0; } 12% { opacity: 1; } 50% { top: calc(100% - 12px); opacity: 1; } 88% { opacity: 1; } 100% { top: 10px; opacity: 0; }
}
.scn-frame.ok { box-shadow: 0 0 0 9999px rgba(0,0,0,0.52); animation: scn-detect .55s ease-out; }
.scn-frame.ok .corner { border-color: var(--scn-success); }
.scn-frame.ok .scn-line { display: none; }
@keyframes scn-detect {
    0% { box-shadow: 0 0 0 9999px rgba(0,0,0,0.46), 0 0 0 0 rgba(34,197,94,.55); }
    100% { box-shadow: 0 0 0 9999px rgba(0,0,0,0.52), 0 0 0 22px rgba(34,197,94,0); }
}
.scn-checkbadge {
    position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%) scale(.4);
    width: 56px; height: 56px; border-radius: 50%; background: var(--scn-success); color: #fff;
    display: grid; place-items: center; animation: scn-badge .4s cubic-bezier(.2,1.4,.4,1) forwards;
}
.scn-checkbadge svg { width: 30px; height: 30px; }
@keyframes scn-badge { to { transform: translate(-50%,-50%) scale(1); } }

.scn-hint {
    position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%); z-index: 4;
    display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 999px; white-space: nowrap;
    background: rgba(0,0,0,0.72); font-size: 12.5px; font-weight: 600; color: rgba(255,255,255,.92);
}
.scn-hint.err { background: rgba(220,38,38,0.85); }
.scn-hint .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--scn-accent); animation: scn-blink 1.1s ease infinite; }
@keyframes scn-blink { 50% { opacity: .3; } }
.scn-hint svg { width: 15px; height: 15px; }

.scn-live-chips { position: absolute; top: 12px; right: 12px; z-index: 5; display: flex; gap: 8px; }
.scn-chip {
    display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; border: 0; cursor: pointer;
    background: rgba(0,0,0,0.68); color: rgba(255,255,255,.9);
    font-family: var(--scn-font); font-size: 11.5px; font-weight: 700; letter-spacing: .03em;
}
.scn-chip svg { width: 14px; height: 14px; }
.scn-chip.on { background: rgba(255,255,255,.92); color: #111; }
.scn-chip .live-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--scn-accent); box-shadow: 0 0 8px var(--scn-accent); animation: scn-blink 1.1s ease infinite; }

/* Zoom control — hardware zoom where supported, else software ROI crop. */
.scn-zoom { display: inline-flex; align-items: center; gap: 2px; padding: 2px; border-radius: 999px; background: rgba(0,0,0,0.68); }
.scn-zoom-btn {
    width: 26px; height: 26px; flex: none; border: 0; border-radius: 999px; cursor: pointer;
    background: transparent; color: rgba(255,255,255,.92);
    font-family: var(--scn-font); font-size: 17px; font-weight: 700; line-height: 1;
    display: grid; place-items: center; padding: 0; user-select: none;
}
.scn-zoom-btn:active { background: rgba(255,255,255,.18); }
.scn-zoom-val { min-width: 30px; text-align: center; color: rgba(255,255,255,.9); font-family: var(--scn-font); font-size: 11.5px; font-weight: 700; letter-spacing: .02em; }
.scn-mode-pill {
    position: absolute; top: 12px; left: 12px; z-index: 5; display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 11px; border-radius: 999px; background: rgba(0,0,0,0.68);
    font-size: 11px; font-weight: 750; letter-spacing: .05em; text-transform: uppercase; color: #fff;
}
.scn-mode-pill .swatch { width: 7px; height: 7px; border-radius: 2px; }
.scn-mode-pill.pickup .swatch { background: var(--scn-accent); }
.scn-mode-pill.return .swatch { background: #60a5fa; }

.scn-flash { position: absolute; inset: 0; z-index: 8; background: #fff; pointer-events: none; animation: scn-flashout .42s ease-out forwards; }
@keyframes scn-flashout { from { opacity: .85; } to { opacity: 0; } }

/* ---------- Foot bar ---------- */
.scn-foot { position: relative; z-index: 6; display: flex; align-items: center; gap: 10px; padding: 13px 16px; flex-wrap: wrap; }
.scn-modal .scn-foot { border-top: 1px solid var(--scn-border); background: var(--scn-surface); }
.scn-sheet .scn-foot { padding-bottom: 30px; background: linear-gradient(to top, rgba(0,0,0,0.7), rgba(0,0,0,0)); }
.scn-foot .scn-foot-status { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.scn-foot .scn-foot-status .fs-k { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--scn-muted); }
.scn-foot .scn-foot-status .fs-v { font-size: 13px; font-weight: 650; color: var(--scn-text-2); display: inline-flex; align-items: center; gap: 7px; }
.scn-foot .scn-foot-status .fs-v b { color: var(--scn-success); }
.scn-foot .scn-foot-status .fs-v svg { width: 15px; height: 15px; color: var(--scn-success); }
.scn-sheet .scn-foot .fs-k { color: rgba(255,255,255,.6); }
.scn-sheet .scn-foot .fs-v { color: rgba(255,255,255,.92); }
.scn-sheet .scn-foot .fs-v b, .scn-sheet .scn-foot .fs-v svg { color: #4ade80; }

.scn-incl { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 650; color: var(--scn-text-2); cursor: pointer; white-space: nowrap; }
.scn-incl input { accent-color: var(--scn-accent); width: 15px; height: 15px; }
.scn-sheet .scn-incl { color: rgba(255,255,255,.9); }

.scn-textbtn {
    display: inline-flex; align-items: center; gap: 7px; padding: 9px 13px; border-radius: 10px; cursor: pointer; white-space: nowrap;
    border: 1px solid var(--scn-border); background: var(--scn-surface-2); color: var(--scn-text);
    font-family: var(--scn-font); font-size: 12.5px; font-weight: 700; transition: background .14s, border-color .14s;
}
.scn-textbtn svg { width: 15px; height: 15px; }
.scn-sheet .scn-textbtn { background: rgba(255,255,255,.12); border-color: transparent; color: #fff; }

/* ---------- Permission / status screens ---------- */
.scn-perm { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 7px; padding: 38px 30px; }
.scn-modal .scn-perm { min-height: 380px; }
.scn-perm-ic { width: 76px; height: 76px; border-radius: 22px; margin-bottom: 8px; display: grid; place-items: center; position: relative; background: var(--scn-surface-2); border: 1px solid var(--scn-border); }
.scn-perm-ic svg { width: 36px; height: 36px; }
.scn-perm-ic.accent { background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.3); color: var(--scn-accent-2); }
.scn-perm-ic.warn   { background: rgba(245,158,11,.12); border-color: rgba(245,158,11,.32); color: var(--scn-warning); }
.scn-perm-ic.danger { background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.3); color: var(--scn-accent-2); }
.scn-perm-ic.muted  { background: var(--scn-surface-2); color: var(--scn-muted); }
.scn-perm-ic .ping { position: absolute; inset: -1px; border-radius: 22px; border: 2px solid var(--scn-accent); animation: scn-ping 1.6s cubic-bezier(0,0,.2,1) infinite; opacity: 0; }
@keyframes scn-ping { 0% { transform: scale(1); opacity: .7; } 100% { transform: scale(1.35); opacity: 0; } }
.scn-perm h3 { margin: 0; font-size: 19px; font-weight: 800; letter-spacing: -.02em; color: var(--scn-text); }
.scn-perm p { margin: 0; font-size: 13.5px; line-height: 1.55; color: var(--scn-text-2); max-width: 300px; }
.scn-perm .scn-actions { margin-top: 16px; width: 100%; max-width: 290px; display: flex; flex-direction: column; gap: 9px; }

.scn-steps { margin-top: 14px; width: 100%; max-width: 300px; border: 1px solid var(--scn-border); border-radius: 12px; background: var(--scn-surface-2); overflow: hidden; text-align: left; }
.scn-steps .st { display: flex; align-items: center; gap: 11px; padding: 11px 13px; border-bottom: 1px solid var(--scn-border); font-size: 12.5px; color: var(--scn-text-2); }
.scn-steps .st:last-child { border-bottom: 0; }
.scn-steps .st .n { width: 20px; height: 20px; flex: none; border-radius: 50%; background: var(--scn-surface); border: 1px solid var(--scn-border); display: grid; place-items: center; font-size: 11px; font-weight: 800; color: var(--scn-text); }
.scn-steps .st b { color: var(--scn-text); font-weight: 700; }
.scn-steps .st svg { width: 15px; height: 15px; color: var(--scn-muted); flex: none; margin-left: auto; }

.scn-btn {
    width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 9px; padding: 12px 16px; border-radius: 12px; cursor: pointer;
    font-family: var(--scn-font); font-size: 14px; font-weight: 750; letter-spacing: -.01em; border: 1px solid transparent; transition: filter .14s, background .14s, border-color .14s;
}
.scn-btn svg { width: 18px; height: 18px; }
.scn-btn.primary { background: var(--scn-accent); color: #fff; box-shadow: 0 6px 18px rgba(239,68,68,.32); }
.scn-btn.primary:hover { filter: brightness(1.06); }
.scn-btn.ghost { background: var(--scn-surface-2); border-color: var(--scn-border); color: var(--scn-text); }
.scn-btn.link { background: transparent; color: var(--scn-text-2); padding: 6px; font-weight: 650; font-size: 13px; }
.scn-btn.link:hover { color: var(--scn-text); }

.scn-priv { display: inline-flex; align-items: center; gap: 7px; font-size: 11.5px; color: var(--scn-muted); margin-top: 4px; }
.scn-priv svg { width: 13px; height: 13px; }

.scn-spin { animation: scn-rot 1s linear infinite; }
@keyframes scn-rot { to { transform: rotate(360deg); } }

/* ---------- Manual entry ---------- */
.scn-manual { flex: 1; display: flex; flex-direction: column; gap: 14px; padding: 22px 20px; overflow: auto; }
.scn-modal .scn-manual { min-height: 360px; max-height: 70vh; }
.scn-manual .ttl { display: flex; align-items: center; gap: 9px; font-size: 14px; font-weight: 750; color: var(--scn-text); }
.scn-manual .ttl svg { width: 18px; height: 18px; color: var(--scn-muted); }
.scn-field { display: flex; gap: 8px; }
.scn-input {
    flex: 1; min-width: 0; padding: 12px 14px; border-radius: 11px; background: var(--scn-field); border: 1px solid var(--scn-border); color: var(--scn-text);
    font-family: var(--scn-mono); font-size: 14px; letter-spacing: .04em; outline: none; transition: border-color .14s, box-shadow .14s;
}
.scn-input::placeholder { color: var(--scn-muted); font-family: var(--scn-font); letter-spacing: 0; }
.scn-input:focus { border-color: var(--scn-accent); box-shadow: 0 0 0 3px rgba(239,68,68,.18); }
.scn-go { flex: none; padding: 0 16px; border-radius: 11px; border: 0; background: var(--scn-accent); color: #fff; font-weight: 750; font-size: 13px; cursor: pointer; }
.scn-go:disabled { opacity: .4; cursor: not-allowed; }
.scn-manual .lbl { font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--scn-muted); }
.scn-suggest { display: flex; flex-direction: column; gap: 6px; overflow: auto; }
.scn-suggest .sg { display: flex; align-items: center; gap: 11px; padding: 10px 12px; cursor: pointer; border-radius: 11px; border: 1px solid var(--scn-border); background: var(--scn-surface-2); text-align: left; color: var(--scn-text); transition: border-color .14s, background .14s; }
.scn-suggest .sg:hover { border-color: var(--scn-accent); }
.scn-suggest .sg.done { opacity: .45; pointer-events: none; }
.scn-suggest .sg .sg-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.scn-suggest .sg .sg-name { font-size: 13px; font-weight: 650; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.scn-suggest .sg .sg-sn { font-family: var(--scn-mono); font-size: 11px; color: var(--scn-muted); }
.scn-suggest .sg .sg-state { width: 22px; height: 22px; flex: none; border-radius: 50%; display: grid; place-items: center; }
.scn-suggest .sg .sg-state.on { background: var(--scn-success); color: #fff; }
.scn-suggest .sg .sg-state.off { border: 1.5px solid var(--scn-border); }
.scn-suggest .sg .sg-state svg { width: 12px; height: 12px; }
.scn-manual-err { font-size: 12px; color: var(--scn-accent-2); display: flex; align-items: center; gap: 6px; min-height: 16px; }
.scn-manual-err svg { width: 14px; height: 14px; }

/* ---------- Mobile list page (pre-camera) ---------- */
.scn-mobile { position: fixed; inset: 0; z-index: 9998; display: flex; flex-direction: column; background: var(--scn-bg); color: var(--scn-text); animation: scn-slide .26s cubic-bezier(.2,.8,.2,1); }
.scnm-head { display: flex; align-items: center; gap: 12px; padding: 18px 16px 12px; border-bottom: 1px solid var(--scn-border); }
.scnm-back { width: 36px; height: 36px; flex: none; border-radius: 10px; border: 1px solid var(--scn-border); background: var(--scn-surface-2); color: var(--scn-text-2); display: grid; place-items: center; cursor: pointer; }
.scnm-back svg { width: 18px; height: 18px; }
.scnm-head-tx { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.scnm-head-tx b { font-size: 15.5px; font-weight: 750; }
.scnm-code { font-family: var(--scn-mono); font-size: 11.5px; color: var(--scn-muted); }
.scnm-prog { padding: 14px 16px; display: flex; flex-direction: column; gap: 8px; }
.scnm-prog-num { font-size: 13px; font-weight: 700; }
.scnm-prog-num b { color: var(--scn-success); }
.scnm-track { height: 8px; border-radius: 999px; background: var(--scn-surface-2); overflow: hidden; }
.scnm-fill { height: 100%; border-radius: 999px; background: var(--scn-success); transition: width .35s cubic-bezier(.4,0,.2,1); }
.scnm-list { flex: 1; overflow: auto; padding: 4px 12px 12px; display: flex; flex-direction: column; gap: 7px; }
.scnm-row { display: flex; align-items: center; gap: 12px; padding: 11px 12px; border-radius: 12px; border: 1px solid var(--scn-border); background: var(--scn-surface); }
.scnm-row.checked { background: rgba(34,197,94,.10); border-color: rgba(34,197,94,.30); }
.scnm-thumb { width: 38px; height: 38px; flex: none; border-radius: 9px; background: var(--scn-surface-2); border: 1px solid var(--scn-border); display: grid; place-items: center; color: var(--scn-muted); }
.scnm-thumb svg { width: 18px; height: 18px; }
.scnm-thumb.on { background: rgba(34,197,94,.14); color: var(--scn-success); border-color: rgba(34,197,94,.30); }
.scnm-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.scnm-name { font-size: 13.5px; font-weight: 650; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.scnm-sn { font-family: var(--scn-mono); font-size: 11px; color: var(--scn-muted); }
.check-ic-sm { width: 24px; height: 24px; flex: none; border-radius: 50%; display: grid; place-items: center; }
.check-ic-sm.on { background: var(--scn-success); color: #fff; }
.check-ic-sm.off { border: 1.5px solid var(--scn-border); }
.check-ic-sm svg { width: 13px; height: 13px; }
.scnm-bottom { padding: 12px 16px calc(20px + env(safe-area-inset-bottom,0px)); border-top: 1px solid var(--scn-border); background: var(--scn-surface); }
.scnm-scan { width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 9px; padding: 14px; border-radius: 13px; border: 0; background: var(--scn-accent); color: #fff; font-family: var(--scn-font); font-size: 15px; font-weight: 750; cursor: pointer; box-shadow: 0 6px 18px rgba(239,68,68,.32); }
.scnm-scan svg { width: 19px; height: 19px; }
</style>
@endonce
