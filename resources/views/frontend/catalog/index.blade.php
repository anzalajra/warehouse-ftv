@extends('layouts.frontend')

@section('title', 'Catalog')

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
    </style>
@endpush

@section('content')
@php($rentalDisabled = \App\Models\Setting::isStorefrontRentalDisabled())
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <x-storefront-rental-disabled-banner />
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Sidebar Filters -->
        <aside class="lg:w-64 flex-shrink-0" x-data="{ filtersOpen: false }">
            <div class="lg:hidden mb-4">
                <button @click="filtersOpen = !filtersOpen" type="button" class="w-full flex justify-between items-center bg-white p-4 rounded-lg shadow text-gray-700 hover:bg-gray-50">
                    <span class="font-semibold">Filters</span>
                    <svg class="w-5 h-5 transition-transform duration-200" :class="{'rotate-180': filtersOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
            </div>

            <div class="space-y-6 hidden lg:block" :class="{'hidden': !filtersOpen, 'block': filtersOpen}">
                <!-- Search -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold mb-4">Search</h3>
                    <form action="{{ route('catalog.index') }}" method="GET">
                        @foreach(request()->except(['search', 'page']) as $key => $value)
                            @if(is_array($value))
                                @foreach($value as $v)
                                    <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                                @endforeach
                            @else
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <div class="relative">
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search products..." class="w-full border rounded-lg pl-3 pr-10 py-2 text-sm">
                            <button type="submit" class="absolute right-2 top-2 text-gray-400 hover:text-primary-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold mb-4">Filters</h3>
                    <form action="{{ route('catalog.index') }}" method="GET">
                        @if(request('category'))
                            <input type="hidden" name="category" value="{{ request('category') }}">
                        @endif
                        @if(request('search'))
                            <input type="hidden" name="search" value="{{ request('search') }}">
                        @endif
                        @foreach((array) request('tags', []) as $tagSlug)
                            <input type="hidden" name="tags[]" value="{{ $tagSlug }}">
                        @endforeach

                        <!-- Brand -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Brand</label>
                            <select name="brand_id" class="w-full border rounded-lg px-3 py-2 text-sm bg-white">
                                <option value="">All Brands</option>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" {{ request('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Date Range -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Rental Dates</label>
                            <div class="relative">
                                <input type="text" id="date_range" placeholder="Select dates..."
                                    class="w-full border rounded-lg px-3 py-2 text-sm bg-white cursor-pointer" readonly>
                                <input type="hidden" name="start_date" id="start_date" value="{{ request('start_date') }}">
                                <input type="hidden" name="end_date" id="end_date" value="{{ request('end_date') }}">
                            </div>
                        </div>

                        <!-- Time -->
                        <div class="grid grid-cols-2 gap-2 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Pickup</label>
                                <input type="time" name="pickup_time" id="pickup_time" value="{{ request('pickup_time', '09:00') }}"
                                    class="w-full border rounded-lg px-2 py-2 text-sm bg-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Return</label>
                                <input type="time" name="return_time" id="return_time" value="{{ request('return_time', '09:00') }}"
                                    class="w-full border rounded-lg px-2 py-2 text-sm bg-white">
                            </div>
                        </div>

                        <!-- Sort -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                            <select name="sort" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="name" {{ request('sort') == 'name' ? 'selected' : '' }}>Name</option>
                                <option value="price_low" {{ request('sort') == 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                                <option value="price_high" {{ request('sort') == 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
                                <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>Newest</option>
                            </select>
                        </div>

                        <button type="submit" class="w-full bg-primary-600 text-white py-2 rounded-lg hover:bg-primary-700">
                            Apply Filters
                        </button>
                        <a href="{{ route('catalog.index', request()->only('category')) }}" class="block w-full text-center mt-3 text-sm text-gray-500 hover:text-gray-700">
                            Reset Filters
                        </a>
                    </form>
                </div>

                <!-- Categories -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold mb-4">Categories</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="{{ route('catalog.index', request()->except(['category', 'page'])) }}"
                               class="block px-2 py-1.5 rounded text-sm {{ !request('category') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                                All Categories
                            </a>
                        </li>
                        @foreach($categories as $category)
                            <li>
                                <a href="{{ route('catalog.index', array_merge(request()->except('page'), ['category' => $category->id])) }}"
                                   class="block px-2 py-1.5 rounded text-sm {{ request('category') == $category->id ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                                    {{ $category->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if($tags->isNotEmpty())
                    <!-- Tags -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="font-semibold mb-4">Tags</h3>
                        <form action="{{ route('catalog.index') }}" method="GET" id="tagsFilterForm">
                            @foreach(request()->except(['tags', 'page']) as $key => $value)
                                @if(is_array($value))
                                    @foreach($value as $v)
                                        <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                                    @endforeach
                                @else
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                                @foreach($tags as $tag)
                                    @php
                                        $tagColor = $tag->color ?: '#3b82f6';
                                        $checked = in_array($tag->slug, $selectedTagSlugs ?? []);
                                    @endphp
                                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                                        <input type="checkbox" name="tags[]" value="{{ $tag->slug }}"
                                               onchange="document.getElementById('tagsFilterForm').submit()"
                                               {{ $checked ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border"
                                              style="background-color: {{ $tagColor }}1a; color: {{ $tagColor }}; border-color: {{ $tagColor }}40;">
                                            {{ $tag->name }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @if(! empty($selectedTagSlugs))
                                <a href="{{ route('catalog.index', request()->except(['tags', 'page'])) }}"
                                   class="block mt-3 text-xs text-gray-500 hover:text-gray-700">
                                    Clear tags
                                </a>
                            @endif
                        </form>
                    </div>
                @endif
            </div>
        </aside>

        <!-- Products Grid -->
        <div class="flex-1" x-data="{ view: localStorage.getItem('catalog_view') || 'grid' }"
             x-init="$watch('view', v => localStorage.setItem('catalog_view', v))">
            <div class="flex justify-between items-center mb-6 gap-4 flex-wrap">
                <div class="flex items-baseline gap-4 flex-wrap">
                    <h1 class="text-2xl font-bold">Equipment Catalog</h1>
                    <p class="text-gray-600 text-sm">{{ $products->total() }} products found</p>
                </div>
                <div class="inline-flex rounded-lg border bg-white overflow-hidden">
                    <button type="button" @click="view='grid'"
                            :class="view==='grid' ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                            class="px-3 py-1.5 text-sm flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h6v6H4zM14 6h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
                        Grid
                    </button>
                    <button type="button" @click="view='list'"
                            :class="view==='list' ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                            class="px-3 py-1.5 text-sm flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        List
                    </button>
                </div>
            </div>

            <div :class="view === 'grid' ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6' : 'flex flex-col gap-4'">
                @forelse($products as $product)
                    @php
                        $unitsAvail = $product->units->whereNotIn('status', ['maintenance', 'retired']);
                        $availableCount = $unitsAvail->count();
                        $hasVar = $product->variations->isNotEmpty();
                        $variationPayload = $hasVar
                            ? $product->variations->map(fn($v) => [
                                'id' => $v->id,
                                'name' => $v->name,
                                'daily_rate' => $v->daily_rate ?? $product->daily_rate,
                                'available_count' => $unitsAvail->where('product_variation_id', $v->id)->count(),
                            ])
                            : null;
                    @endphp
                    <div class="bg-white rounded-lg shadow overflow-hidden hover:shadow-lg transition"
                         :class="view === 'list' ? 'flex flex-col sm:flex-row' : 'flex flex-col'">
                        <div class="bg-white flex items-center justify-center p-4 aspect-square"
                             :class="view === 'list' ? 'sm:w-48 sm:flex-shrink-0 sm:aspect-square' : ''">
                            @if($product->image)
                                <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" class="h-full w-full object-contain">
                            @else
                                <span class="text-6xl">📷</span>
                            @endif
                        </div>
                        <div class="p-4 border-t border-gray-100 flex-1 flex flex-col"
                             :class="view === 'list' ? 'sm:border-t-0 sm:border-l' : ''">
                            <p class="text-xs text-primary-600 mb-1">{{ $product->category->name }}@if($product->brand) · {{ $product->brand->name }}@endif</p>
                            <h3 class="font-semibold mb-2">{{ $product->name }}</h3>
                            @if($product->tags->isNotEmpty())
                                <div class="flex flex-wrap gap-1 mb-2">
                                    @foreach($product->tags->take(3) as $tag)
                                        @php $tagColor = $tag->color ?: '#3b82f6'; @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border"
                                              style="background-color: {{ $tagColor }}1a; color: {{ $tagColor }}; border-color: {{ $tagColor }}40;">
                                            {{ $tag->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            <p class="text-sm text-gray-600 mb-3 line-clamp-2">{{ Str::limit(strip_tags($product->description), 160) }}</p>
                            <div class="flex justify-between items-center mb-3">
                                <p class="text-primary-600 font-bold">Rp {{ number_format($product->daily_rate, 0, ',', '.') }}/day</p>
                                <span class="text-xs text-gray-500">{{ $availableCount }} available</span>
                            </div>
                            <div class="mt-auto" :class="view === 'list' ? 'flex flex-col sm:flex-row gap-2' : 'flex flex-col gap-2'">
                                <a href="{{ route('catalog.show', array_merge(['product' => $product], request()->only(['start_date', 'end_date', 'pickup_time', 'return_time']))) }}"
                                   class="block text-center bg-primary-600 text-white py-2 px-3 rounded hover:bg-primary-700 transition text-sm sm:flex-1">
                                    View Details
                                </a>
                                @auth('customer')
                                    @if($rentalDisabled)
                                        <button type="button" disabled
                                                class="block w-full bg-gray-400 text-white py-2 px-3 rounded text-sm font-semibold cursor-not-allowed sm:flex-1">
                                            Rental Dinonaktifkan
                                        </button>
                                    @else
                                        <button type="button"
                                                class="add-to-cart-btn block w-full bg-emerald-600 text-white py-2 px-3 rounded hover:bg-emerald-700 transition text-sm font-semibold sm:flex-1"
                                                data-product-id="{{ $product->id }}"
                                                data-product-name="{{ $product->name }}"
                                                data-has-variations="{{ $hasVar ? '1' : '0' }}"
                                                data-available="{{ $availableCount }}"
                                                @if($hasVar) data-variations='@json($variationPayload)' @endif
                                                @if($availableCount <= 0) disabled @endif>
                                            @if($availableCount <= 0)
                                                Habis
                                            @else
                                                Add to Cart
                                            @endif
                                        </button>
                                    @endif
                                @endauth
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12">
                        <p class="text-gray-500">No products found.</p>
                    </div>
                @endforelse
            </div>

            <!-- Pagination -->
            <div class="mt-8">
                {{ $products->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const pickupTimeInput = document.getElementById('pickup_time');
            const returnTimeInput = document.getElementById('return_time');

            const operationalDays = @json($operationalDays);
            const holidaysRaw = @json($holidays);
            const holidays = [];
            holidaysRaw.forEach(h => {
                if (h.start_date && h.end_date) {
                    let current = new Date(h.start_date + 'T00:00:00');
                    const end = new Date(h.end_date + 'T00:00:00');
                    while (current <= end) {
                        holidays.push(current.toISOString().split('T')[0]);
                        current.setDate(current.getDate() + 1);
                    }
                } else if (h.date) {
                    holidays.push(h.date);
                }
            });

            const isClosed = function(date) {
                if (!operationalDays.includes(date.getDay().toString())) return true;
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const dayStr = String(date.getDate()).padStart(2, '0');
                const dateStr = `${year}-${month}-${dayStr}`;
                if (holidays.includes(dateStr)) return true;
                return false;
            };

            // ----- Date lock via localStorage (shared with show.blade.php) -----
            const urlStart = @json(request('start_date'));
            const urlEnd = @json(request('end_date'));
            const urlPickup = @json(request('pickup_time'));
            const urlReturn = @json(request('return_time'));

            const savedDates = localStorage.getItem('gearent_rental_dates');
            const savedPickup = localStorage.getItem('gearent_pickup_time');
            const savedReturn = localStorage.getItem('gearent_return_time');

            let defaultDates = null;
            if (urlStart && urlEnd) {
                defaultDates = [urlStart, urlEnd];
                const dateStr = `${urlStart} to ${urlEnd}`;
                if (localStorage.getItem('gearent_rental_dates') !== dateStr) {
                    localStorage.setItem('gearent_rental_dates', dateStr);
                }
            } else if (savedDates) {
                const [s, e] = savedDates.split(' to ');
                if (s && e) {
                    defaultDates = [s, e];
                    startDateInput.value = s;
                    endDateInput.value = e;
                }
            }

            if (!urlPickup && savedPickup) pickupTimeInput.value = savedPickup;
            if (!urlReturn && savedReturn) returnTimeInput.value = savedReturn;

            // Initialize Flatpickr
            const fp = flatpickr("#date_range", {
                mode: "range",
                dateFormat: "Y-m-d",
                minDate: "today",
                defaultDate: defaultDates,
                onDayCreate: function(dObj, dStr, fp, dayElem) {
                    if (isClosed(dayElem.dateObj)) {
                        dayElem.classList.add('closed-day');
                    }
                },
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length > 0 && isClosed(selectedDates[0])) {
                         Swal.fire({ icon: 'error', title: 'Tanggal Tidak Tersedia', text: 'Pengambilan tidak dapat dilakukan pada hari libur operasional.', confirmButtonColor: '#ef4444' });
                         instance.clear();
                         return;
                    }

                    if (selectedDates.length === 2) {
                        if (isClosed(selectedDates[1])) {
                             Swal.fire({ icon: 'error', title: 'Tanggal Tidak Tersedia', text: 'Pengembalian tidak dapat dilakukan pada hari libur operasional.', confirmButtonColor: '#ef4444' });
                             instance.clear();
                             return;
                        }

                        const s = instance.formatDate(selectedDates[0], "Y-m-d");
                        const e = instance.formatDate(selectedDates[1], "Y-m-d");
                        startDateInput.value = s;
                        endDateInput.value = e;
                        localStorage.setItem('gearent_rental_dates', `${s} to ${e}`);
                    }
                }
            });

            pickupTimeInput.addEventListener('change', () => {
                localStorage.setItem('gearent_pickup_time', pickupTimeInput.value);
            });
            returnTimeInput.addEventListener('change', () => {
                localStorage.setItem('gearent_return_time', returnTimeInput.value);
            });

            // Cross-tab sync
            window.addEventListener('storage', function(e) {
                if (e.key === 'gearent_rental_dates' && e.newValue) {
                    const [s, ev] = e.newValue.split(' to ');
                    if (s && ev) fp.setDate([s, ev], false);
                }
                if (e.key === 'gearent_pickup_time' && e.newValue) pickupTimeInput.value = e.newValue;
                if (e.key === 'gearent_return_time' && e.newValue) returnTimeInput.value = e.newValue;
            });

            // ----- Card Add to Cart -----
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
            const cartAddUrl = @json(route('cart.add'));

            function getRentalDates() {
                const d = localStorage.getItem('gearent_rental_dates');
                if (!d) return null;
                const [s, e] = d.split(' to ');
                if (!s || !e) return null;
                const pt = localStorage.getItem('gearent_pickup_time') || '09:00';
                const rt = localStorage.getItem('gearent_return_time') || '09:00';
                return {
                    start_date: `${s} ${pt}:00`,
                    end_date: `${e} ${rt}:00`,
                    pickup_time: pt,
                    return_time: rt,
                };
            }

            async function postAddToCart(payload, confirmChanges = false) {
                if (confirmChanges) payload.confirm_changes = 1;
                const form = new FormData();
                Object.entries(payload).forEach(([k, v]) => form.append(k, v));
                const res = await fetch(cartAddUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: form,
                });
                let body = {};
                try { body = await res.json(); } catch (e) {}
                return { status: res.status, body };
            }

            async function handleAdd(payload) {
                const { status, body } = await postAddToCart(payload);
                if (status === 200) {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                                title: body.message || 'Ditambahkan ke keranjang',
                                timer: 1800, showConfirmButton: false });
                } else if (status === 409) {
                    const list = (body.conflicts || []).map(i => `<li>• ${i}</li>`).join('');
                    const r = await Swal.fire({
                        title: 'Perubahan Tanggal Sewa',
                        html: `<p class="mb-3">Item berikut akan dihapus dari keranjang karena tidak tersedia di tanggal baru:</p>
                               <ul class="text-left bg-gray-100 p-3 rounded text-sm text-red-600">${list}</ul>
                               <p class="mt-3">Lanjutkan?</p>`,
                        icon: 'warning', showCancelButton: true,
                        confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Ya, Lanjutkan', cancelButtonText: 'Batal',
                    });
                    if (r.isConfirmed) {
                        const retry = await postAddToCart(payload, true);
                        if (retry.status === 200) {
                            Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                                        title: retry.body.message || 'Updated', timer: 1800, showConfirmButton: false });
                        } else {
                            Swal.fire('Error', retry.body.message || 'Gagal menambah ke keranjang', 'error');
                        }
                    }
                } else {
                    Swal.fire('Oops', body.message || 'Terjadi kesalahan', 'error');
                }
            }

            document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
                if (btn.disabled) return;
                btn.addEventListener('click', () => {
                    const dates = getRentalDates();
                    if (!dates) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Pilih tanggal sewa dulu',
                            text: 'Tentukan rentang tanggal pada filter di sebelah kiri.',
                            confirmButtonColor: '#3b82f6'
                        }).then(() => {
                            const dr = document.getElementById('date_range');
                            if (dr) {
                                dr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                dr.focus();
                            }
                        });
                        return;
                    }
                    const productId = btn.dataset.productId;
                    const hasVar = btn.dataset.hasVariations === '1';
                    const basePayload = { product_id: productId, quantity: 1, ...dates };

                    if (!hasVar) {
                        handleAdd(basePayload);
                        return;
                    }

                    const variations = JSON.parse(btn.dataset.variations || '[]');
                    const tiles = variations.map(v => `
                        <button type="button" data-vid="${v.id}" ${v.available_count <= 0 ? 'disabled' : ''}
                            class="variation-tile border-2 rounded-lg p-3 text-sm text-center transition ${v.available_count > 0
                                ? 'border-gray-200 hover:border-primary-400 bg-white cursor-pointer'
                                : 'border-gray-100 opacity-50 cursor-not-allowed bg-gray-50'}">
                            <div class="font-semibold text-gray-900">${v.name}</div>
                            <div class="text-xs text-gray-500 mt-1">Rp ${new Intl.NumberFormat('id-ID').format(v.daily_rate)}</div>
                            <div class="text-xs mt-1 ${v.available_count > 0 ? 'text-green-600' : 'text-red-500'}">
                                ${v.available_count > 0 ? v.available_count + ' unit' : 'Habis'}
                            </div>
                        </button>`).join('');
                    Swal.fire({
                        title: `Pilih Variasi — ${btn.dataset.productName}`,
                        html: `<div class="grid grid-cols-2 gap-2">${tiles}</div>`,
                        showConfirmButton: false,
                        showCloseButton: true,
                        didOpen: () => {
                            document.querySelectorAll('.variation-tile').forEach(t => t.addEventListener('click', () => {
                                if (t.disabled) return;
                                const vid = t.dataset.vid;
                                Swal.close();
                                handleAdd({ ...basePayload, variation_id: vid });
                            }));
                        },
                    });
                });
            });
        });
    </script>
@endpush
