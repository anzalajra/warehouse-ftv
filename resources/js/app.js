import './bootstrap';

import Alpine from 'alpinejs';
import { mufiseConnection } from './mufise-connection';
import { mufiseEditor, clearMufiseDrafts } from './mufise-editor';
import { mufiseCheckout } from './mufise-checkout';

window.Alpine = Alpine;
window.mufiseConnection = mufiseConnection;
window.mufiseCheckout = mufiseCheckout;
window.mufiseEditor = mufiseEditor;

// Shared-device logout must discard private project drafts for every grant.
document.addEventListener('submit', event => {
    if (event.target instanceof HTMLFormElement && new URL(event.target.action, window.location.origin).pathname.endsWith('/logout')) clearMufiseDrafts(sessionStorage);
});
document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (link && new URL(link.href, window.location.origin).pathname.endsWith('/logout')) clearMufiseDrafts(sessionStorage);
});

Alpine.start();
