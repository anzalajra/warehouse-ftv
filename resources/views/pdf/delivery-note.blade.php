@extends('pdf.layout')

@section('title', $delivery->delivery_number)

@section('content')
    @include('pdf.partials.delivery-note-body', ['delivery' => $delivery])
@endsection
