@extends('pdf.layout')

@section('title', 'Surat Jalan')

@section('content')
    @foreach($deliveries as $index => $delivery)
        @include('pdf.partials.delivery-note-body', ['delivery' => $delivery])

        @if(! $loop->last)
            <div style="page-break-after: always;"></div>
        @endif
    @endforeach
@endsection
