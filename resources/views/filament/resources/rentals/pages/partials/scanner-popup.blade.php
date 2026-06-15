{{--
    Unit scanner popup — desktop modal + mobile full-screen sheet.
    Recreated from the design handoff (scanner.css / scanner-core.jsx) as an
    Alpine component (resources/js/unit-scanner.js). Included once per operation
    blade; opened via the window event `open-unit-scanner`.

    Expects: $mode ('pickup'|'return'), $rental, and the inherited $icon helper.
--}}
@once
    @vite('resources/js/unit-scanner.js')
@endonce

{{-- Shared .scn-* styles (also used by the global admin QR scanner hook). --}}
@include('filament.scanner-styles')

<div class="scn-host"
     wire:ignore
     x-data="unitScanner({
        mode: @js($mode),
        rentalCode: @js($rental->rental_code),
        prefix: @js(app(\App\Services\UnitCodeService::class)->prefix()),
        items: @js($this->scannableList()),
     })"
     x-on:open-unit-scanner.window="openScanner()"
     x-cloak>

    <template x-if="open">
        <div>
            {{-- DESKTOP — modal --}}
            <template x-if="variant==='desktop'">
                <div class="scn-root is-desktop">
                    <div class="scn-scrim" @click.self="close()" @keydown.escape.window="close()">
                        <div class="scn-modal">
                            @include('filament.resources.rentals.pages.partials.scanner-screens', ['closeAction' => 'close()'])
                        </div>
                    </div>
                </div>
            </template>

            {{-- MOBILE — list page + camera sheet --}}
            <template x-if="variant==='mobile'">
                <div class="scn-root is-mobile">
                    {{-- Pre-camera scan page --}}
                    <div class="scn-mobile" x-show="view==='list'" x-cloak>
                        <div class="scnm-head">
                            <button class="scnm-back" @click="close()" aria-label="Back to operation">{!! $icon('arrowLeft') !!}</button>
                            <div class="scnm-head-tx">
                                <span class="scn-sub" :class="modeKey" x-text="isReturn?'Return':'Pickup'" style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;"></span>
                                <b>Scan units</b>
                            </div>
                            <span class="scnm-code" x-text="rentalCode"></span>
                        </div>
                        <div class="scnm-prog">
                            <div class="scnm-prog-num"><b x-text="scanned"></b> / <span x-text="total"></span> units scanned</div>
                            <div class="scnm-track"><div class="scnm-fill" :style="'width:'+(total ? Math.round(scanned/total*100) : 0)+'%'"></div></div>
                        </div>
                        <div class="scnm-list">
                            <template x-for="it in items" :key="it.id">
                                <div class="scnm-row" :class="it.checked?'checked':''">
                                    <div class="scnm-thumb" :class="it.checked?'on':''">{!! $icon('cube') !!}</div>
                                    <div class="scnm-main">
                                        <div class="scnm-name" x-text="it.name"></div>
                                        <div class="scnm-sn" x-text="(it.serial||'—') + ' · ' + (it.type==='kit'?'Kit':'Unit')"></div>
                                    </div>
                                    <span class="check-ic-sm" :class="it.checked?'on':'off'"><template x-if="it.checked">{!! $icon('check') !!}</template></span>
                                </div>
                            </template>
                        </div>
                        <div class="scnm-bottom">
                            <button class="scnm-scan" @click="openCamSheet()">
                                {!! $icon('scan') !!}<span x-text="remaining===0 ? 'Scan again' : ('Scan unit · ' + remaining + ' left')"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Camera sheet --}}
                    <div class="scn-sheet" :class="phase==='live' ? 'on-cam' : 'on-surface'" x-show="view==='cam'" x-cloak>
                        @include('filament.resources.rentals.pages.partials.scanner-screens', ['closeAction' => 'backToList()'])
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
