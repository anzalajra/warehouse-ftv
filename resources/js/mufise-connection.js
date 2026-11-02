export function mufiseConnection(config) {
    return {
        busy: false, polling: false, message: '', failed: false, verificationUrl: '', deadline: 0, timer: null,
        resume() {
            if (config.pending) this.waitForApproval(config.pending);
        },
        destroy() {
            clearTimeout(this.timer);
        },
        waitForApproval(data) {
            clearTimeout(this.timer);
            this.verificationUrl = data.verification_url;
            this.deadline = Date.parse(data.expires_at);
            this.failed = false;
            this.message = 'waiting for syncronization';
            this.polling = true;
            this.timer = setTimeout(() => this.poll(), 5000);
        },
        async send(url) {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            if (response.redirected) throw new Error('Sesi Anda berakhir. Silakan masuk kembali ke Warehouse.');
            const data = await response.json().catch(() => { throw new Error('Koneksi Mufise belum dapat diproses. Silakan coba lagi.'); });
            if (!response.ok) {
                const error = new Error(data.message || 'Permintaan gagal. Silakan coba lagi.');
                error.retry = response.status === 429 || response.status >= 500;
                error.wait = Math.max(5, Number(response.headers.get('Retry-After')) || 5);
                throw error;
            }
            return data;
        },
        async connect() {
            if (this.busy || this.polling) return;
            this.busy = true;
            this.failed = false;
            this.message = 'Menyiapkan koneksi Mufise…';
            this.verificationUrl = '';
            // Open within the click event, before awaiting the server, to retain browser user activation.
            let mufiseTab = null;
            try {
                mufiseTab = window.open('about:blank', '_blank');
                if (mufiseTab) mufiseTab.opener = null;
            } catch { /* The fallback link remains available if popups are blocked. */ }
            try {
                const data = await this.send(config.connectUrl);
                this.waitForApproval(data);
                if (mufiseTab && !mufiseTab.closed) {
                    try { mufiseTab.location.replace(data.verification_url); }
                    catch { mufiseTab.close(); }
                }
            } catch (error) {
                if (mufiseTab && !mufiseTab.closed) mufiseTab.close();
                this.failed = true;
                this.message = error.message;
            } finally {
                this.busy = false;
            }
        },
        async poll() {
            if (!this.polling) return;
            if (Date.now() >= this.deadline) {
                this.polling = false;
                this.verificationUrl = '';
                this.failed = true;
                this.message = 'Koneksi kedaluwarsa. Silakan hubungkan kembali akun Mufise.';
                return;
            }
            let wait = 5;
            try {
                const data = await this.send(config.pollUrl);
                if (data.connected) {
                    this.polling = false;
                    window.location.reload();
                    return;
                }
                this.failed = false;
                this.message = 'waiting for syncronization';
            } catch (error) {
                this.failed = true;
                this.message = error.message;
                if (!error.retry) {
                    this.polling = false;
                    this.verificationUrl = '';
                    return;
                }
                wait = error.wait || 5;
            }
            this.timer = setTimeout(() => this.poll(), wait * 1000);
        },
        async disconnect() {
            if (this.busy || this.polling) return;
            this.busy = true;
            this.failed = false;
            try {
                await this.send(config.disconnectUrl);
                window.location.reload();
            } catch (error) {
                this.failed = true;
                this.message = error.message;
            } finally {
                this.busy = false;
            }
        },
    };
}
