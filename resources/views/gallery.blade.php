@extends('layouts.app')

@section('title', 'Галерея')

@section('content')
    <h1>{{ $item['title'] }}</h1>
    <img src="{{ asset($item['full_image']) }}" alt="{{ $item['title'] }}">
    <p>{{ $item['description'] }}</p>
    <a href="{{ url('/') }}">← Назад на главную</a>
@endsection