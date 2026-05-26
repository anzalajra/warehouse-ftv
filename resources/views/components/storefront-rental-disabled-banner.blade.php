@if(\App\Models\Setting::isStorefrontRentalDisabled())
    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 flex items-start gap-3">
        <svg class="w-6 h-6 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"></path>
        </svg>
        <div>
            <p class="font-semibold text-amber-900">Sistem Rental Sedang Dinonaktifkan</p>
            <p class="text-sm text-amber-800 mt-1 whitespace-pre-line">{{ \App\Models\Setting::storefrontRentalDisabledMessage() }}</p>
        </div>
    </div>
@endif
