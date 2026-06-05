<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            statePath: @js($getStatePath()),
            drawing: false,
            ctx: null,
            init() {
                const canvas = this.$refs.canvas;
                const ratio = window.devicePixelRatio || 1;
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                this.ctx = canvas.getContext('2d');
                this.ctx.scale(ratio, ratio);
                this.ctx.lineWidth = 2;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                this.ctx.strokeStyle = '#111827';

                const existing = $wire.get(this.statePath);
                if (existing) {
                    const img = new Image();
                    img.onload = () => this.ctx.drawImage(img, 0, 0, canvas.offsetWidth, canvas.offsetHeight);
                    img.src = existing;
                }
            },
            point(e) {
                const r = this.$refs.canvas.getBoundingClientRect();
                const t = e.touches ? e.touches[0] : e;
                return { x: t.clientX - r.left, y: t.clientY - r.top };
            },
            start(e) {
                this.drawing = true;
                const p = this.point(e);
                this.ctx.beginPath();
                this.ctx.moveTo(p.x, p.y);
            },
            move(e) {
                if (! this.drawing) return;
                e.preventDefault();
                const p = this.point(e);
                this.ctx.lineTo(p.x, p.y);
                this.ctx.stroke();
            },
            end() {
                if (! this.drawing) return;
                this.drawing = false;
                $wire.set(this.statePath, this.$refs.canvas.toDataURL('image/png'));
            },
            clear() {
                const c = this.$refs.canvas;
                this.ctx.clearRect(0, 0, c.width, c.height);
                $wire.set(this.statePath, null);
            }
        }"
        wire:ignore
        class="space-y-2"
    >
        <canvas
            x-ref="canvas"
            class="w-full rounded-lg border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-900"
            style="height: 180px; touch-action: none; cursor: crosshair;"
            @mousedown="start($event)"
            @mousemove="move($event)"
            @mouseup="end()"
            @mouseleave="end()"
            @touchstart.passive="start($event)"
            @touchmove="move($event)"
            @touchend="end()"
        ></canvas>

        <div class="flex justify-end">
            <button
                type="button"
                @click="clear()"
                class="text-xs font-medium text-gray-500 hover:text-danger-600"
            >
                Bersihkan tanda tangan
            </button>
        </div>
    </div>
</x-dynamic-component>
