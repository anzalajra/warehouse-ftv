@extends('layouts.frontend')

@section('title', $product->name)

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .flatpickr-day.flatpickr-disabled,
        .flatpickr-day.flatpickr-disabled:hover {
            color: #ffffff !important;
            background: #ef4444 !important;
            border-color: #ef4444 !important;
            text-decoration: none !important;
            opacity: 1 !important;
            cursor: not-allowed !important;
        }
        .flatpickr-day.closed-day {
            background: #f3f4f6;
            color: #9ca3af;
            border-color: #f3f4f6;
        }
        .flatpickr-day.closed-day:hover {
             background: #e5e7eb;
        }
        .flatpickr-day.closed-day.selected {
             background: #ef4444 !important; /* Should not happen if validation works, but fallback */
        }
        .flatpickr-day.partial-day {
            background: linear-gradient(135deg, #fee2e2 50%, transparent 50%);
            border: 1px solid #fee2e2;
        }
        .flatpickr-day.partial-day:hover {
            background: linear-gradient(135deg, #fecaca 50%, #f3f4f6 50%);
        }
    </style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Breadcrumb -->
    <nav class="text-sm mb-6">
        <a href="{{ route('catalog.index') }}" class="text-gray-500 hover:text-primary-600">Catalog</a>
        <span class="mx-2 text-gray-400">/</span>
        <span class="text-gray-900">{{ $product->name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
        <!-- Product Image -->
        <div>
            <div class="bg-white border border-gray-100 rounded-lg aspect-square flex items-center justify-center overflow-hidden p-4">
                @if($product->image)
                    <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" class="h-full w-full object-contain rounded-lg hover:scale-105 transition duration-500">
                @else
                    <span class="text-9xl">📷</span>
                @endif
            </div>
        </div>

        <!-- Product Info -->
        <div>
            <span class="text-sm text-primary-600">{{ $product->category->name }}</span>
            <h1 class="text-3xl font-bold mt-2 mb-4">{{ $product->name }}</h1>
            <p class="text-gray-600 mb-6">{{ $product->description }}</p>

            <div class="text-3xl font-bold text-primary-600 mb-6">
                Rp {{ number_format($product->daily_rate, 0, ',', '.') }} <span class="text-base font-normal text-gray-500">/ day</span>
            </div>

            <!-- Availability -->
            <div class="mb-6">
                <p class="font-semibold mb-2">Available Units: {{ $availableUnits->count() }}</p>
            </div>

            @auth('customer')
                @php
                    $customer = auth('customer')->user();
                    $canRent = $customer->canRent();
                    $verificationStatus = $customer->getVerificationStatus();
                @endphp

                <!-- Verification Warning -->
                @if(!$canRent)
                    <div class="mb-6 p-4 rounded-lg border 
                        @if($verificationStatus === 'pending') bg-yellow-50 border-yellow-300 
                        @else bg-red-50 border-red-300 @endif">
                        <div class="flex items-start">
                            @if($verificationStatus === 'pending')
                                <svg class="h-5 w-5 text-yellow-400 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-yellow-800">Verifikasi Sedang Diproses</p>
                                    <p class="text-sm text-yellow-700">Anda dapat melakukan rental setelah verifikasi disetujui.</p>
                                </div>
                            @else
                                <svg class="h-5 w-5 text-red-400 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-red-800">Verifikasi Diperlukan</p>
                                    <p class="text-sm text-red-700">
                                        <a href="{{ route('customer.profile') }}" class="underline font-semibold">Lengkapi verifikasi</a> untuk dapat melakukan rental.
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if($availableUnits->count() > 0)
                    <!-- Booking Form -->
                    <form id="addToCartForm" action="{{ route('cart.add') }}" method="POST" class="bg-gray-50 rounded-lg p-6">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        
                        @if($product->variations->isNotEmpty())
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Variasi</label>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    @foreach($product->variations as $variation)
                                        @php
                                            $varAvailableCount = $availableUnits->where('product_variation_id', $variation->id)->count();
                                            $isAvailable = $varAvailableCount > 0;
                                            $price = $variation->daily_rate ?? $product->daily_rate;
                                        @endphp
                                        <button type="button" 
                                            class="variation-btn border-2 rounded-lg p-3 text-sm text-center transition relative
                                                   {{ $isAvailable ? 'border-gray-200 hover:border-primary-400 cursor-pointer bg-white' : 'border-gray-100 opacity-60 cursor-not-allowed bg-gray-50' }}"
                                            data-id="{{ $variation->id }}"
                                            data-price="{{ $price }}"
                                            data-count="{{ $varAvailableCount }}"
                                            {{ !$isAvailable ? 'disabled' : '' }}>
                                            <span class="block font-semibold text-gray-900">{{ $variation->name }}</span>
                                            <span class="block text-xs text-gray-500 mt-1">
                                                Rp {{ number_format($price, 0, ',', '.') }}
                                            </span>
                                            <span class="block text-xs mt-1 {{ $isAvailable ? 'text-green-600' : 'text-red-500' }}">
                                                {{ $isAvailable ? $varAvailableCount . ' unit' : 'Habis' }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                                <input type="hidden" name="variation_id" id="variation_id" required>
                                <p id="variation-error" class="text-red-500 text-sm mt-1 hidden">Silakan pilih variasi produk.</p>
                            </div>
                        @endif

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Rentang Tanggal Sewa</label>
                            <div class="relative">
                                <input type="text" id="date_range" required placeholder="Pilih tanggal sewa..." 
                                    class="w-full border rounded-lg px-3 py-2 bg-white cursor-pointer" readonly>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Jam Pickup</label>
                                <input type="time" name="pickup_time" id="pickup_time" required value="09:00"
                                    class="w-full border rounded-lg px-3 py-2 bg-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Jam Return</label>
                                <input type="time" name="return_time" id="return_time" required value="09:00"
                                    class="w-full border rounded-lg px-3 py-2 bg-white">
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                            <input type="number" name="quantity" id="quantity" value="1" min="1" max="{{ $availableUnits->count() }}" required 
                                class="w-full border rounded-lg px-3 py-2 bg-white">
                            <p class="text-xs text-gray-500 mt-1">Maksimal: {{ $availableUnits->count() }} unit</p>
                        </div>

                        <input type="hidden" name="start_date" id="start_date">
                        <input type="hidden" name="end_date" id="end_date">

                @if($canRent)
                    @if($product->isFullyUnderMaintenance())
                        <button type="button" disabled class="w-full bg-red-500 text-white py-3 rounded-lg font-semibold cursor-not-allowed">
                            Under Maintenance
                        </button>
                    @else
                        <button type="submit" class="w-full bg-primary-600 text-white py-3 rounded-lg font-semibold hover:bg-primary-700 transition">
                            Add to Cart
                        </button>
                    @endif
                @else
                    <button type="button" disabled class="w-full bg-gray-400 text-white py-3 rounded-lg font-semibold cursor-not-allowed">
                        Verifikasi Diperlukan
                    </button>
                @endif
                    </form>
                @else
                    <div class="bg-yellow-50 text-yellow-700 p-4 rounded-lg">
                        No units available at the moment.
                    </div>
                @endif
            @else
                <div class="bg-gray-50 rounded-lg p-6 text-center">
                    <p class="mb-4">Please login to book this equipment</p>
                    <a href="{{ route('customer.login') }}" class="bg-primary-600 text-white px-6 py-2 rounded-lg inline-block hover:bg-primary-700">Login to Book</a>
                </div>
            @endauth
        </div>
    </div>

    <!-- Related Products -->
    @if($relatedProducts->count() > 0)
        <section class="mt-16">
            <h2 class="text-2xl font-bold mb-6">Related Products</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($relatedProducts as $related)
                    <div class="bg-white rounded-lg shadow overflow-hidden hover:shadow-lg transition">
                        <div class="aspect-square bg-white flex items-center justify-center p-4">
                            @if($related->image)
                                <img src="{{ Storage::url($related->image) }}" alt="{{ $related->name }}" class="h-full w-full object-contain">
                            @else
                                <span class="text-4xl">📷</span>
                            @endif
                        </div>
                        <div class="p-4 border-t border-gray-100">
                            <h3 class="font-semibold mb-2">{{ $related->name }}</h3>
                            <p class="text-primary-600 font-bold">Rp {{ number_format($related->daily_rate, 0, ',', '.') }}/day</p>
                            <a href="{{ route('catalog.show', $related) }}" class="mt-2 block text-center text-primary-600 text-sm hover:underline">View Details</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variation Selection Logic
            const variationBtns = document.querySelectorAll('.variation-btn');
            const variationInput = document.getElementById('variation_id');
            const variationError = document.getElementById('variation-error');
            const quantityInput = document.getElementById('quantity');
            const priceDisplay = document.querySelector('.text-3xl.font-bold.text-primary-600');
            const availableCountDisplay = document.querySelector('.font-semibold.mb-2');

            if (variationBtns.length > 0) {
                variationBtns.forEach(btn => {
                    btn.addEventListener('click', function() {
                        if (this.disabled) return;

                        // Reset active state
                        variationBtns.forEach(b => {
                            b.classList.remove('border-primary-400', 'bg-blue-50', 'ring-2', 'ring-primary-200');
                            b.classList.add('border-gray-200');
                        });

                        // Set active state
                        this.classList.remove('border-gray-200');
                        this.classList.add('border-primary-400', 'bg-blue-50', 'ring-2', 'ring-primary-200');

                        // Update form
                        variationInput.value = this.dataset.id;
                        if (variationError) variationError.classList.add('hidden');

                        // Update Price
                        const price = parseInt(this.dataset.price);
                        const formattedPrice = new Intl.NumberFormat('id-ID').format(price);
                        if (priceDisplay && priceDisplay.firstChild) {
                            priceDisplay.firstChild.nodeValue = `Rp ${formattedPrice} `;
                        }

                        // Update Available Count
                        const count = parseInt(this.dataset.count);
                        if (availableCountDisplay) {
                            availableCountDisplay.textContent = `Available Units: ${count}`;
                        }
                        
                        // Update Quantity Max
                        if (quantityInput) {
                            quantityInput.max = count;
                            if (parseInt(quantityInput.value) > count) {
                                quantityInput.value = count;
                            }
                            // Trigger input event to update any other listeners
                            quantityInput.dispatchEvent(new Event('input'));
                        }
                    });
                });
            }

            // Handle Add to Cart with Smart Sync
            const form = document.getElementById('addToCartForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Validate Variation
                    if (variationInput && !variationInput.value && variationBtns.length > 0) {
                        if (variationError) variationError.classList.remove('hidden');
                        // Scroll to variation section
                        variationInput.closest('.mb-6').scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return;
                    }

                    const formData = new FormData(form);
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.innerHTML;
                    
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = 'Processing...';

                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: formData
                    })
                    .then(response => response.json().then(data => ({ status: response.status, body: data })))
                    .then(({ status, body }) => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;

                        if (status === 200) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: body.message,
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                window.location.reload();
                            });
                        } else if (status === 409) {
                            // Conflict detected
                            const conflictsList = body.conflicts.map(item => `<li>• ${item}</li>`).join('');
                            
                            Swal.fire({
                                title: 'Perubahan Tanggal Sewa',
                                html: `
                                    <p class="mb-4">Mengubah tanggal sewa akan menghapus item berikut dari keranjang karena tidak tersedia di tanggal baru:</p>
                                    <ul class="text-left bg-gray-100 p-4 rounded mb-4 text-sm font-medium text-red-600">
                                        ${conflictsList}
                                    </ul>
                                    <p>Apakah Anda ingin melanjutkan?</p>
                                `,
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#d33',
                                cancelButtonColor: '#3085d6',
                                confirmButtonText: 'Ya, Lanjutkan',
                                cancelButtonText: 'Batal'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    // Retry with confirmation
                                    formData.append('confirm_changes', '1');
                                    
                                    // Recursive call or just duplicate logic? Duplicate for simplicity here
                                    submitBtn.disabled = true;
                                    submitBtn.innerHTML = 'Updating...';
                                    
                                    fetch(form.action, {
                                        method: 'POST',
                                        headers: {
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                        },
                                        body: formData
                                    })
                                    .then(res => res.json().then(d => ({ s: res.status, b: d })))
                                    .then(({ s, b }) => {
                                        if (s === 200) {
                                            Swal.fire('Updated!', b.message, 'success').then(() => window.location.reload());
                                        } else {
                                            Swal.fire('Error', b.message || 'Something went wrong', 'error');
                                            submitBtn.disabled = false;
                                            submitBtn.innerHTML = originalBtnText;
                                        }
                                    });
                                }
                            });
                        } else {
                            // Validation or other error
                            Swal.fire({
                                icon: 'error',
                                title: 'Oops...',
                                text: body.message || 'Something went wrong!'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Network error occurred. Please try again.'
                        });
                    });
                });
            }

            const bookedDates = @json($bookedDates);
            const partialDates = @json($partialDates ?? []);
            const operationalDays = @json($operationalDays);
            const holidaysRaw = @json($holidays);
            const holidays = [];
            holidaysRaw.forEach(h => {
                if (h.start_date && h.end_date) {
                    const [sy, sm, sd] = h.start_date.split('-').map(Number);
                    const [ey, em, ed] = h.end_date.split('-').map(Number);
                    let current = new Date(sy, sm - 1, sd);
                    const end = new Date(ey, em - 1, ed);
                    while (current <= end) {
                        const year = current.getFullYear();
                        const month = String(current.getMonth() + 1).padStart(2, '0');
                        const day = String(current.getDate()).padStart(2, '0');
                        holidays.push(`${year}-${month}-${day}`);
                        current.setDate(current.getDate() + 1);
                    }
                } else if (h.date) {
                    holidays.push(h.date);
                }
            });

            const isClosed = function(date) {
                // 1. Check operational days
                if (!operationalDays.includes(date.getDay().toString())) return true;
                
                // 2. Check holidays
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const dayStr = String(date.getDate()).padStart(2, '0');
                const dateStr = `${year}-${month}-${dayStr}`;
                
                if (holidays.includes(dateStr)) return true;
                
                return false;
            };

            const disableRules = [...bookedDates];

            const pickupTimeInput = document.getElementById('pickup_time');
            const returnTimeInput = document.getElementById('return_time');
            
            // Helper to check if a date is partial
            const isPartialDate = (date) => {
                 const year = date.getFullYear();
                 const month = String(date.getMonth() + 1).padStart(2, '0');
                 const dayStr = String(date.getDate()).padStart(2, '0');
                 const dateStr = `${year}-${month}-${dayStr}`;
                 return partialDates.includes(dateStr);
            };

            
            // URL Params
            const urlStartDate = "{{ request('start_date') }}";
            const urlEndDate = "{{ request('end_date') }}";
            const urlPickupTime = "{{ request('pickup_time') }}";
            const urlReturnTime = "{{ request('return_time') }}";

            // Load saved values
            const savedDates = localStorage.getItem('gearent_rental_dates');
            const savedPickup = localStorage.getItem('gearent_pickup_time');
            const savedReturn = localStorage.getItem('gearent_return_time');

            if (urlPickupTime) pickupTimeInput.value = urlPickupTime;
            else if (savedPickup) pickupTimeInput.value = savedPickup;

            if (urlReturnTime) returnTimeInput.value = urlReturnTime;
            else if (savedReturn) returnTimeInput.value = savedReturn;

            let selectedStart = null;
            let selectedEnd = null;

            const updateHiddenDates = () => {
                if (selectedStart && selectedEnd) {
                    const pickupTime = pickupTimeInput.value;
                    const returnTime = returnTimeInput.value;
                    
                    document.getElementById('start_date').value = `${selectedStart} ${pickupTime}:00`;
                    document.getElementById('end_date').value = `${selectedEnd} ${returnTime}:00`;

                    // Save times to localStorage
                    if (localStorage.getItem('gearent_pickup_time') !== pickupTime) {
                        localStorage.setItem('gearent_pickup_time', pickupTime);
                    }
                    if (localStorage.getItem('gearent_return_time') !== returnTime) {
                        localStorage.setItem('gearent_return_time', returnTime);
                    }
                }
            };

            let defaultDates = null;
            if (urlStartDate && urlEndDate) {
                defaultDates = [urlStartDate, urlEndDate];
                selectedStart = urlStartDate;
                selectedEnd = urlEndDate;
                updateHiddenDates();
                
                // Update localStorage to match current selection
                const dateStr = `${urlStartDate} to ${urlEndDate}`;
                if (localStorage.getItem('gearent_rental_dates') !== dateStr) {
                    localStorage.setItem('gearent_rental_dates', dateStr);
                }
            } else if (savedDates) {
                defaultDates = savedDates.split(' to ');
            }

            const fp = flatpickr("#date_range", {
                mode: "range",
                minDate: "today",
                dateFormat: "Y-m-d",
                disable: disableRules,
                defaultDate: defaultDates,
                onDayCreate: function(dObj, dStr, fp, dayElem) {
                    if (isClosed(dayElem.dateObj)) {
                        dayElem.classList.add('closed-day');
                    }
                    
                    const dateStr = fp.formatDate(dayElem.dateObj, "Y-m-d");
                    if (partialDates.includes(dateStr)) {
                        dayElem.classList.add('partial-day');
                    }
                },
                onChange: function(selectedDates, dateStr, instance) {
                    // Validate start/end dates are not closed days
                    if (selectedDates.length > 0) {
                        if (isClosed(selectedDates[0])) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Tanggal Tidak Tersedia',
                                text: 'Pengambilan tidak dapat dilakukan pada hari libur operasional.',
                                confirmButtonColor: '#ef4444'
                            });
                            instance.clear();
                            return;
                        }
                        
                        const startDate = selectedDates[0];
                        
                        if (isPartialDate(startDate)) {
                            // Partial dates have some bookings but are still available
                            // No specific time restriction from the data, just note it's partial
                        }
                        pickupTimeInput.removeAttribute('min');
                    }

                    if (selectedDates.length === 2) {
                        if (isClosed(selectedDates[1])) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Tanggal Tidak Tersedia',
                                text: 'Pengembalian tidak dapat dilakukan pada hari libur operasional.',
                                confirmButtonColor: '#ef4444'
                            });
                            instance.clear();
                            return;
                        }

                        selectedStart = instance.formatDate(selectedDates[0], "Y-m-d");
                        selectedEnd = instance.formatDate(selectedDates[1], "Y-m-d");
                        
                        // Save to localStorage if changed
                        if (localStorage.getItem('gearent_rental_dates') !== dateStr) {
                            localStorage.setItem('gearent_rental_dates', dateStr);
                        }
                        
                        updateHiddenDates();
                    }
                },
                onReady: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        selectedStart = instance.formatDate(selectedDates[0], "Y-m-d");
                        selectedEnd = instance.formatDate(selectedDates[1], "Y-m-d");
                        updateHiddenDates();
                    }
                }
            });

            pickupTimeInput.addEventListener('change', updateHiddenDates);
            returnTimeInput.addEventListener('change', updateHiddenDates);

            // Listen for changes from other tabs/windows
            window.addEventListener('storage', function(e) {
                if (e.key === 'gearent_rental_dates' && e.newValue) {
                    if (fp.input.value !== e.newValue) {
                        fp.setDate(e.newValue.split(' to '), true);
                    }
                }
                if (e.key === 'gearent_pickup_time' && e.newValue) {
                    if (pickupTimeInput.value !== e.newValue) {
                        pickupTimeInput.value = e.newValue;
                        updateHiddenDates();
                    }
                }
                if (e.key === 'gearent_return_time' && e.newValue) {
                    if (returnTimeInput.value !== e.newValue) {
                        returnTimeInput.value = e.newValue;
                        updateHiddenDates();
                    }
                }
            });
        });
    </script>
@endpush