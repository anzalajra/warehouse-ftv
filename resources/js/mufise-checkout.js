export function mufiseCheckout(config) {
    return {
        mufiseStatus: config.status,
        mufiseProjects: config.projects,
        mufiseError: config.error,
        mufiseProject: config.selected || '',
        mufiseRefreshing: false,
        mufiseNotice: '',
        mufiseProjectTitle() {
            return this.mufiseProjects.find(project => project.id === this.mufiseProject)?.title || '';
        },
        mufiseGuidance() {
            if (!this.mufiseStatus) return 'Hubungkan akun Mufise untuk memilih proyek produksi Anda.';
            return {
                join_organization: 'Lengkapi keanggotaan organisasi di Mufise untuk melanjutkan.',
                create_project: 'Buat proyek aktif di Mufise, lalu bagikan ke Warehouse.',
                select_project: 'Pilih proyek yang akan dibagikan ke Warehouse melalui pengaturan koneksi.',
                ready: this.mufiseProjects.length ? 'Akun terhubung. Pilih proyek untuk booking ini.' : 'Belum ada proyek yang dibagikan. Pilih atau buat proyek melalui pengaturan koneksi.',
            }[this.mufiseStatus.next_action] || 'Hubungkan ulang akun Mufise untuk memperbarui akses.';
        },
        async refreshMufiseProjects() {
            if (this.mufiseRefreshing) return;
            this.mufiseRefreshing = true;
            this.mufiseNotice = '';
            this.mufiseError = '';
            try {
                const response = await fetch(config.statusUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                if (response.redirected) throw new Error('Sesi Anda berakhir. Silakan masuk kembali ke Warehouse.');
                if (!response.ok) throw new Error('Proyek belum dapat diperbarui. Silakan coba lagi.');
                const data = await response.json().catch(() => { throw new Error('Proyek belum dapat diperbarui. Silakan coba lagi.'); });
                if (data.error) throw new Error(data.error);
                this.mufiseStatus = data.status;
                this.mufiseProjects = data.projects;
                if (!this.mufiseProjects.some(project => project.id === this.mufiseProject)) this.mufiseProject = '';
                this.mufiseNotice = data.status ? 'Daftar proyek berhasil diperbarui.' : 'Akun belum terhubung. Selesaikan koneksi di tab Mufise, lalu perbarui proyek.';
            } catch (error) {
                this.mufiseError = error.message;
            } finally {
                this.mufiseRefreshing = false;
            }
        },
    };
}
