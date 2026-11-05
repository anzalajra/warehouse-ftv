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

test('configuration and account problems choose recovery without a pairing loop', async t => {
    setup(t, () => assert.fail('configuration errors must not start pairing'));
    for (const action of ['check_configuration', 'check_credentials', 'verify_email', 'contact_admin', 'check_token_storage']) {
        const problem = { reason: 'test', action, message: 'Perlu tindakan', request_id: 'mufise-ref', warehouse_request_id: 'warehouse-ref', retryable: false };
        const connection = mufiseConnection({ ...config, problem });
        assert.equal(connection.canConnect(), false);
        await connection.connect();
        const state = mufiseCheckout({ status: null, projects: [], problem });
        assert.match(state.mufiseConnectionLabel(), /Periksa Pengaturan/);
        assert.match(state.mufiseError, /Referensi Mufise: mufise-ref/);
        assert.match(state.mufiseError, /Referensi Warehouse: warehouse-ref/);
    }
    for (const action of ['reconnect_account', 'restart_pairing', 'join_organization']) {
        assert.equal(mufiseConnection({ ...config, problem: { action } }).canConnect(), true);
    }
    assert.equal(mufiseConnection({ ...config, problem: { reason: 'local_token_expired', action: 'check_token_storage' } }).canConnect(), true);
});

test('refresh error preserves draft and uses server recovery then clears it after success', async t => {
    const state = checkout();
    state.mufiseProject = 'film-1';
    let data = { error: 'Verifikasi email', problem: { message: 'Verifikasi email', action: 'verify_email', request_id: 'ref' } };
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => data }));
    await state.refreshMufiseProjects();
    assert.match(state.mufiseError, /Buka Mufise/);
    assert.match(state.mufiseError, /Referensi Mufise: ref/);
    assert.equal(state.mufiseProject, 'film-1');
    assert.equal(state.notes, 'Tetap tersimpan');
    assert.equal(state.outsidePurpose, 'uts');
    assert.match(state.mufiseConnectionLabel(), /Periksa Pengaturan/);
    data = { status: { next_action: 'ready' }, projects: [{ id: 'film-1', title: 'Film Senja' }], error: null, problem: null };
    await state.refreshMufiseProjects();
    assert.equal(state.mufiseProblem, null);
    assert.equal(state.mufiseError, '');
    assert.match(state.mufiseConnectionLabel(), /Kelola Proyek/);
});

test('polling follows explicit retryable metadata and shows references', async t => {
    const timers = setup(t);
    const previousDocument = globalThis.document;
    globalThis.document = { querySelector: () => ({ content: 'csrf' }) };
    t.after(() => { globalThis.document = previousDocument; });
    let problem = { message: 'Konfigurasi bermasalah', action: 'check_configuration', retryable: false, warehouse_request_id: 'local-ref' };
    t.mock.method(globalThis, 'fetch', async () => ({ ok: false, status: 503, headers: { get: () => '30' }, json: async () => problem }));
    const state = mufiseConnection(config);
    state.waitForApproval(approval);
    await state.poll();
    assert.equal(state.polling, false);
    assert.equal(state.canConnect(), false);
    assert.match(state.message, /local-ref/);
    assert.equal(timers.length, 1);
    problem = { message: 'Gangguan sementara', action: 'retry_later', retryable: true, retry_after: 30 };
    state.waitForApproval(approval);
    await state.poll();
    assert.equal(state.polling, true);
    assert.equal(timers.at(-1).delay, 30000);
});

const { mufiseEditor, changedFields } = await import('../../resources/js/mufise-editor.js');
const editorConfig = { apiUrl: '/projects/__PROJECT__/editor-api', mediaUrl: '/projects/__PROJECT__/media', mufiseUrl: 'https://mufise.test/', pollSeconds: 15 };
function editor(t) {
    const previous = { window: globalThis.window, document: globalThis.document, sessionStorage: globalThis.sessionStorage };
    globalThis.window = { location: { origin: 'https://warehouse.test' }, confirm: () => true };
    globalThis.document = { querySelector: () => ({ content: 'csrf' }) };
    const saved = new Map();
    globalThis.sessionStorage = { setItem: (k,v) => saved.set(k,v), getItem: k => saved.get(k) || null, removeItem: k => saved.delete(k) };
    t.after(() => Object.assign(globalThis, previous));
    const state = mufiseEditor(editorConfig);
    state.projectId = 'film-1'; state.$nextTick = cb => cb();
    state.$refs = { editorDialog: { showModal() {}, close() {}, querySelectorAll: () => [], querySelector: () => null } };
    state.capabilities = { contract: 'mufise.external-editor.v1', permissions: { can_edit_crew: true, can_edit_call_sheet: true, can_edit_locations: true }, draft_scope: 'scope-1' };
    return state;
}

test('editor patch preserves omitted fields and replaces only deliberately changed arrays', () => {
    const old = { call_sheet: { title: 'Day 1', notes: 'Old', hod: { director: 'Ayu' }, crew_contact_ids: ['a'] }, items: [{ id: 'stable', title: 'Shot', duration: 20 }] };
    const current = structuredClone(old); current.call_sheet.notes = 'New';
    assert.deepEqual(changedFields(current, old), { call_sheet: { notes: 'New' } });
    current.call_sheet.crew_contact_ids = [];
    assert.deepEqual(changedFields(current, old), { call_sheet: { notes: 'New', crew_contact_ids: [] } });
});

test('unknown save outcome retries the same persisted key and payload after input changes', async t => {
    const state = editor(t); state.mode = 'location-new'; state.form = { name: 'Studio' }; state.baseline = {};
    const requests = [];
    state.request = async (endpoint, method, data, key) => { requests.push({ endpoint, method, data: structuredClone(data), key }); if (requests.length === 1) throw Object.assign(new Error('Timeout'), { uncertain: true }); return {}; };
    state.sync = async () => {};
    const operation = { endpoint: 'locations', method: 'POST', data: { name: 'Studio' } };
    assert.equal(await state.mutate(operation), false);
    assert.ok(state.pending.id);
    state.form.name = 'New studio';
    assert.equal(await state.mutate({ ...operation, data: state.form }), true);
    assert.deepEqual(requests[1], requests[0]);
    assert.equal(state.pending, null);
});

test('polling a changed bundle preserves draft and marks it for review', async t => {
    const state = editor(t); state.bundle = { sync_version: 'old', project: { title: 'Before' } };
    state.form = { full_name: 'My draft' }; state.dirty = true;
    state.request = async endpoint => endpoint === 'sync' ? { changed: true, sync_version: 'new', project: { title: 'Remote' } } : { permissions: { can_edit_crew: false } };
    await state.sync();
    assert.equal(state.form.full_name, 'My draft');
    assert.equal(state.bundle.project.title, 'Before');
    assert.equal(state.remoteChanged, true);
    assert.equal(state.permission('can_edit_crew'), false);
});

test('old directory responses are ignored after query changes', async t => {
    const state = editor(t); state.mode = 'crew-pick';
    let firstResolve;
    state.request = async () => new Promise(resolve => { firstResolve = resolve; });
    const first = state.searchCrew();
    state.request = async () => ({ people: [{ id: 'new' }], has_more: false });
    await state.searchCrew(); firstResolve({ people: [{ id: 'old' }] }); await first;
    assert.deepEqual(state.directory, [{ id: 'new' }]);
});

test('duplicate preview requires an explicit distinct-person decision', t => {
    const state = editor(t); state.mode = 'crew-new';
    state.form = { full_name: 'Nadia', position_ids: ['director'], position_id: 'director' }; state.positions = [{ id: 'director', name: 'Director' }];
    state.preview = { action: 'confirm_distinct' };
    assert.throws(() => state.payload(), /konfirmasikan/);
    state.form.confirm_distinct = true;
    assert.equal(state.payload().data.confirm_distinct, true);
    state.preview = { action: 'select_existing' };
    assert.throws(() => state.payload(), /sudah ada/);
});

test('per-resource permissions and locked days keep unavailable editors read only', t => {
    const state = editor(t); state.mode = 'day-edit'; state.detail = { locked: true };
    assert.equal(state.canSave(), false); state.detail.locked = false;
    assert.equal(state.canSave(), true); state.capabilities.permissions.can_edit_call_sheet = false;
    assert.equal(state.canSave(), false);
    state.mode = 'crew-edit'; state.detail = { permissions: { can_edit: false } };
    assert.equal(state.canSave(), false);
});

test('location actions use editor version while text patches use object version', t => {
    const state = editor(t); state.mode = 'location-point'; state.target = 'location'; state.form = { latitude: -6.9, longitude: 107.6 }; state.detail = { location: { version: 'text' }, editor_version: 'detail' };
    assert.equal(state.payload().data.expected_version, 'detail');
    state.mode = 'location-edit'; state.form = { name: 'New' }; state.baseline = { name: 'Old' };
    assert.equal(state.payload().data.expected_version, 'text');
    assert.equal(state.payload().data.expected_editor_version, 'detail');
    assert.equal(state.mapUrl({ latitude: '', longitude: '' }), '');
    assert.equal(state.mapUrl({ latitude: NaN, longitude: 107 }), '');
});

test('draft scope isolates projects and grant changes, and access loss clears sensitive state', t => {
    const state = editor(t); state.mode = 'crew-edit'; state.form = { full_name: 'Draft' }; state.dirty = true; state.storeDraft();
    assert.equal(state.readDraft().form.full_name, 'Draft');
    state.capabilities.draft_scope = 'different-grant'; assert.equal(state.readDraft(), null);
    state.capabilities.draft_scope = 'scope-1'; state.bundle = { crew: ['private'] }; state.clearPrivateData();
    assert.equal(state.bundle, null); assert.deepEqual(state.form, {}); assert.equal(sessionStorage.getItem('mufise-draft:scope-1'), null);
});

test('conflict review keeps changed draft fields and preserves unrelated remote corrections', async t => {
    const state = editor(t); state.mode = 'location-edit'; state.target = 'location';
    state.baseline = { name: 'Studio', address: 'Old road' }; state.form = { name: 'My title', address: 'Old road' };
    state.latest = { location: { name: 'Remote title', address: 'New road', version: 'v2' }, editor_version: 'editor2' };
    state.remoteChanged = true; state.acceptLatest();
    assert.deepEqual(state.form, { name: 'My title', address: 'New road' });
    assert.equal(state.detail.editor_version, 'editor2'); assert.equal(state.remoteChanged, false);
});

test('logout removes private drafts and leaves unrelated browser preferences', async () => {
    const { clearMufiseDrafts } = await import('../../resources/js/mufise-editor.js');
    const items = new Map([['mufise-draft:grant1', 'private'], ['mufise-draft:grant2', 'private'], ['theme', 'dark']]);
    clearMufiseDrafts({ get length() { return items.size; }, key: index => [...items.keys()][index], removeItem: key => items.delete(key) });
    assert.deepEqual([...items], [['theme','dark']]);
});
