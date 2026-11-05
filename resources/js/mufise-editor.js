import { mufiseProblemText } from './mufise-problem.js';

export const copy = value => JSON.parse(JSON.stringify(value));
export function changedFields(current, original = {}) {
    const result = {};
    for (const [key, value] of Object.entries(current)) {
        if (JSON.stringify(value) === JSON.stringify(original?.[key])) continue;
        result[key] = value && typeof value === 'object' && !Array.isArray(value)
            ? changedFields(value, original?.[key] || {}) : value;
    }
    return result;
}
const labels = { crew_required: 'Tambahkan kru bernama', producer_or_director_required: 'Tambahkan produser atau sutradara', required_crew_roles_missing: 'Lengkapi jabatan wajib', location_required: 'Lengkapi lokasi dan alamat', project_inactive: 'Aktifkan proyek', day_1_call_sheet_required: 'Isi kegiatan Day 1', day_1_locked_call_sheet_required: 'Kunci call sheet Day 1', plan_access_required: 'Izinkan akses Plan' };
export const missingText = code => labels[code] || 'Lengkapi persyaratan proyek di Mufise';

export function clearMufiseDrafts(storage) {
    try {
        for (let index = storage.length - 1; index >= 0; index--) {
            const key = storage.key(index);
            if (key?.startsWith('mufise-draft:')) storage.removeItem(key);
        }
    } catch { /* Browser storage can be unavailable. */ }
}

export function mufiseEditor(config) {
    return {
        config, projectId: config.projectId || '', bundle: config.bundle || null, capabilities: null,
        loading: false, syncing: false, busy: false, error: '', notice: '', fields: {}, activeTab: 'crew',
        sheet: null, directory: [], query: '', page: 1, hasMore: false, searching: false, searchSequence: 0,
        positions: [], person: null, preview: null, mode: '', form: {}, baseline: {}, detail: null,
        target: '', dirty: false, remoteChanged: false, latest: null, comparison: [], pending: null,
        photo: null, pointKind: 'shoot', readinessStep: 'check', availability: [], timer: null, generation: 0,
        async init() {
            this.visibilityHandler = () => { if (!document.hidden && this.projectId) this.sync(); };
            document.addEventListener('visibilitychange', this.visibilityHandler);
            if (this.projectId) await this.setProject(this.projectId, true);
        },
        destroy() { clearTimeout(this.timer); document.removeEventListener('visibilitychange', this.visibilityHandler); this.generation++; },
        apiUrl() { return config.apiUrl.replace('__PROJECT__', encodeURIComponent(this.projectId)); },
        media(endpoint, format = '') {
            return config.mediaUrl.replace('__PROJECT__', encodeURIComponent(this.projectId)) + '?' + new URLSearchParams({ endpoint, ...(format ? { format } : {}) });
        },
        directUrl() { return config.mufiseUrl.replace('__PROJECT__', encodeURIComponent(this.projectId)); },
        permission(name) { return config.writable !== false && !!this.capabilities?.permissions?.[name]; },
        async request(endpoint, method = 'GET', data = {}, operationId = null, photo = null) {
            const generation = this.generation;
            const url = new URL(this.apiUrl(), window.location.origin);
            const options = { method: method === 'GET' ? 'GET' : 'POST', cache: 'no-store', headers: { Accept: 'application/json' } };
            if (method === 'GET') {
                url.search = new URLSearchParams({ endpoint, ...data });
            } else {
                options.headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
                if (photo) {
                    const body = new FormData();
                    body.set('endpoint', endpoint); body.set('method', method); body.set('operation_id', operationId);
                    // Laravel decodes an envelope field, while multipart file bytes stay separate.
                    body.set('payload_json', JSON.stringify(data)); body.set('photo', photo);
                    options.body = body;
                } else {
                    options.headers['Content-Type'] = 'application/json';
                    options.body = JSON.stringify({ endpoint, method, data, operation_id: operationId });
                }
            }
            let response;
            try { response = await fetch(url.toString(), options); }
            catch { throw Object.assign(new Error('Koneksi terputus. Input tersimpan. Ulangi penyimpanan yang sama untuk memastikan hasilnya.'), { uncertain: method !== 'GET', retry_after: 30 }); }
            const result = await response.json().catch(() => null);
            if (response.redirected || !result) throw Object.assign(new Error('Sesi atau respons tidak sesuai. Periksa koneksi sebelum mencoba kembali.'), { uncertain: method !== 'GET' });
            if (!response.ok) {
                const problem = result.message ? result : { message: response.status === 419 ? 'Sesi berakhir. Salin draf sebelum masuk kembali.' : 'Data belum dapat diproses.', fields: Object.fromEntries(Object.keys(result.errors || {}).map(key => [key, 'Periksa nilai isian ini.'])) };
                const denied = [403, 404].includes(response.status) && !['read_only', 'restricted_area', 'write_scope_denied', 'editor_permission_required'].includes(problem.code);
                if (denied && generation === this.generation) this.clearPrivateData();
                throw Object.assign(new Error(mufiseProblemText(problem)), problem, { status: response.status, uncertain: response.status >= 500 });
            }
            return result;
        },
        clearPrivateData() {
            this.removeDraft(); this.bundle = this.sheet = this.capabilities = this.detail = null;
            this.directory = []; this.form = {}; this.baseline = {}; this.pending = null;
            this.dirty = false; this.closeDialog(true); clearTimeout(this.timer);
        },
        async setProject(id, force = false) {
            if (!force && id === this.projectId) return;
            if (this.dirty || this.pending) this.storeDraft();
            const generation = ++this.generation;
            clearTimeout(this.timer); this.closeDialog(true);
            this.projectId = id; this.nextSyncAt = 0; this.bundle = null; this.capabilities = null; this.sheet = null;
            this.directory = []; this.error = ''; this.remoteChanged = false;
            if (!id) return;
            this.loading = true;
            try {
                const capabilities = await this.request('editor');
                if (generation !== this.generation) return;
                if (capabilities.contract !== 'mufise.external-editor.v1') throw new Error('Editor belum didukung server Mufise. Buka proyek di Mufise atau minta admin memperbarui server.');
                this.capabilities = capabilities;
                await this.sync(true);
                if (generation !== this.generation) return;
                const restored = this.readDraft();
                if (restored) {
                    this.mode = restored.mode; this.target = restored.target; this.form = restored.form;
                    this.baseline = restored.baseline; this.detail = restored.detail; this.pending = restored.pending;
                    this.dirty = true; this.remoteChanged = true;
                    this.notice = 'Draf sebelumnya tersedia. Tinjau data terbaru sebelum menyimpan.';
                    this.$nextTick(() => this.$refs.editorDialog.showModal());
                }
            } catch (e) { if (generation === this.generation) this.error = e.message; }
            finally { if (generation === this.generation) { this.loading = false; this.scheduleSync(); } }
        },
        scheduleSync(wait = config.pollSeconds || 15) {
            clearTimeout(this.timer);
            this.timer = setTimeout(async () => {
                if (!document.hidden && this.projectId && !this.busy) await this.sync();
                this.scheduleSync(Math.max(5, this.retryWait || wait));
            }, Math.max(5, wait) * 1000);
        },
        async sync(force = false) {
            if (this.syncing || !this.projectId || Date.now() < (this.nextSyncAt || 0)) return;
            const generation = this.generation;
            this.syncing = true;
            try {
                let result = await this.request('sync', 'GET', !force && this.bundle?.sync_version ? { since: this.bundle.sync_version } : {});
                if (generation !== this.generation) return;
                this.retryWait = 0;
                const localRulesChanged = result.warehouse_requirements && JSON.stringify(result.warehouse_requirements) !== JSON.stringify(this.bundle?.warehouse_requirements);
                if (this.bundle && localRulesChanged && !result.changed) result = await this.request('sync');
                if (result.changed && (result.sync_version !== this.bundle?.sync_version || localRulesChanged)) {
                    if (this.dirty || this.pending) {
                        this.remoteChanged = true;
                        // Apply current permissions immediately; preserve the form and its versions.
                        this.capabilities = await this.request('editor');
                    } else {
                        this.bundle = result;
                        this.capabilities = await this.request('editor');
                        if (this.sheet) this.sheet = await this.request('call-sheet-editor');
                    }
                }
            } catch (e) { if (generation === this.generation) { this.error = e.message; this.retryWait = e.retry_after || 30; this.nextSyncAt = Date.now() + this.retryWait * 1000; } }
            finally { this.syncing = false; }
        },
        stageMissing(stage) {
            const r = this.bundle?.readiness;
            return [...(r?.missing_by_stage?.[stage] || r?.missing || []).map(missingText), ...(this.bundle?.warehouse_missing || [])];
        },
        ready(stage) { return !!this.bundle?.readiness?.[stage === 'submit' ? 'can_submit' : 'can_collect'] && !(this.bundle?.warehouse_missing || []).length; },
        requirement(stage) {
            const rule = this.bundle?.requirements?.[stage];
            if (!rule) return 'Mengikuti kebijakan koneksi Mufise';
            const crew = { none: 'Kru opsional', any: 'Minimal satu kru', producer_or_director: 'Produser atau sutradara', roles: 'Jabatan: ' + (rule.crew_roles || []).join(', ') }[rule.crew];
            const sheet = { none: 'call sheet opsional', exists: 'Day 1 berisi kegiatan', locked: 'Day 1 terkunci' }[rule.call_sheet];
            return [crew, rule.location ? 'lokasi dan alamat wajib' : 'lokasi opsional', sheet].join(' · ');
        },
        async showTab(tab) {
            this.activeTab = tab;
            if (tab === 'sheets') {
                try { this.sheet = await this.request('call-sheet-editor'); } catch (e) { this.error = e.message; }
            }
        },
        touch() { this.dirty = true; this.preview = null; this.storeDraft(); },
        draftKey() { return this.capabilities?.draft_scope ? 'mufise-draft:' + this.capabilities.draft_scope : null; },
        storeDraft() {
            const key = this.draftKey(); if (!key || !this.mode) return;
            try { sessionStorage.setItem(key, JSON.stringify({ mode: this.mode, target: this.target, form: this.form, baseline: this.baseline, detail: this.detail, pending: this.pending, savedAt: Date.now() })); } catch { /* Memory remains the fallback. */ }
        },
        readDraft() {
            try { const d = JSON.parse(sessionStorage.getItem(this.draftKey())); return d && Date.now() - d.savedAt < 86400000 ? d : null; } catch { return null; }
        },
        removeDraft() { try { if (this.draftKey()) sessionStorage.removeItem(this.draftKey()); } catch { /* Optional storage. */ } },
        closeDialog(force = false) {
            if (!force && (this.busy || this.pending)) { this.error = 'Selesaikan atau ulangi operasi yang belum pasti sebelum menutup editor.'; return; }
            if (!force && this.dirty && !window.confirm('Tutup editor dan buang perubahan yang belum disimpan?')) return;
            this.$refs?.editorDialog?.close();
            if (!force) this.removeDraft();
            this.dirty = false; this.mode = ''; this.photo = null; this.pending = null;
        },
        async open(mode, entry = null) {
            if (this.busy) return;
            this.error = ''; this.fields = {}; this.mode = mode; this.target = entry?.id || entry?.day_id || '';
            this.preview = null; this.person = null; this.pending = null; this.photo = null; this.latest = null;
            this.dirty = false; this.remoteChanged = false; this.detail = null; this.availability = [];
            this.form = {}; this.baseline = {};
            this.$refs.editorDialog.showModal(); this.loading = true;
            try {
                if (mode === 'crew-new' || mode === 'crew-pick' || mode === 'crew-edit') {
                    this.positions = (await this.request('crew-positions')).positions || [];
                }
                if (mode === 'crew-pick') { this.query = ''; this.page = 1; await this.searchCrew(); }
                if (mode === 'crew-new') this.form = { full_name: '', stage_name: '', phone: '', email: '', address: '', filmography: '', position_ids: [], position_id: '' };
                if (mode === 'crew-edit') {
                    this.detail = await this.request('crew/' + this.target);
                    const p = this.detail.profile;
                    this.form = { full_name: p.full_name || '', stage_name: p.stage_name || '', address: p.address || '', filmography: p.filmography || '', position_id: this.detail.crew.position_id || '', reason: '' };
                    if (this.detail.permissions.can_edit_identity) Object.assign(this.form, { phone: p.phone || '', email: p.email || '' });
                }
                if (mode === 'location-new') this.form = { name: '', address: '', parking: '', hospital: '', points: [] };
                if (mode === 'location-edit') {
                    this.detail = await this.request('locations/' + this.target);
                    this.form = Object.fromEntries(['name', 'address', 'parking', 'hospital'].map(key => [key, this.detail.location[key] || '']));
                }
                if (mode === 'day-new' || mode === 'day-edit') {
                    this.sheet = await this.request('call-sheet-editor');
                    if (mode === 'day-edit') {
                        this.detail = await this.request('call-sheets/' + this.target);
                        const d = this.detail.editable || this.sheet.days.find(day => day.id === this.target);
                        this.form = Object.fromEntries(['date', 'start', 'location', 'call_sheet', 'items'].filter(key => key in d).map(key => [key, copy(d[key])]));
                    } else this.form = { date: '', start: '08:00', location: '', call_sheet: { title: '', notes: '' }, items: [] };
                }
                this.baseline = copy(this.form);
            } catch (e) { this.error = e.message; }
            finally { this.loading = false; this.$nextTick(() => this.$refs.editorDialog.querySelector('input:not([type=hidden]):enabled,select:enabled,textarea:enabled')?.focus()); }
        },
        async searchCrew() {
            const sequence = ++this.searchSequence, generation = this.generation;
            this.searching = true;
            try {
                const result = await this.request('organization-crew', 'GET', { q: this.query, page: this.page, limit: 25 });
                if (sequence !== this.searchSequence || generation !== this.generation || this.mode !== 'crew-pick') return;
                this.directory = result.people || []; this.hasMore = result.has_more;
            } catch (e) { if (sequence === this.searchSequence) this.error = e.message; }
            finally { if (sequence === this.searchSequence) this.searching = false; }
        },
        async selectPerson(person) {
            try {
                const result = await this.request('organization-crew/' + person.id);
                this.person = result.person || result.people?.[0] || result;
                this.mode = 'crew-pick'; this.form = { person_id: this.person.id, position_id: '' }; this.preview = null;
                this.touch();
            } catch (e) { this.error = e.message; }
        },
        assigned(position) { return (this.person?.assignments || []).some(a => a.position_id === position.id || a.role === position.name); },
        async previewCrew() {
            this.busy = true; this.error = '';
            try { this.preview = await this.request('crew/preview', 'POST', { full_name: this.form.full_name, phone: this.form.phone, email: this.form.email }); }
            catch (e) { this.error = e.message; }
            finally { this.busy = false; }
        },
        choosePhoto(event) {
            const file = event.target.files[0];
            if (file && (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 5000000)) { this.error = 'Pilih foto PNG, JPEG atau WebP maksimal 5 MB.'; event.target.value = ''; return; }
            this.photo = file || null; this.touch();
        },
        addPoint(kind) {
            if (this.form.points.some(point => point.kind === kind)) return;
            this.form.points.push({ kind, latitude: null, longitude: null, name: '', address: '' }); this.touch();
        },
        discardSection() {
            if (this.dirty && !window.confirm('Buang perubahan yang belum disimpan sebelum berpindah bagian?')) return false;
            this.dirty = false; this.removeDraft(); return true;
        },
        pointForm(kind) {
            if (!this.discardSection()) return;
            const point = (this.detail?.points || []).find(p => p.kind === kind) || {};
            this.pointKind = kind; this.mode = 'location-point';
            this.form = { latitude: point.latitude ?? null, longitude: point.longitude ?? null, name: point.name || '', address: point.address || '' };
            this.baseline = copy(this.form);
        },
        mapUrl(point) {
            if (point.latitude === null || point.longitude === null || point.latitude === '' || point.longitude === '') return '';
            const lat = Number(point.latitude), lon = Number(point.longitude);
            return Number.isFinite(lat) && Number.isFinite(lon) && Math.abs(lat) <= 85.05112878 && Math.abs(lon) <= 180
                ? 'https://www.openstreetmap.org/?mlat=' + lat + '&mlon=' + lon + '#map=17/' + lat + '/' + lon : '';
        },
        mapEmbed(point) {
            if (!this.mapUrl(point)) return '';
            const lat = Number(point.latitude), lon = Number(point.longitude);
            const box = [Math.max(-180,lon-.005),Math.max(-85.05112878,lat-.004),Math.min(180,lon+.005),Math.min(85.05112878,lat+.004)].join(',');
            return 'https://www.openstreetmap.org/export/embed.html?' + new URLSearchParams({ bbox: box, layer: 'mapnik', marker: lat + ',' + lon });
        },
        async geocode() {
            try { this.geoResults = (await this.request('geocode', 'GET', { q: this.geoQuery })).results || []; }
            catch (e) { this.error = e.message; }
        }, geoQuery: '', geoResults: [],
        readinessForm(step) {
            if (!this.discardSection()) return;
            const row = (this.detail?.readiness || []).find(r => r.step === step) || {};
            this.readinessStep = step; this.mode = 'location-readiness';
            this.form = { status: row.status || 'pending', completed_on: row.completed_on || '', owner_user_id: row.owner_user_id || null, notes: row.notes || '' }; this.baseline = copy(this.form);
        },
        async availabilityForm(kind) {
            if (!this.discardSection()) return;
            try {
                if (kind === 'crew') this.detail = await this.request('crew/' + this.target + '/availability');
                this.availability = this.detail.unavailable || this.detail.availability || [];
                this.mode = kind + '-availability'; this.form = { date: '', end_date: '', reason: '' }; this.baseline = copy(this.form);
            } catch (e) { this.error = e.message; }
        },
        addItem() {
            if (this.form.items.length >= (this.sheet?.options?.day_max_items || 300)) return;
            this.form.items.push({ id: crypto.randomUUID(), kind: 'manual', title: '', duration: 30 }); this.touch();
        },
        moveItem(index, direction) { const next = index + direction; if (next < 0 || next >= this.form.items.length) return; [this.form.items[index], this.form.items[next]] = [this.form.items[next], this.form.items[index]]; this.touch(); },
        dropped(index) { if (this.dragIndex === null || this.dragIndex === index) return; const item = this.form.items.splice(this.dragIndex, 1)[0]; this.form.items.splice(index, 0, item); this.dragIndex = null; this.touch(); }, dragIndex: null,
        sheetField(key) { return (this.sheet?.options?.call_sheet_fields || []).includes(key); },
        setSheetField(key, value) { this.form.call_sheet ||= {}; this.form.call_sheet[key] = value; this.touch(); },
        setWeather(period, value) {
            this.form.call_sheet ||= {};
            this.form.call_sheet.weather_periods ||= { morning: '', midday: '', night: '' };
            this.form.call_sheet.weather_periods[period] = value; this.touch();
        },
        crewSelected(id) { return (this.form.call_sheet?.crew_contact_ids || []).includes(id); },
        toggleCrew(id, checked) {
            const ids = new Set(this.form.call_sheet?.crew_contact_ids || []); checked ? ids.add(id) : ids.delete(id);
            this.setSheetField('crew_contact_ids', [...ids]);
        },
        dayLocked() { return !!this.detail?.locked; },
        canSave() {
            if (this.mode.startsWith('crew-')) return this.permission('can_edit_crew') && (this.mode !== 'crew-edit' || !!this.detail?.permissions?.can_edit);
            if (this.mode.startsWith('location-')) return this.permission('can_edit_locations');
            if (this.mode === 'day-action') return false;
            if (this.mode.startsWith('day-')) return this.permission('can_edit_call_sheet') && !this.dayLocked();
            return false;
        },
        payload() {
            let endpoint, method = this.target ? 'PATCH' : 'POST', data = copy(this.form);
            if (this.mode === 'crew-pick') return { endpoint: 'crew', method: 'POST', data: { person_id: data.person_id, position_id: data.position_id } };
            if (this.mode === 'crew-new') {
                endpoint = 'crew';
                const position = this.positions.find(p => p.id === data.position_id);
                if (!position || !data.position_ids.includes(position.id)) throw new Error('Pilih keahlian dan konfirmasikan jabatan produksi dari keahlian tersebut.');
                delete data.position_id; data.project_role = position.name;
                if (this.preview?.action === 'select_existing') throw new Error('Personel sudah ada. Pilih orang dari hasil pemeriksaan.');
                if (this.preview?.action === 'confirm_distinct' && !data.confirm_distinct) throw new Error('Tinjau orang serupa dan konfirmasikan jika berbeda.');
            } else if (this.mode === 'crew-edit') {
                endpoint = 'crew/' + this.target; data = changedFields(data, this.baseline);
                if (!this.detail.permissions.can_edit_identity) { delete data.email; delete data.phone; }
                data.expected_version = this.detail.crew.version; data.reason = this.form.reason;
            } else if (this.mode === 'location-new') endpoint = 'locations';
            else if (this.mode === 'location-edit') { endpoint = 'locations/' + this.target; data = changedFields(data, this.baseline); data.expected_version = this.detail.location.version; data.expected_editor_version = this.detail.editor_version; }
            else if (this.mode === 'location-point' || this.mode === 'location-readiness') {
                endpoint = 'locations/' + this.target + (this.mode === 'location-point' ? '/points/' + this.pointKind : '/readiness/' + this.readinessStep);
                data.expected_version = this.detail.editor_version;
                if (this.mode === 'location-readiness' && !data.completed_on) data.completed_on = null;
                if (this.mode === 'location-readiness' && !data.owner_user_id) data.owner_user_id = null;
                if (this.mode === 'location-readiness' && data.status !== 'done' && !window.confirm('Mengubah tahap ini dapat mereset tahap berikutnya. Lanjutkan?')) throw new Error('Perubahan dibatalkan.');
            } else if (this.mode.endsWith('-availability')) {
                const kind = this.mode.startsWith('crew') ? 'crew' : 'locations'; endpoint = kind + '/' + this.target + '/availability'; method = 'POST';
                data.expected_version = kind === 'crew' ? this.detail.version : this.detail.editor_version;
                if (!data.end_date) delete data.end_date;
            } else {
                endpoint = 'call-sheets' + (this.target ? '/' + this.target : '');
                data = this.target ? changedFields(data, this.baseline) : data;
                data.expected_revision = this.sheet.schedule_revision;
            }
            return { endpoint, method, data };
        },
        async mutate(operation, photo = null) {
            if (this.busy) return false;
            if (this.pending?.photoRequired && !photo) { this.error = 'Pilih ulang foto yang sama untuk mengulangi operasi sebelumnya.'; return false; }
            this.busy = true; this.$dispatch?.('mufise-saving', { busy: true }); this.error = ''; this.fields = {};
            // An uncertain operation must be retried verbatim, even after further input.
            if (!this.pending) this.pending = { ...copy(operation), id: crypto.randomUUID(), photoRequired: !!photo };
            this.storeDraft();
            try {
                const p = this.pending;
                await this.request(p.endpoint, p.method, p.data, p.id, photo);
                this.pending = null; this.removeDraft(); this.dirty = false; this.remoteChanged = false;
                this.closeDialog(true); this.notice = 'Perubahan tersimpan di Mufise.';
                await this.sync(true);
                if (this.activeTab === 'sheets') this.sheet = await this.request('call-sheet-editor');
                return true;
            } catch (e) {
                this.error = e.message; this.fields = e.fields || {};
                if (!e.uncertain) this.pending = null;
                if (['revision_conflict', 'stale_profile', 'connection_policy_changed'].includes(e.code)) this.remoteChanged = true;
                if (['duplicate_person', 'similar_person'].includes(e.code)) this.preview = null;
                this.storeDraft();
                this.$nextTick(() => this.$refs.editorDialog.querySelector('[aria-invalid="true"]')?.focus());
                return false;
            } finally { this.busy = false; this.$dispatch?.('mufise-saving', { busy: false }); }
        },
        async save() {
            if (this.pending) { await this.mutate(this.pending, this.photo); return; }
            if (!this.canSave() || this.remoteChanged) return;
            if ([...this.$refs.editorDialog.querySelectorAll('input,select,textarea')].some(input => !input.reportValidity())) return;
            if (this.mode === 'crew-new' && !this.preview) { await this.previewCrew(); if (this.preview?.action !== 'create') return; }
            try {
                const operation = this.payload();
                if (this.mode.startsWith('day-') && !this.target) {
                    const same = (this.sheet.days || []).filter(day => day.date === operation.data.date);
                    if (same.length) {
                        if (!window.confirm('Tanggal ' + operation.data.date + ' sudah dipakai ' + same.length + ' hari. Tambahkan hari pada tanggal yang sama?')) return;
                        operation.data.confirm_same_date = true;
                    }
                }
                await this.mutate(operation, this.photo);
            } catch (e) { this.error = e.message; }
        },
        async reviewLatest() {
            if (this.pending) { this.error = 'Ulangi operasi yang hasilnya belum pasti sebelum meninjau versi baru.'; return; }
            this.loading = true;
            try {
                const path = this.mode.startsWith('crew-') ? 'crew/' + this.target + (this.mode.endsWith('availability') ? '/availability' : '') : this.mode.startsWith('location-') ? 'locations/' + this.target : 'call-sheets/' + this.target;
                if (!this.target) {
                    this.latest = {}; this.comparison = [{ key: 'Proyek', current: 'Data terbaru tersedia', draft: 'Draf baru tetap disimpan' }];
                } else {
                    this.latest = await this.request(path);
                    const source = this.mode.startsWith('day-') ? this.latest.editable : this.mode === 'crew-edit' ? { ...this.latest.profile, full_name: this.latest.profile.full_name, position_id: this.latest.crew.position_id || '' } : this.mode === 'location-edit' ? this.latest.location : {};
                    this.comparison = Object.entries(this.form).filter(([key]) => key !== 'reason').map(([key, value]) => ({ key: this.fieldLabel(key), current: this.displayValue(source?.[key]), draft: this.displayValue(value) }));
                }
            } catch (e) { this.error = e.message; }
            finally { this.loading = false; }
        },
        acceptLatest() {
            if (!this.latest) return;
            // Keep only the user's changed fields; retain unrelated remote edits.
            const delta = changedFields(this.form, this.baseline);
            let source = null;
            if (this.mode === 'crew-edit') source = Object.fromEntries(Object.keys(this.form).map(key => [key, key === 'position_id' ? this.latest.crew.position_id || '' : this.latest.profile[key] || '']));
            if (this.mode === 'location-edit') source = Object.fromEntries(Object.keys(this.form).map(key => [key, this.latest.location[key] || '']));
            if (this.mode.startsWith('day-') && this.target) source = Object.fromEntries(['date', 'start', 'location', 'call_sheet', 'items'].filter(key => key in this.latest.editable).map(key => [key, this.latest.editable[key]]));
            const merge = (base, diff) => { for (const [key, value] of Object.entries(diff)) base[key] = value && typeof value === 'object' && !Array.isArray(value) ? merge(base[key] || {}, value) : value; return base; };
            if (source) { this.baseline = copy(source); this.form = merge(copy(source), delta); }
            if (this.target) this.detail = this.latest;
            this.latest = null; this.comparison = []; this.remoteChanged = false; this.storeDraft();
            this.refreshRevision();
        },
        async refreshRevision() {
            try { if (this.mode.startsWith('day-')) this.sheet = await this.request('call-sheet-editor'); }
            catch (e) { this.error = e.message; this.remoteChanged = true; }
        },
        displayValue(value) { return value === undefined ? '—' : typeof value === 'object' ? JSON.stringify(value) : String(value); },
        fieldLabel(key) { return ({ full_name: 'Nama', stage_name: 'Nama panggung', phone: 'Telepon', email: 'Email', position_id: 'Jabatan', filmography: 'Filmografi', address: 'Alamat', name: 'Nama lokasi', parking: 'Parkir', hospital: 'Rumah sakit', date: 'Tanggal', start: 'Mulai', location: 'Lokasi', call_sheet: 'Detail call sheet', items: 'Kegiatan', latitude: 'Latitude', longitude: 'Longitude', status: 'Status', completed_on: 'Tanggal selesai', owner_user_id: 'Penanggung jawab', notes: 'Catatan', end_date: 'Sampai', reason: 'Alasan' })[key] || key; },
        async removeCrew() {
            if (!this.permission('can_remove_crew') || !this.form.reason || this.form.reason.length < 3) { this.error = 'Isi alasan minimal 3 karakter sebelum melepas kru.'; return; }
            if (!window.confirm('Lepas ' + this.detail.crew.name + ' · ' + this.detail.crew.role + ' dari proyek? Personel organisasi tetap tersimpan.')) return;
            await this.mutate({ endpoint: 'crew/' + this.target, method: 'DELETE', data: { expected_version: this.detail.crew.version, reason: this.form.reason, confirmed: true } });
        },
        previewDay(day) {
            this.mode = 'sheet-preview'; this.target = day.id;
            this.error = ''; this.dirty = false; this.$refs.editorDialog.showModal();
        },
        async dayAction(day, action) {
            if (this.busy || this.dirty || this.pending) return;
            const permission = action === 'remove' ? 'can_remove_call_sheet' : 'can_lock_call_sheet';
            if (!this.permission(permission)) return;
            if (!window.confirm(action === 'remove' ? 'Hapus hari ' + day.date + '? Hari harus belum terkunci.' : (day.locked ? 'Buka kunci' : 'Kunci') + ' hari ' + day.date + '? Penguncian memeriksa bentrokan jadwal dan ketersediaan.')) return;
            this.mode = 'day-action'; this.$refs.editorDialog.showModal();
            await this.mutate({ endpoint: 'call-sheets/' + day.id + (action === 'remove' ? '' : '/lock'), method: action === 'remove' ? 'DELETE' : 'PATCH', data: { expected_revision: this.sheet.schedule_revision, confirmed: true, ...(action === 'remove' ? {} : { locked: !day.locked }) } });
        },
    };
}
