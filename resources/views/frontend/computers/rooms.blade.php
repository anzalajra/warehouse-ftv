@extends('layouts.frontend')

@section('title', 'Lab Komputer')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Lab Komputer</h1>
        <p class="mt-2 text-gray-600">Pilih ruangan terlebih dahulu untuk melihat komputer yang tersedia.</p>
    </div>

    @if($rooms->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            Belum ada ruangan yang tersedia.
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($rooms as $room)
                <a href="{{ route('computers.rooms.show', $room) }}" class="block bg-white rounded-lg shadow hover:shadow-lg transition overflow-hidden">
                    <div class="aspect-video bg-gray-100">
                        @if($room->image_path)
                            <img src="{{ asset('storage/'.$room->image_path) }}" alt="{{ $room->name }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg>
                            </div>
                        @endif
                    </div>
                    <div class="p-4">
                        <h2 class="font-semibold text-gray-900">{{ $room->name }}</h2>
                        @if($room->description)
                            <p class="mt-1 text-sm text-gray-500 line-clamp-2">{{ $room->description }}</p>
                        @endif
                        @php
                            $totalComputers = $room->computers->count();
                            $onlineCount = $room->computers->filter(fn ($c) => $c->is_online)->count();
                        @endphp
                        <p class="mt-2 text-xs text-gray-500">{{ $totalComputers }} komputer @if($totalComputers > 0) — <span class="font-medium text-green-700">{{ $onlineCount }}</span> online @endif</p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
