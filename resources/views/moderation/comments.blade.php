@extends('layouts.app')

@section('title', 'Модерация комментариев')

@section('content')
    <h1>Модерация комментариев</h1>
    <p>Ожидают проверки: {{ $comments->total() }}</p>
    @if(session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    @forelse($comments as $comment)
        <article>
            <h2><a href="{{ route('articles.show', $comment->article) }}">{{ $comment->article->title }}</a></h2>
            <p><strong>{{ $comment->user->name }}</strong> · {{ $comment->created_at->format('d.m.Y H:i') }}</p>
            <p style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $comment->body }}</p>
            <form action="{{ route('moderation.comments.approve', $comment) }}" method="POST">
                @csrf
                @method('PATCH')
                <button type="submit">Принять</button>
            </form>
            <form action="{{ route('moderation.comments.reject', $comment) }}" method="POST">
                @csrf
                @method('PATCH')
                <button type="submit">Отклонить</button>
            </form>
        </article>
    @empty
        <p>Нет комментариев, ожидающих модерации.</p>
    @endforelse

    {{ $comments->links() }}
@endsection
