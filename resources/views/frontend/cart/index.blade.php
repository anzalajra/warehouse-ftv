@extends('layouts.frontend')

@section('title', 'Shopping Cart')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
@endpush

@section('content')
@php($rentalDisabled = \App\Models\Setting::isStorefrontRentalDisabled())
@php($rentalDisabledMessage = \App\Models\Setting::storefrontRentalDisabledMessage())
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold mb-8">Shopping Cart</h1>
    <x-storefront-rental-disabled-banner />

    <!-- Verification Warning -->
    @if(!$canCheckout)
        @php $__cartCustomer = auth('customer')->user(); @endphp
        <div class="mb-6 p-4 bg-red-50 border border-red-300 rounded-lg">
            <div class="flex items-start">
                @if($__cartCustomer && $__cartCustomer->isBlocked())
                    <svg class="h-6 w-6 text-red-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728A9 9 0 015.636 5.636"/>
                    </svg>
                    <div>
                        <h3 class="text-sm font-medium text-red-800">Akun Diblokir</h3>
                        <p class="mt-1 text-sm text-red-700">Akun Anda telah diblokir oleh admin sehingga tidak dapat melakukan checkout.</p>
                        @if($__cartCustomer->blocked_reason)
                            <p class="mt-1 text-sm text-red-700"><span class="font-semibold">Alasan:</span> {{ $__cartCustomer->blocked_reason }}</p>
                        @endif
                    </div>
                @else
                    <svg class="h-6 w-6 text-red-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div>
                        <h3 class="text-sm font-medium text-red-800">Akun Belum Terverifikasi</h3>
                        <p class="mt-1 text-sm text-red-700">
                            Anda harus menyelesaikan verifikasi akun sebelum dapat melakukan checkout.
                            <a href="{{ route('customer.profile') }}" class="font-semibold underline">Lengkapi verifikasi sekarang →</a>
                        </p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($cartItems->count() > 0)
        <!-- Global Rental Settings -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-lg font-semibold mb-4">Rental Period (Applies to all items)</h2>
            <form action="{{ route('cart.update-all') }}" method="POST" id="global-date-form" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                @csrf
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                    <div class="relative">
                        <input type="text" id="global_date_range" class="w-full border rounded-lg px-3 py-2 bg-white cursor-pointer" placeholder="Select dates..." readonly>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pickup Time</label>
                    <input type="time" id="global_pickup_time" class="w-full border rounded-lg px-3 py-2 bg-white" value="{{ $cartItems->first()->start_date->format('H:i') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Return Time</label>
                    <input type="time" id="global_return_time" class="w-full border rounded-lg px-3 py-2 bg-white" value="{{ $cartItems->first()->end_date->format('H:i') }}">
                </div>
                
                <input type="hidden" name="start_date" id="global_start_date">
                <input type="hidden" name="end_date" id="global_end_date">
                
                <div class="md:col-span-4 flex justify-end">
                    <button type="submit" class="bg-primary-600 text-white px-6 py-2 rounded-lg hover:bg-primary-700 transition">
                        Update All Dates
                    </button>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2">
                @php
                    $groupedCartItems = $cartItems->groupBy(function($item) {
                        $variationId = $item->productUnit->product_variation_id ?? 'default';
                        return $item->productUnit->product->id . '-' . $variationId;
                    });
                @endphp

                <!-- Mobile Cart View -->
                <div class="space-y-4 md:hidden mb-6">
                    @foreach($groupedCartItems as $productId => $items)
                        @php
                            $firstItem = $items->first();
                            $product = $firstItem->productUnit->product;
                            $variation = $firstItem->productUnit->variation;
                            $quantity = $items->count();
                            $subtotal = $items->sum('subtotal');
                        @endphp
                        <div class="bg-white rounded-lg shadow p-4">
                            <div class="flex gap-4">
                                <div class="h-20 w-20 bg-gray-200 rounded flex-shrink-0 flex items-center justify-center">
                                    @if($product->image)
                                        <img src="{{ Storage::url($product->image) }}" alt="" class="h-full w-full object-cover rounded">
                                    @else
                                        <span class="text-2xl">📷</span>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-semibold">
                                        {{ $product->name }}
                                        @if($variation)
                                            <span class="text-gray-500 font-normal">({{ $variation->name }})</span>
                                        @endif
                                    </p>
                                    <p class="text-sm text-primary-600">Rp {{ number_format($firstItem->daily_rate, 0, ',', '.') }}/day</p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ $firstItem->start_date->format('d M H:i') }} - {{ $firstItem->end_date->format('d M H:i') }}
                                    </p>
                                    <div class="mt-2">
                                        <form action="{{ route('cart.update-quantity') }}" method="POST" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                                            @if($variation)
                                                <input type="hidden" name="variation_id" value="{{ $variation->id }}">
                                            @endif
                                            <label class="text-sm font-medium">Qty:</label>
                                            <input type="number" name="quantity" value="{{ $quantity }}" min="1" max="100" 
                                                   class="w-16 text-center border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500" 
                                                   onchange="this.form.submit()">
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-100">
                                <div>
                                    <span class="text-sm font-semibold">{{ $firstItem->days }}</span> <span class="text-xs text-gray-500">days</span>
                                    <p class="font-bold text-gray-900">Rp {{ number_format($subtotal, 0, ',', '.') }}</p>
                                </div>
                                <form action="{{ route('cart.remove-product') }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                        Remove All
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Desktop Cart View -->
                <div class="hidden md:block bg-white rounded-lg shadow overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <!-- Removed individual dates column as it's now global -->
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Days</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($groupedCartItems as $productId => $items)
                                @php
                                    $firstItem = $items->first();
                                    $product = $firstItem->productUnit->product;
                                    $variation = $firstItem->productUnit->variation;
                                    $quantity = $items->count();
                                    $subtotal = $items->sum('subtotal');
                                @endphp
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="h-16 w-16 bg-gray-200 rounded flex items-center justify-center mr-4">
                                                @if($product->image)
                                                    <img src="{{ Storage::url($product->image) }}" alt="" class="h-full w-full object-cover rounded">
                                                @else
                                                    <span class="text-2xl">📷</span>
                                                @endif
                                            </div>
                                            <div>
                                                <p class="font-semibold">
                                                    {{ $product->name }}
                                                    @if($variation)
                                                        <span class="text-gray-500 font-normal">({{ $variation->name }})</span>
                                                    @endif
                                                </p>
                                                <p class="text-sm text-primary-600">Rp {{ number_format($firstItem->daily_rate, 0, ',', '.') }}/day</p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    {{ $firstItem->start_date->format('d M H:i') }} - {{ $firstItem->end_date->format('d M H:i') }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <form action="{{ route('cart.update-quantity') }}" method="POST" class="flex items-center justify-center">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                                            @if($variation)
                                                <input type="hidden" name="variation_id" value="{{ $variation->id }}">
                                            @endif
                                            <input type="number" name="quantity" value="{{ $quantity }}" min="1" max="100" 
                                                   class="w-16 text-center border rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-primary-500" 
                                                   onchange="this.form.submit()">
                                        </form>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-semibold">{{ $firstItem->days }}</span> days
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold">
                                        Rp {{ number_format($subtotal, 0, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <form action="{{ route('cart.remove-product') }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                                            <button type="submit" class="text-red-600 hover:text-red-800" title="Remove All">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-between">
                    <a href="{{ route('catalog.index') }}" class="text-primary-600 hover:underline">← Continue Shopping</a>
                    <form action="{{ route('cart.clear') }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline">Clear Cart</button>
                    </form>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold mb-4">Order Summary</h2>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span>Rp {{ number_format($grossTotal, 0, ',', '.') }}</span>
                        </div>

                        @if($discountAmount > 0)
                            <div class="flex justify-between text-green-600">
                                <span>Discount ({{ $categoryName }})</span>
                                <span>- Rp {{ number_format($discountAmount, 0, ',', '.') }}</span>
                            </div>
                        @endif

                        @if($deposit > 0)
                        <div class="flex justify-between">
                            <span class="text-gray-600">Deposit</span>
                            <span>Rp {{ number_format($deposit, 0, ',', '.') }}</span>
                        </div>
                        @endif
                        <hr>
                        <div class="flex justify-between font-bold text-lg">
                            <span>Total</span>
                            <span class="text-primary-600">Rp {{ number_format($netTotal, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    @if($rentalDisabled)
                        <button type="button"
                                onclick="showRentalDisabledPopup()"
                                class="w-full block text-center bg-amber-500 text-white py-3 rounded-lg font-semibold hover:bg-amber-600 transition">
                            Proceed to Checkout
                        </button>
                    @elseif($canCheckout)
                        <a href="{{ route('checkout.index') }}" class="w-full block text-center bg-primary-600 text-white py-3 rounded-lg font-semibold hover:bg-primary-700 transition">
                            Proceed to Checkout
                        </a>
                    @else
                        <button disabled class="w-full bg-gray-400 text-white py-3 rounded-lg font-semibold cursor-not-allowed">
                            @if(auth('customer')->user() && auth('customer')->user()->isBlocked())
                                Akun Diblokir
                            @else
                            Verifikasi Diperlukan
                            @endif
                        </button>
                        <p class="text-xs text-center text-gray-500 mt-2">
                            <a href="{{ route('customer.profile') }}" class="text-primary-600 hover:underline">Lengkapi verifikasi</a> untuk checkout
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="text-center py-16">
            <div class="text-6xl mb-4">🛒</div>
            <h2 class="text-xl font-semibold mb-2">Your cart is empty</h2>
            <p class="text-gray-600 mb-6">Looks like you haven't added any items yet.</p>
            <a href="{{ route('catalog.index') }}" class="bg-primary-600 text-white px-6 py-3 rounded-lg inline-block hover:bg-primary-700">
                Browse Catalog
            </a>
        </div>
    @endif
</div>

@if($rentalDisabled)
    <div id="rentalDisabledModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex items-start gap-3">
                <svg class="w-8 h-8 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"></path>
                </svg>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-900">Sistem Rental Sedang Dinonaktifkan</h3>
                    <p class="text-sm text-gray-700 mt-2 whitespace-pre-line">{{ $rentalDisabledMessage }}</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="hideRentalDisabledPopup()"
                        class="px-4 py-2 bg-primary-600 text-white rounded-lg font-semibold hover:bg-primary-700">
                    Tutup
                </button>
            </div>
        </div>
    </div>
    <script>
        @if(session('rental_disabled'))
            document.addEventListener('DOMContentLoaded', function () { showRentalDisabledPopup(); });
        @endif
        function showRentalDisabledPopup() {
            const m = document.getElementById('rentalDisabledModal');
            if (m) { m.classList.remove('hidden'); m.classList.add('flex'); }
        }
        function hideRentalDisabledPopup() {
            const m = document.getElementById('rentalDisabledModal');
            if (m) { m.classList.add('hidden'); m.classList.remove('flex'); }
        }
    </script>
@endif
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Only run if the global date form exists
            if (!document.getElementById('global-date-form')) return;

            const pickupTimeInput = document.getElementById('global_pickup_time');
            const returnTimeInput = document.getElementById('global_return_time');
            const startDateInput = document.getElementById('global_start_date');
            const endDateInput = document.getElementById('global_end_date');
            
            let selectedStart = null;
            let selectedEnd = null;

            // Try to initialize from existing cart items (server-side rendered values)
            // But we can also check localStorage to see if it matches
            
            const updateHiddenDates = () => {
                if (selectedStart && selectedEnd) {
                    const pickupTime = pickupTimeInput.value;
                    const returnTime = returnTimeInput.value;
                    
                    startDateInput.value = `${selectedStart} ${pickupTime}:00`;
                    endDateInput.value = `${selectedEnd} ${returnTime}:00`;

                    // Update localStorage so catalog pages stay in sync
                    if (localStorage.getItem('gearent_pickup_time') !== pickupTime) {
                        localStorage.setItem('gearent_pickup_time', pickupTime);
                    }
                    if (localStorage.getItem('gearent_return_time') !== returnTime) {
                        localStorage.setItem('gearent_return_time', returnTime);
                    }
                    if (localStorage.getItem('gearent_rental_dates') !== `${selectedStart} to ${selectedEnd}`) {
                        localStorage.setItem('gearent_rental_dates', `${selectedStart} to ${selectedEnd}`);
                    }
                }
            };

            const fp = flatpickr("#global_date_range", {
                mode: "range",
                minDate: "today",
                dateFormat: "Y-m-d",
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        selectedStart = instance.formatDate(selectedDates[0], "Y-m-d");
                        selectedEnd = instance.formatDate(selectedDates[1], "Y-m-d");
                        updateHiddenDates();
                    }
                },
                onReady: function(selectedDates, dateStr, instance) {
                    // Initialize with the dates from the cart (passed from backend or parsed from existing items)
                    // Since we didn't pass specific variables, we can parse from the first item if needed
                    // OR rely on the fact that we should default to what the user sees
                    
                    // Actually, let's use the values from the first cart item as the source of truth
                    // We can inject them via PHP
                    @if($cartItems->count() > 0)
                        const initialStart = "{{ $cartItems->first()->start_date->format('Y-m-d') }}";
                        const initialEnd = "{{ $cartItems->first()->end_date->format('Y-m-d') }}";
                        instance.setDate([initialStart, initialEnd], true);
                    @endif
                }
            });

            pickupTimeInput.addEventListener('change', updateHiddenDates);
            returnTimeInput.addEventListener('change', updateHiddenDates);
            
            // Listen for changes from other tabs? 
            // Maybe not necessary here as this IS the source of truth when editing.
            // But if user updates cart in another tab, we should probably refresh?
            // For now, let's just focus on pushing changes out.
        });
    </script>
@endpush