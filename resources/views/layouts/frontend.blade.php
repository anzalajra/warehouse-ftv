<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    {{-- PWA Meta Tags --}}
    <meta name="theme-color" content="#0ea5e9">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ \App\Models\Setting::get('site_name', 'Gearent') }}">
    <meta name="application-name" content="{{ \App\Models\Setting::get('site_name', 'Gearent') }}">
    <meta name="msapplication-TileColor" content="#0ea5e9">
    <meta name="msapplication-tap-highlight" content="no">
    <meta name="format-detection" content="telephone=no">
    
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    <title>{{ config('app.name', 'Gearent') }} - @yield('title', 'Rental Equipment')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    @if(isset($themeCssVariables))
        <style>
            :root {
                {!! $themeCssVariables !!}
            }
        </style>
    @endif
</head>
<body class="font-sans antialiased bg-gray-50">
    @php
        try {
            $__activeBanners = \App\Models\Announcement::activeBanners();
            $__activePopup = \App\Models\Announcement::activePopup();
        } catch (\Throwable $e) {
            $__activeBanners = collect();
            $__activePopup = null;
        }
    @endphp

    @foreach($__activeBanners as $__banner)
        <div class="text-sm" style="background-color: {{ $__banner->banner_bg_color ?? '#0ea5e9' }}; color: {{ $__banner->banner_text_color ?? '#ffffff' }};">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-center gap-3 text-center">
                <span>{{ $__banner->content }}</span>
                @if($__banner->link_url)
                    <a href="{{ $__banner->link_url }}" class="underline font-semibold whitespace-nowrap hover:opacity-80">
                        {{ $__banner->link_label ?: 'Selengkapnya' }}
                    </a>
                @endif
            </div>
        </div>
    @endforeach

    @if($__activePopup)
        <div id="announcement-popup-{{ $__activePopup->id }}"
             class="fixed inset-0 z-[110] hidden items-center justify-center bg-black/60 p-4"
             data-announcement-id="{{ $__activePopup->id }}">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full overflow-hidden relative">
                <button type="button"
                        onclick="document.getElementById('announcement-popup-{{ $__activePopup->id }}').remove(); try { sessionStorage.setItem('announcement_dismissed_{{ $__activePopup->id }}', '1'); } catch(e) {}"
                        class="absolute top-2 right-2 z-10 bg-white/90 hover:bg-white rounded-full w-8 h-8 flex items-center justify-center text-gray-700 shadow">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                @if($__activePopup->image_path)
                    @if($__activePopup->link_url)
                        <a href="{{ $__activePopup->link_url }}">
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($__activePopup->image_path) }}"
                                 alt="{{ $__activePopup->title }}"
                                 class="w-full h-auto block">
                        </a>
                    @else
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($__activePopup->image_path) }}"
                             alt="{{ $__activePopup->title }}"
                             class="w-full h-auto block">
                    @endif
                @endif
                @if($__activePopup->link_url && $__activePopup->link_label)
                    <div class="px-6 py-4 bg-gray-50 flex justify-end">
                        <a href="{{ $__activePopup->link_url }}" class="px-5 py-2 bg-primary-600 text-white rounded font-medium hover:bg-primary-700">
                            {{ $__activePopup->link_label }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
        <script>
            (function() {
                try {
                    if (sessionStorage.getItem('announcement_dismissed_{{ $__activePopup->id }}')) return;
                } catch(e) {}
                var el = document.getElementById('announcement-popup-{{ $__activePopup->id }}');
                if (el) { el.classList.remove('hidden'); el.classList.add('flex'); }
            })();
        </script>
    @endif

    @if(session()->has('impersonator_id'))
        <div class="bg-amber-500 text-white text-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between gap-3">
                <span>
                    <strong>Mode Impersonate:</strong>
                    Anda sedang masuk sebagai <strong>{{ auth('customer')->user()?->name }}</strong>.
                    Semua aksi akan tercatat atas nama user ini.
                </span>
                <a href="{{ route('impersonate.stop') }}" class="bg-white text-amber-700 font-semibold px-3 py-1 rounded hover:bg-amber-50 whitespace-nowrap">
                    Berhenti Impersonate
                </a>
            </div>
        </div>
    @endif

    <!-- Navigation -->
    <nav class="bg-white shadow-sm sticky top-0 z-50" x-data="{ mobileMenuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <!-- Mobile menu button -->
                    <div class="-ml-2 mr-2 flex items-center sm:hidden">
                        <button @click="mobileMenuOpen = !mobileMenuOpen" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500" aria-controls="mobile-menu" aria-expanded="false">
                            <span class="sr-only">Open main menu</span>
                            <!-- Icon when menu is closed. -->
                            <svg x-show="!mobileMenuOpen" class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                            <!-- Icon when menu is open. -->
                            <svg x-show="mobileMenuOpen" class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" style="display: none;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <a href="{{ url('/') }}" class="flex items-center gap-2 text-xl font-bold text-primary-600">
                        @if(\App\Models\Setting::get('site_logo'))
                            <img src="{{ \Illuminate\Support\Facades\Storage::url(\App\Models\Setting::get('site_logo')) }}" alt="{{ \App\Models\Setting::get('site_name', 'Gearent') }}" class="h-10 w-auto">
                        @endif
                    </a>
                    <div class="hidden sm:ml-10 sm:flex sm:space-x-8">
                        @php
                            $menuItems = [];
                            try {
                                $navigationModel = \LaraZeus\Sky\SkyPlugin::get()->getModel('Navigation');
                                $headerHandle = \App\Models\Setting::get('header_navigation_handle', 'main-menu');
                                $mainMenu = $navigationModel::fromHandle($headerHandle) ?? $navigationModel::fromHandle('navigation');
                                
                                if ($mainMenu && !empty($mainMenu->items)) {
                                    foreach($mainMenu->items as $item) {
                                        $url = '#';
                                        $target = $item['data']['target'] ?? '_self';
                                        
                                        if ($item['type'] === 'page_link' && isset($item['data']['page_id'])) {
                                            $postModel = \LaraZeus\Sky\SkyPlugin::get()->getModel('Post');
                                            $page = $postModel::find($item['data']['page_id']);
                                            $url = $page ? route('page', $page->slug) : '#';
                                        } elseif ($item['type'] === 'post_link' && isset($item['data']['post_id'])) {
                                            $postModel = \LaraZeus\Sky\SkyPlugin::get()->getModel('Post');
                                            $post = $postModel::find($item['data']['post_id']);
                                            $url = $post ? route('post', $post->slug) : '#';
                                        } elseif ($item['type'] === 'external-link' || $item['type'] === 'url') {
                                            $url = $item['data']['url'] ?? '#';
                                        }

                                        $menuItems[] = [
                                            'label' => $item['label'],
                                            'url' => $url,
                                            'target' => $target,
                                        ];
                                    }
                                } else {
                                    $menuItems = [
                                        ['label' => 'Home', 'url' => url('/'), 'target' => '_self'],
                                        ['label' => 'Catalog', 'url' => route('catalog.index'), 'target' => '_self'],
                                    ];
                                }
                            } catch (\Exception $e) {
                                $menuItems = [
                                    ['label' => 'Home', 'url' => url('/'), 'target' => '_self'],
                                    ['label' => 'Catalog', 'url' => route('catalog.index'), 'target' => '_self'],
                                ];
                            }
                        @endphp

                        @foreach($menuItems as $item)
                            <a href="{{ $item['url'] }}"
                               target="{{ $item['target'] }}"
                               class="text-gray-900 hover:text-primary-600 px-3 py-2 text-sm font-medium {{ request()->url() == $item['url'] ? 'text-primary-600' : '' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                        <a href="{{ route('frontend.schedule') }}"
                           class="text-gray-900 hover:text-primary-600 px-3 py-2 text-sm font-medium {{ request()->routeIs('frontend.schedule') ? 'text-primary-600' : '' }}">
                            Schedule
                        </a>
                        <a href="{{ route('computers.index') }}"
                           class="text-gray-900 hover:text-primary-600 px-3 py-2 text-sm font-medium {{ request()->routeIs('computers.*') ? 'text-primary-600' : '' }}">
                            Computer Booking
                        </a>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    @auth('customer')
                        <a href="{{ route('cart.index') }}" class="relative text-gray-600 hover:text-primary-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            @php $cartCount = auth('customer')->user()->carts()->count(); @endphp
                            @if($cartCount > 0)
                                <span class="absolute -top-2 -right-2 bg-primary-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">{{ $cartCount }}</span>
                            @endif
                        </a>

                        <!-- Notifications -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="relative text-gray-600 hover:text-primary-600 focus:outline-none pt-1">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                </svg>
                                @php
                                    $unreadCount = auth('customer')->user()->unreadNotifications->count();
                                @endphp
                                @if($unreadCount > 0)
                                    <span class="absolute -top-2 -right-2 bg-red-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">{{ $unreadCount }}</span>
                                @endif
                            </button>

                            <div x-show="open" 
                                 @click.away="open = false"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute right-0 mt-2 w-80 bg-white rounded-md shadow-lg py-1 z-50 overflow-hidden ring-1 ring-black ring-opacity-5"
                                 style="display: none;">
                                
                                <div class="px-4 py-2 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                                    <h3 class="text-sm font-semibold text-gray-700">Notifications</h3>
                                    @if($unreadCount > 0)
                                        <form action="{{ route('customer.notifications.read-all') }}" method="POST">
                                            @csrf
                                            <button type="submit" class="text-xs text-primary-600 hover:text-primary-800 font-medium">Mark all read</button>
                                        </form>
                                    @endif
                                </div>

                                <div class="max-h-96 overflow-y-auto">
                                    @forelse(auth('customer')->user()->notifications->take(10) as $notification)
                                        <a href="{{ route('customer.notifications.read', $notification->id) }}" class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-0 {{ $notification->read_at ? 'opacity-75' : 'bg-primary-50' }}">
                                            <div class="flex items-start gap-3">
                                                <div class="flex-shrink-0 mt-1">
                                                    @if(isset($notification->data['icon']))
                                                        <x-dynamic-component :component="$notification->data['icon']" class="w-5 h-5 text-gray-500" />
                                                    @else
                                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                    @endif
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-900 truncate">{{ $notification->data['title'] ?? 'Notification' }}</p>
                                                    <p class="text-xs text-gray-500 line-clamp-2">{{ $notification->data['body'] ?? '' }}</p>
                                                    <p class="mt-1 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                                                </div>
                                                @if(!$notification->read_at)
                                                    <div class="flex-shrink-0 self-center">
                                                        <span class="block h-2 w-2 rounded-full bg-primary-600 ring-2 ring-white"></span>
                                                    </div>
                                                @endif
                                            </div>
                                        </a>
                                    @empty
                                        <div class="px-4 py-6 text-center text-sm text-gray-500 flex flex-col items-center">
                                            <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                            </svg>
                                            <p>No notifications</p>
                                        </div>
                                    @endforelse
                                </div>
                                
                                @if(auth('customer')->user()->notifications->count() > 10)
                                    <div class="bg-gray-50 px-4 py-2 text-center border-t border-gray-100">
                                        <a href="#" class="text-xs font-medium text-primary-600 hover:text-primary-500">View all notifications</a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="relative hidden sm:block" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center gap-2 focus:outline-none group">
                                <div class="h-8 w-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-600 font-bold text-sm ring-2 ring-transparent group-hover:ring-primary-200 transition-all">
                                    {{ substr(auth('customer')->user()->name, 0, 1) }}
                                </div>
                                <div class="text-sm font-medium text-gray-700 group-hover:text-primary-600 transition-colors">
                                    {{ auth('customer')->user()->name }}
                                </div>
                                <svg class="w-4 h-4 text-gray-400 group-hover:text-primary-600 transition-colors" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <div x-show="open" 
                                 @click.away="open = false"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute right-0 mt-2 w-60 bg-white rounded-xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] py-2 z-50 ring-1 ring-black ring-opacity-5 origin-top-right"
                                 style="display: none;">
                                 
                                <div class="px-4 py-3 border-b border-gray-100 mb-1">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-full bg-primary-50 flex items-center justify-center text-primary-600 font-bold text-lg">
                                            {{ substr(auth('customer')->user()->name, 0, 1) }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 truncate">{{ auth('customer')->user()->name }}</p>
                                            <p class="text-xs text-gray-500 truncate">{{ auth('customer')->user()->email }}</p>
                                        </div>
                                    </div>
                                </div>

                                <a href="{{ route('customer.dashboard') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary-600 transition-colors">Dashboard</a>
                                <a href="{{ route('customer.rentals') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary-600 transition-colors">My Rentals</a>
                                <a href="{{ route('customer.computer-bookings.index') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary-600 transition-colors">My Computer Bookings</a>
                                <a href="{{ route('customer.profile') }}" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary-600 transition-colors">Profile</a>
                                
                                <div class="border-t border-gray-100 my-1"></div>
                                
                                <form method="POST" action="{{ route('customer.logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">Logout</button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('customer.login') }}" class="text-gray-600 hover:text-primary-600 text-sm font-medium">Login</a>
                        <a href="{{ route('customer.register') }}" class="bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-700">Register</a>
                    @endauth
                </div>
            </div>
        </div>

        <!-- Mobile menu, show/hide based on menu state. -->
        <div x-show="mobileMenuOpen" class="sm:hidden" id="mobile-menu" style="display: none;">
            <div class="space-y-1 pt-2 pb-3">
                @foreach($menuItems as $item)
                    <a href="{{ $item['url'] }}"
                       target="{{ $item['target'] }}"
                       class="block border-l-4 py-2 pl-3 pr-4 text-base font-medium {{ request()->url() == $item['url'] ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-transparent text-gray-500 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-700' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
                <a href="{{ route('frontend.schedule') }}"
                   class="block border-l-4 py-2 pl-3 pr-4 text-base font-medium {{ request()->routeIs('frontend.schedule') ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-transparent text-gray-500 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-700' }}">
                    Schedule
                </a>
                <a href="{{ route('computers.index') }}"
                   class="block border-l-4 py-2 pl-3 pr-4 text-base font-medium {{ request()->routeIs('computers.*') ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-transparent text-gray-500 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-700' }}">
                    Computer Booking
                </a>
            </div>
            <div class="border-t border-gray-200 pt-4 pb-3">
                @auth('customer')
                    <div class="flex items-center px-4">
                        <div class="flex-shrink-0">
                            <div class="h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-600 font-bold text-lg">
                                {{ substr(auth('customer')->user()->name, 0, 1) }}
                            </div>
                        </div>
                        <div class="ml-3">
                            <div class="text-base font-medium text-gray-800">{{ auth('customer')->user()->name }}</div>
                            <div class="text-sm font-medium text-gray-500">{{ auth('customer')->user()->email }}</div>
                        </div>
                    </div>
                    <div class="mt-3 space-y-1">
                        <a href="{{ route('customer.dashboard') }}" class="block px-4 py-2 text-base font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800">Dashboard</a>
                        <a href="{{ route('customer.rentals') }}" class="block px-4 py-2 text-base font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800">My Rentals</a>
                        <a href="{{ route('customer.computer-bookings.index') }}" class="block px-4 py-2 text-base font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800">My Computer Bookings</a>
                        <a href="{{ route('customer.profile') }}" class="block px-4 py-2 text-base font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800">Profile</a>
                        <form method="POST" action="{{ route('customer.logout') }}">
                            @csrf
                            <button type="submit" class="block w-full text-left px-4 py-2 text-base font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800">Logout</button>
                        </form>
                    </div>
                @else
                    <div class="mt-3 space-y-1">
                        <a href="{{ route('customer.login') }}" class="block px-4 py-2 text-base font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800">Login</a>
                        <a href="{{ route('customer.register') }}" class="block px-4 py-2 text-base font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800">Register</a>
                    </div>
                @endauth
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                {{ session('error') }}
            </div>
        </div>
    @endif

    @auth('customer')
        @php
            $__blockedUser = auth('customer')->user();
            $__blockedAtTs = $__blockedUser && $__blockedUser->blocked_at ? $__blockedUser->blocked_at->timestamp : null;
            $__seenAtTs = session('blocked_popup_seen_at');
            $__showBlockedPopup = $__blockedUser && $__blockedUser->isBlocked() && $__blockedAtTs && (!$__seenAtTs || $__seenAtTs < $__blockedAtTs);
        @endphp
        @if($__showBlockedPopup)
            <div id="blocked-account-popup" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full overflow-hidden">
                    <div class="bg-red-600 px-6 py-4 flex items-center gap-3">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728A9 9 0 015.636 5.636"/>
                        </svg>
                        <h2 class="text-lg font-bold text-white">Akun Anda Diblokir</h2>
                    </div>
                    <div class="px-6 py-5">
                        <p class="text-gray-700 mb-3">Maaf, akun Anda telah diblokir oleh admin. Anda tidak dapat melakukan rental untuk sementara waktu.</p>
                        @if($__blockedUser->blocked_reason)
                            <div class="bg-red-50 border border-red-200 rounded p-3 text-sm text-red-800">
                                <span class="font-semibold">Alasan:</span><br>
                                {{ $__blockedUser->blocked_reason }}
                            </div>
                        @endif
                        <p class="text-xs text-gray-500 mt-3">Silakan hubungi admin untuk informasi lebih lanjut.</p>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 flex justify-end">
                        <form method="POST" action="{{ route('customer.blocked-popup.acknowledge') }}">
                            @csrf
                            <button type="submit" class="px-5 py-2 bg-red-600 text-white rounded font-medium hover:bg-red-700">
                                Saya Mengerti
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        @php
            $__missingCustomFields = ($__blockedUser && !$__showBlockedPopup)
                ? $__blockedUser->getMissingRequiredCustomFields()
                : [];
            $__onProfilePage = request()->routeIs('customer.profile');
        @endphp
        @if(!empty($__missingCustomFields) && !$__onProfilePage && !$__showBlockedPopup)
            <div id="missing-custom-fields-popup" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full overflow-hidden">
                    <div class="bg-yellow-500 px-6 py-4 flex items-center gap-3">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        <h2 class="text-lg font-bold text-white">Lengkapi Profil Anda</h2>
                    </div>
                    <div class="px-6 py-5">
                        <p class="text-gray-700 mb-3">Beberapa informasi tambahan wajib diisi untuk kategori akun Anda
                            (<span class="font-semibold">{{ $__blockedUser->category->name ?? '-' }}</span>):</p>
                        <ul class="list-disc list-inside text-sm text-gray-800 bg-yellow-50 border border-yellow-200 rounded p-3 space-y-1">
                            @foreach($__missingCustomFields as $__mf)
                                <li>{{ $__mf['label'] ?? $__mf['name'] }}</li>
                            @endforeach
                        </ul>
                        <p class="text-xs text-gray-500 mt-3">Silakan lengkapi di halaman profile Anda untuk dapat melanjutkan transaksi.</p>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 flex justify-end">
                        <a href="{{ route('customer.profile') }}" class="px-5 py-2 bg-yellow-500 text-white rounded font-medium hover:bg-yellow-600">
                            Lengkapi Sekarang
                        </a>
                    </div>
                </div>
            </div>
        @endif
    @endauth

    <!-- Main Content -->
    <main>
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">{{ \App\Models\Setting::get('site_name', 'Gearent') }}</h3>
                    <p class="text-gray-400">{{ \App\Models\Setting::get('site_tagline', 'Your trusted equipment rental partner.') }}</p>
                    @if(\App\Models\Setting::get('site_address'))
                        <p class="text-gray-400 mt-4 text-sm font-light leading-relaxed whitespace-pre-line">{{ \App\Models\Setting::get('site_address') }}</p>
                    @endif
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-gray-400">
                        @php
                            $footerItems = [];
                            try {
                                $navigationModel = \LaraZeus\Sky\SkyPlugin::get()->getModel('Navigation');
                                $footerHandle = \App\Models\Setting::get('footer_navigation_handle', 'footer-menu');
                                $footerMenu = $navigationModel::fromHandle($footerHandle);
                                
                                if ($footerMenu && !empty($footerMenu->items)) {
                                     foreach($footerMenu->items as $item) {
                                        $url = '#';
                                        $target = $item['data']['target'] ?? '_self';
                                        
                                        if ($item['type'] === 'page_link' && isset($item['data']['page_id'])) {
                                            $postModel = \LaraZeus\Sky\SkyPlugin::get()->getModel('Post');
                                            $page = $postModel::find($item['data']['page_id']);
                                            $url = $page ? route('page', $page->slug) : '#';
                                        } elseif ($item['type'] === 'post_link' && isset($item['data']['post_id'])) {
                                            $postModel = \LaraZeus\Sky\SkyPlugin::get()->getModel('Post');
                                            $post = $postModel::find($item['data']['post_id']);
                                            $url = $post ? route('post', $post->slug) : '#';
                                        } elseif ($item['type'] === 'external-link' || $item['type'] === 'url') {
                                            $url = $item['data']['url'] ?? '#';
                                        }

                                        $footerItems[] = [
                                            'label' => $item['label'],
                                            'url' => $url,
                                            'target' => $target,
                                        ];
                                     }
                                } else {
                                     // Fallback to old system if no Sky menu found
                                     $oldFooterMenu = \App\Models\NavigationMenu::where('handle', 'footer-menu')->first();
                                     $footerItems = $oldFooterMenu ? $oldFooterMenu->items : [
                                        ['label' => 'Home', 'url' => url('/'), 'target' => '_self'],
                                        ['label' => 'Catalog', 'url' => route('catalog.index'), 'target' => '_self'],
                                     ];
                                }
                            } catch (\Exception $e) {
                                 $footerItems = [
                                    ['label' => 'Home', 'url' => url('/'), 'target' => '_self'],
                                    ['label' => 'Catalog', 'url' => route('catalog.index'), 'target' => '_self'],
                                 ];
                            }
                        @endphp
                        
                        @foreach($footerItems as $item)
                            <li>
                                <a href="{{ $item['url'] }}" 
                                   target="{{ $item['target'] ?? '_self' }}"
                                   class="hover:text-white">
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Contact</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li>Phone: {{ \App\Models\Setting::get('site_phone', '021-1234567') }}</li>
                        <li>Email: {{ \App\Models\Setting::get('site_email', 'info@gearent.com') }}</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Follow Us</h4>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white">Instagram</a>
                        <a href="#" class="text-gray-400 hover:text-white">Facebook</a>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                @if(\App\Models\Setting::get('site_copyright'))
                    {{ \App\Models\Setting::get('site_copyright') }}
                @else
                    &copy; {{ date('Y') }} {{ \App\Models\Setting::get('site_name', 'Gearent') }}. All rights reserved.
                @endif
                <div class="mt-2 text-xs text-gray-500">
                    v{{ config('app.version') }}
                </div>
            </div>
        </div>
    </footer>

    @stack('scripts')
    
    {{-- PWA Service Worker Registration --}}
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(registration => {
                        console.log('SW registered:', registration.scope);
                    })
                    .catch(error => {
                        console.log('SW registration failed:', error);
                    });
            });
        }
        
        // Install prompt handling
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Show install button if needed (optional UI)
            const installBanner = document.getElementById('pwa-install-banner');
            if (installBanner) {
                installBanner.classList.remove('hidden');
            }
        });
        
        // Handle install click
        function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted PWA install');
                    }
                    deferredPrompt = null;
                });
            }
        }
    </script>
</body>
</html>