@extends('layouts.app')

@section('title', $article->title)

@section('content')
    <h1>{{ $article->title }}</h1>
    @if($article->full_image)
        <img src="{{ $article->full_image }}" width="400">
    @endif
    <p>{{ $article->description }}</p>

    @auth
    <a href="{{ url('/articles/' . $article->id . '/edit') }}">Редактировать</a>

    <form action="{{ url('/articles/' . $article->id) }}" method="POST" style="display:inline">
        @csrf
        @method('DELETE')
        <button type="submit" onclick="return confirm('Удалить статью?')">Удалить</button>
    </form>
    @endauth

    <a href="{{ url('/articles') }}">← Назад к списку</a>
@endsection
