@extends('layouts.app')

@section('title', 'Доступ запрещён')

@section('content')
    <h1>403 — Доступ запрещён</h1>
    <p>{{ $message }}</p>
    <a href="{{ route('articles.index') }}">Перейти к статьям</a>
@endsection
