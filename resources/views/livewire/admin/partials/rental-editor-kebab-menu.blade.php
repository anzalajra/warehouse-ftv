{{-- Shared kebab dropdown for the rental editor header (desktop + mobile).
     Expects: $record (Rental|null), $rental_code (string).
     Outer wrapper must provide `open` Alpine state. --}}
@php
    $hasRecord = $record && $record->exists;
    $hasQuotation = $hasRecord && ! empty($record->quotation_id);
    $hasInvoice = $hasRecord && ! empty($record->invoice_id);
    $canCancel = $hasRecord && $record->canBeEdited();
@endphp
<div class="kebab-menu" x-show="open" x-cloak x-transition.opacity.duration.120ms @click="open = false">
    <button type="button" class="kebab-item" wire:click="duplicateRental" @if(!$hasRecord) disabled @endif>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>
        <span>Duplicate Rental</span>
    </button>
    <button type="button" class="kebab-item" wire:click="printQuotation" @if(!$hasQuotation) disabled @endif>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
        <span>Print Quotation</span>
    </button>
    <button type="button" class="kebab-item" wire:click="printInvoice" @if(!$hasInvoice) disabled @endif>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18V12M9 15h6"/></svg>
        <span>Print Invoice</span>
    </button>
    @if($canCancel)
        <div class="kebab-divider"></div>
        <button type="button" class="kebab-item danger"
                wire:click="cancelRental('Dibatalkan dari halaman edit')"
                wire:confirm="Batalkan rental ini? Tindakan ini tidak bisa dibatalkan.">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
            <span>Cancel Rental</span>
        </button>
    @endif
</div>
