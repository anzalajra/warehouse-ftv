@extends('layouts.frontend')

@section('title', $room->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-4">
        <a href="{{ route('computers.index') }}" class="text-primary-600 hover:underline text-sm">&larr; Kembali ke daftar ruangan</a>
    </div>
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">{{ $room->name }}</h1>
        @if($room->description)
            <p class="mt-2 text-gray-600">{{ $room->description }}</p>
        @endif
    </div>

    @if($computers->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            Belum ada komputer di ruangan ini.
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($computers as $computer)
                <a href="{{ route('computers.show', $computer) }}" class="block bg-white rounded-lg shadow hover:shadow-lg transition overflow-hidden">
                    <div class="aspect-video bg-gray-100">
                        @if($computer->image_path)
                            <img src="{{ asset('storage/'.$computer->image_path) }}" alt="{{ $computer->name }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25"/></svg>
                            </div>
                        @endif
                    </div>
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-2 gap-2">
                            <h2 class="font-semibold text-gray-900 truncate">{{ $computer->name }}</h2>
                            @if($computer->status !== \App\Models\Computer::STATUS_AVAILABLE)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Maintenance</span>
                            @elseif($computer->is_online)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1 animate-pulse"></span>Online
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">Offline</span>
                            @endif
                        </div>
                        @php
                            $currentUser = $computer->status === \App\Models\Computer::STATUS_AVAILABLE ? $computer->currentBookingUser() : null;
                        @endphp
                        @if($currentUser)
                            <p class="text-xs text-gray-600">Sedang dipakai: <span class="font-medium">{{ $currentUser->name }}</span></p>
                        @endif
                        @if($computer->brand)
                            <p class="text-sm text-gray-500">{{ $computer->brand }}</p>
                        @endif
                        @if(! empty($computer->specs))
                            <ul class="mt-3 text-xs text-gray-600 space-y-1">
                                @foreach(array_slice($computer->specs, 0, 3, true) as $key => $value)
                                    <li><span class="font-medium">{{ $key }}:</span> {{ $value }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
