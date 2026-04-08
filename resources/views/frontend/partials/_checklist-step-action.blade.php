{{-- Step action buttons/info for each checklist step --}}
@if($step['key'] === 'wa_confirm')
    <template x-if="steps[{{ $index }}].status === 'active' || steps[{{ $index }}].status === 'completed'">
        <a href="{{ $waLink }}" target="_blank" rel="noopener"
            class="inline-flex items-center mt-2 px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition">
            <svg class="w-3.5 h-3.5 mr-1.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.612.638l4.702-1.407A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.24 0-4.326-.727-6.022-1.96l-.424-.318-2.785.833.762-2.672-.348-.453A9.96 9.96 0 012 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
            Konfirmasi
        </a>
    </template>

@elseif($step['key'] === 'download_checklist')
    @if($checklistPdfUrl)
        <template x-if="steps[{{ $index }}].status === 'active'">
            <button @click="trackAndOpen('{{ route('customer.rental.mark-checklist-downloaded', $rental) }}', '{{ $checklistPdfUrl }}', {{ $index }})"
                class="inline-flex items-center mt-2 px-3 py-1.5 bg-primary-600 text-white text-xs font-medium rounded-lg hover:bg-primary-700 transition">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Download Checklist
            </button>
        </template>
        <template x-if="steps[{{ $index }}].status === 'completed'">
            <a href="{{ $checklistPdfUrl }}" target="_blank" rel="noopener"
                class="inline-flex items-center mt-2 px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-200 transition">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Download Checklist
            </a>
        </template>
    @endif
    <template x-if="steps[{{ $index }}].status === 'locked'">
        <p class="mt-2 text-xs text-gray-400">Selesaikan langkah sebelumnya</p>
    </template>

@elseif($step['key'] === 'permit_letter')
    @if($permitLink && $permitLink !== '#')
        <template x-if="steps[{{ $index }}].status === 'active'">
            <button @click="trackAndOpen('{{ route('customer.rental.mark-permit-clicked', $rental) }}', '{{ $permitLink }}', {{ $index }})"
                class="inline-flex items-center mt-2 px-3 py-1.5 bg-primary-600 text-white text-xs font-medium rounded-lg hover:bg-primary-700 transition">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Lihat Template Surat Perizinan
            </button>
        </template>
        <template x-if="steps[{{ $index }}].status === 'completed'">
            <a href="{{ $permitLink }}" target="_blank" rel="noopener"
                class="inline-flex items-center mt-2 px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-200 transition">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Lihat Template Surat Perizinan
            </a>
        </template>
    @endif
    <template x-if="steps[{{ $index }}].status === 'locked'">
        <p class="mt-2 text-xs text-gray-400">Selesaikan langkah sebelumnya</p>
    </template>

@elseif($step['key'] === 'physical_pickup')
    <template x-if="steps[{{ $index }}].status === 'active'">
        <div class="mt-2 text-xs text-primary-700 bg-primary-50 rounded-lg p-2">
            Bawa dokumen fisik saat pengambilan pada <strong>{{ $rental->start_date->format('d M Y') }}</strong> pukul <strong>{{ $rental->start_date->format('H:i') }}</strong>
        </div>
    </template>
    <template x-if="steps[{{ $index }}].status === 'completed'">
        <p class="mt-2 text-xs text-green-600">Pengambilan selesai</p>
    </template>
    <template x-if="steps[{{ $index }}].status === 'locked'">
        <p class="mt-2 text-xs text-gray-400">Selesaikan langkah sebelumnya</p>
    </template>
@endif
