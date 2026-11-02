import assert from 'node:assert/strict';
import { test } from 'node:test';
import { mufiseConnection } from '../../resources/js/mufise-connection.js';
import { mufiseCheckout } from '../../resources/js/mufise-checkout.js';

const approval = { verification_url: 'https://mufise.test/connect/warehouse?request=org-bound-request', expires_at: new Date(Date.now() + 600000).toISOString() };
const config = { connectUrl: '/connect', pollUrl: '/poll', disconnectUrl: '/disconnect' };

function setup(t, open = () => null) {
    const previousWindow = globalThis.window;
    globalThis.window = { open, location: { reload() {} } };
    t.after(() => { globalThis.window = previousWindow; });
    const timers = [];
    t.mock.method(globalThis, 'setTimeout', (callback, delay) => { timers.push({ callback, delay }); return timers.length; });
    t.mock.method(globalThis, 'clearTimeout', () => {});
    return timers;
}

for (const blocked of [false, true]) {
    test(`connect opens the tab before starting the request, blocked=${blocked}`, async t => {
        const events = [];
        const tab = { opener: 'warehouse', closed: false, location: { replace(url) { events.push(url); } } };
        const timers = setup(t, () => { events.push('open'); return blocked ? null : tab; });
        const state = mufiseConnection(config);
        state.send = async url => { events.push(url); return approval; };
        await state.connect();
        assert.deepEqual(events, blocked ? ['open', '/connect'] : ['open', '/connect', approval.verification_url]);
        if (!blocked) assert.equal(tab.opener, null);
        assert.equal(state.verificationUrl, approval.verification_url);
        assert.equal(state.message, 'waiting for syncronization');
        assert.equal(state.polling, true);
        assert.equal(state.busy, false);
        assert.equal(timers[0].delay, 5000);
        await state.connect();
        assert.equal(events.filter(event => event === 'open').length, 1);
    });
}

test('a failed connect closes the blank tab and allows retry', async t => {
    let closed = false;
    setup(t, () => ({ closed: false, close() { closed = true; } }));
    const state = mufiseConnection(config);
    state.send = async () => { throw new Error('Mufise tidak dapat dijangkau.'); };
    await state.connect();
    assert.equal(closed, true);
    assert.equal(state.polling, false);
    assert.equal(state.failed, true);
    assert.equal(state.busy, false);
    assert.equal(state.verificationUrl, '');
});

test('resuming a pending connection polls without opening another tab', async t => {
    const timers = setup(t, () => assert.fail('reload must not open a popup'));
    const state = mufiseConnection({ ...config, pending: approval });
    state.resume();
    assert.equal(state.verificationUrl, approval.verification_url);
    assert.equal(state.message, 'waiting for syncronization');
    assert.equal(timers.length, 1);
    state.send = async () => ({ connected: false, pending: true });
    await state.poll();
    assert.equal(timers.length, 2);
});

test('expired pairing clears its fallback and enables reconnect', async t => {
    setup(t);
    const state = mufiseConnection(config);
    state.waitForApproval({ ...approval, expires_at: new Date(0).toISOString() });
    state.send = () => assert.fail('expired pairing must not call the API');
    await state.poll();
    assert.equal(state.polling, false);
    assert.equal(state.failed, true);
    assert.equal(state.verificationUrl, '');
});

test('polling respects retry delay and reloads only after success', async t => {
    const timers = setup(t);
    let reloads = 0;
    window.location.reload = () => reloads++;
    const state = mufiseConnection(config);
    state.waitForApproval(approval);
    state.send = async () => { throw Object.assign(new Error('Tunggu'), { retry: true, wait: 30 }); };
    await state.poll();
    assert.equal(timers.at(-1).delay, 30000);
    assert.equal(reloads, 0);
    state.send = async () => ({ connected: true });
    await state.poll();
    assert.equal(reloads, 1);
    assert.equal(state.polling, false);
});

function checkout() {
    // Match the spread into the rest of the checkout form, including its unrelated fields.
    return { ...mufiseCheckout({ status: null, projects: [], error: null, selected: '', statusUrl: '/status' }), outsidePurpose: 'uts', notes: 'Tetap tersimpan' };
}

function respond(t, data, ok = true) {
    t.mock.method(globalThis, 'fetch', async () => ({ ok, json: async () => data }));
}

test('refresh updates guidance and titles while preserving all other form fields', async t => {
    const state = checkout();
    respond(t, { status: { next_action: 'ready' }, projects: [{ id: 'film-1', title: 'Film Senja' }], error: null });
    await state.refreshMufiseProjects();
    state.mufiseProject = 'film-1';
    assert.equal(state.mufiseProjectTitle(), 'Film Senja');
    assert.match(state.mufiseGuidance(), /Akun terhubung/);
    assert.equal(state.outsidePurpose, 'uts');
    assert.equal(state.notes, 'Tetap tersimpan');
    await state.refreshMufiseProjects();
    assert.equal(state.mufiseProject, 'film-1');
});

test('refresh clears a project that is no longer shared', async t => {
    const state = checkout();
    state.mufiseProject = 'old-project';
    respond(t, { status: { next_action: 'select_project' }, projects: [], error: null });
    await state.refreshMufiseProjects();
    assert.equal(state.mufiseProject, '');
    assert.match(state.mufiseGuidance(), /pengaturan koneksi/);
});

test('refresh failure preserves selection and displays an error', async t => {
    const state = checkout();
    state.mufiseProject = 'film-1';
    respond(t, { error: 'Mufise tidak dapat dijangkau.' });
    await state.refreshMufiseProjects();
    assert.equal(state.mufiseProject, 'film-1');
    assert.equal(state.mufiseError, 'Mufise tidak dapat dijangkau.');
    assert.equal(state.mufiseRefreshing, false);
    assert.equal(state.mufiseNotice, '');
});
