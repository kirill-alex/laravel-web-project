@extends('layouts.app')

@section('title', 'Галерея')

@section('content')
    <h1>{{ $item['title'] }}</h1>
    <img class="gallery-image" src="{{ asset($item['full_image']) }}" width="600" height="360" alt="{{ $item['title'] }}">
    <p>{{ $item['description'] }}</p>
    <a href="{{ url('/') }}">← Назад на главную</a>
@endsection
