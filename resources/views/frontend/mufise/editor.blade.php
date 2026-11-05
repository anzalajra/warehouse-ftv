<div class="mf-editor space-y-4" x-data="window.mufiseEditor(@js($editorConfig))" @if($compact ?? false) x-effect="setProject(mufiseProject)" @endif>
    <style>
        .mf-editor [x-cloak]{display:none!important}.mf-editor .mf-card{background:white;border:1px solid #e5e7eb;border-radius:16px;padding:20px}.mf-editor .mf-btn{border:1px solid #d1d5db;border-radius:9px;padding:8px 12px;font-size:13px;font-weight:600;background:white;display:inline-flex;align-items:center;justify-content:center;gap:6px}.mf-editor .mf-btn:disabled{opacity:.45;cursor:not-allowed}.mf-editor .mf-primary{background:var(--color-primary-600,#0284c7);color:white;border-color:transparent}.mf-editor label{display:block;font-size:13px;font-weight:500}.mf-editor input:not([type=checkbox]):not([type=file]),.mf-editor textarea,.mf-editor select{display:block;width:100%;border:1px solid #d1d5db;border-radius:8px;padding:9px;margin-top:5px;font-size:14px;background:white}.mf-editor input:focus,.mf-editor textarea:focus,.mf-editor select:focus{outline:2px solid #38bdf8;outline-offset:1px}.mf-editor dialog{margin:auto;border:0;border-radius:18px;padding:0;width:min(900px,94vw);max-height:92dvh;color:#111827}.mf-editor dialog::backdrop{background:#0f172a88}.mf-editor .mf-dialog-body{padding:20px;overflow:auto;flex:1;min-height:0}.mf-editor .mf-dialog-shell{display:flex;flex-direction:column;max-height:92dvh}.mf-editor [aria-invalid=true]{border-color:#dc2626!important}.mf-editor .mf-muted{font-size:12px;color:#64748b}@media(max-width:640px){.mf-editor dialog{width:100%;max-width:100%;height:100dvh;max-height:100dvh;border-radius:0}.mf-editor .mf-dialog-shell{height:100dvh;max-height:100dvh}.mf-editor .mf-card{padding:14px}}
    </style>
    <div x-show="loading && !mode" class="mf-card animate-pulse" role="status">Memuat data proyek…</div>
    <div x-show="error && !mode" x-cloak role="alert" class="rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-800">
        <p x-text="error"></p><button type="button" class="mf-btn mt-2" @click="setProject(projectId, true)">Coba lagi</button>
    </div>
    <p x-show="notice" x-cloak role="status" class="rounded-xl bg-sky-50 p-3 text-sm" x-text="notice"></p>
    <template x-if="bundle">
        <div class="space-y-4">
            <div class="mf-card space-y-4">
                <div class="flex flex-wrap justify-between gap-3">
                    <div><p class="mf-muted" x-text="capabilities?.organization?.name || 'Proyek Mufise'"></p><h2 class="font-bold text-lg" x-text="bundle.project.title"></h2><p class="mf-muted" x-text="bundle.project.production_name || ''"></p></div>
                    <div class="flex items-start flex-wrap gap-2">
                        <button type="button" class="mf-btn" :disabled="syncing" @click="sync(true)">↻ Perbarui</button>
                        <a class="mf-btn" :href="directUrl()" target="_blank" rel="noopener noreferrer">Buka di Mufise ↗</a>
                        @if($compact ?? false)<a class="mf-btn mf-primary" :href="@js(url('/customer/connections/mufise/projects')) + '/' + encodeURIComponent(projectId)" target="_blank" rel="noopener noreferrer">Editor lengkap ↗</a>@endif
                    </div>
                </div>
                @if($compact ?? false)
                <div class="flex flex-wrap gap-2 text-xs">
                    <template x-for="stage in ['submit','collect']" :key="stage"><span class="rounded-full px-3 py-1 font-semibold" :class="ready(stage) ? 'bg-green-50 text-green-800' : 'bg-amber-50 text-amber-800'" x-text="(stage === 'submit' ? 'Booking' : 'Pengambilan') + ': ' + (ready(stage) ? 'siap' : 'belum lengkap')"></span></template>
                </div>
                <template x-for="message in stageMissing('submit')" :key="message"><p class="text-xs text-amber-800" x-text="message"></p></template>
                <details><summary class="mf-muted cursor-pointer">Lihat syarat & status pengambilan</summary><div class="space-y-2 pt-2"><p class="mf-muted" x-text="'Booking: ' + requirement('submit')"></p><p class="mf-muted" x-text="'Pengambilan: ' + requirement('collect')"></p><template x-for="message in stageMissing('collect')" :key="message"><p class="text-xs text-amber-800" x-text="message"></p></template></div></details>
                @else
                <div class="grid sm:grid-cols-2 gap-3">
                    <template x-for="stage in ['submit', 'collect']" :key="stage">
                        <div class="rounded-xl p-3 border" :class="ready(stage) ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200'">
                            <p class="text-sm font-semibold"><span x-text="stage === 'submit' ? 'Booking' : 'Pengambilan'"></span> · <span x-text="ready(stage) ? 'Siap' : 'Belum lengkap'"></span></p>
                            <p class="mf-muted mt-1" x-text="requirement(stage)"></p>
                            <template x-for="message in stageMissing(stage)" :key="message"><p class="text-xs mt-1" x-text="'• ' + message"></p></template>
                            <p class="text-xs mt-1" x-show="bundle.readiness?.missing_crew_roles?.[stage]?.length" x-text="'Jabatan: ' + (bundle.readiness?.missing_crew_roles?.[stage] || []).join(', ')"></p>
                        </div>
                    </template>
                </div>
                <p class="mf-muted">Pengambilan mewarisi syarat booking. Diperiksa <span x-text="bundle.checked_at"></span>.</p>
                @endif
            </div>
            <div class="mf-card space-y-4">
                <div role="tablist" aria-label="Data proyek" class="flex gap-2 overflow-x-auto border-b pb-3">
                    <template x-for="tab in [{id:'crew',label:'Kru',count:bundle.crew?.length},{id:'locations',label:'Lokasi',count:bundle.locations?.length},{id:'sheets',label:'Call sheet',count:bundle.call_sheets?.length}]" :key="tab.id">
                        <button type="button" role="tab" class="mf-btn whitespace-nowrap" :aria-selected="activeTab === tab.id" :class="activeTab === tab.id && 'mf-primary'" @click="showTab(tab.id)" x-text="tab.label + ' · ' + (tab.count || 0)"></button>
                    </template>
                </div>
                <section x-show="activeTab === 'crew'" aria-label="Kru proyek" class="space-y-3">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" x-show="permission('can_search_organization_crew')" class="mf-btn mf-primary" @click="open('crew-pick')">Tambah kru organisasi</button>
                        <button type="button" x-show="permission('can_edit_crew') && capabilities?.features?.crew_duplicate_preview" class="mf-btn" @click="open('crew-new')">Buat kru baru</button>
                    </div>
                    <p x-show="!permission('can_edit_crew')" class="mf-muted">Kru tersedia dalam mode baca. Izin edit mengikuti koneksi dan persetujuan akun Mufise.</p>
                    <p x-show="permission('can_edit_crew') && !permission('can_search_organization_crew')" class="mf-muted">Picker organisasi memerlukan izin tambahan dan pemasangan ulang akun.</p>
                    <template x-for="person in (bundle.crew || []){{ ($compact ?? false) ? '.slice(0,3)' : '' }}" :key="person.id">
                        <button type="button" class="flex gap-3 w-full text-left border rounded-xl p-3 hover:bg-gray-50" @click="open('crew-edit', person)">
                            <span class="w-10 h-10 bg-sky-50 rounded-full flex items-center justify-center shrink-0 font-bold" x-text="person.name?.slice(0,1)"></span>
                            <span class="min-w-0"><span class="font-semibold block text-sm" x-text="person.name"></span><span class="mf-muted" x-text="[person.role, person.department].filter(Boolean).join(' · ')"></span><span x-show="person.registered_account" class="text-xs text-sky-700 block">Akun terdaftar</span></span>
                            <span class="ml-auto mf-muted">Detail →</span>
                        </button>
                    </template>
                    <p x-show="!bundle.crew?.length" class="mf-muted py-5">Belum ada kru pada proyek ini.</p>
                </section>
                <section x-show="activeTab === 'locations'" aria-label="Lokasi proyek" class="space-y-3">
                    <button type="button" x-show="permission('can_edit_locations')" class="mf-btn mf-primary" @click="open('location-new')">Tambah lokasi</button>
                    <template x-for="location in (bundle.locations || []){{ ($compact ?? false) ? '.slice(0,3)' : '' }}" :key="location.id">
                        <button type="button" class="w-full text-left border rounded-xl p-4 hover:bg-gray-50" @click="open('location-edit', location)"><span class="font-semibold block text-sm" x-text="location.name"></span><span class="mf-muted" x-text="location.address"></span><span class="float-right mf-muted">Detail →</span></button>
                    </template>
                    <p x-show="!bundle.locations?.length" class="mf-muted py-5">Belum ada lokasi. Tambahkan bila diwajibkan atau dibutuhkan produksi.</p>
                </section>
                <section x-show="activeTab === 'sheets'" aria-label="Call sheet" class="space-y-3">
                    <button type="button" x-show="permission('can_edit_call_sheet') && sheet" class="mf-btn mf-primary" @click="open('day-new')">Tambah hari</button>
                    <template x-if="sheet"><div class="space-y-3">
                        <div x-show="sheet.schedule_issues?.length" class="p-3 rounded-xl bg-amber-50 text-sm"><p class="font-semibold">Peringatan jadwal</p><template x-for="(issue,index) in sheet.schedule_issues || []" :key="index"><button type="button" class="block text-left underline mt-1" @click="issue.day_id && open('day-edit', {id: issue.day_id})" x-text="issue.message || issue.code || 'Tinjau jadwal'"></button></template></div>
                        <template x-for="(day,index) in (sheet.days || []){{ ($compact ?? false) ? '.slice(0,2)' : '' }}" :key="day.id"><div class="border rounded-xl p-4 space-y-3">
                            <p class="font-semibold text-sm" x-text="'Day ' + (index + 1) + ' · ' + day.date + ' · ' + day.start + (day.locked ? ' · Terkunci' : ' · Draf')"></p>
                            <p class="mf-muted" x-text="[day.location, (day.items?.length || 0) + ' kegiatan'].filter(Boolean).join(' · ')"></p>
                            <div class="flex gap-2 flex-wrap">
                                <button type="button" class="mf-btn" @click="open('day-edit', day)" x-text="day.locked || !permission('can_edit_call_sheet') ? 'Lihat hari' : 'Edit hari'"></button>
                                <button type="button" x-show="day.items?.length" @click="previewDay(day)" class="mf-btn">Preview</button>
                                <a x-show="day.items?.length" :href="media('call-sheets/' + day.id,'pdf')" class="mf-btn">Unduh PDF</a>
                                <button type="button" x-show="permission('can_lock_call_sheet')" class="mf-btn" :disabled="busy || dirty" @click="dayAction(day,'lock')" x-text="day.locked ? 'Buka kunci' : 'Kunci hari'"></button>
                                <button type="button" x-show="permission('can_remove_call_sheet') && !day.locked" class="mf-btn text-red-700" :disabled="busy || dirty" @click="dayAction(day,'remove')">Hapus hari</button>
                            </div>
                        </div></template>
                        <p x-show="!sheet.days?.length" class="mf-muted">Belum ada hari. Mulai dari Day 1.</p>
                    </div></template>
                    <p x-show="!sheet" class="mf-muted">Editor call sheet memerlukan akses Production dan Plan. Gunakan Perbarui atau buka Mufise jika akses belum tersedia.</p>
                </section>
            </div>
        </div>
    </template>
    @include('frontend.mufise.editor-dialog')
</div>
