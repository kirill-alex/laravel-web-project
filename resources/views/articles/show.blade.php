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

    <section id="comments">
        <h2>Комментарии ({{ $comments->total() }})</h2>
        @if(session('status'))
            <p role="status">{{ session('status') }}</p>
        @endif

        @auth
            <form action="{{ route('articles.comments.store', $article) }}" method="POST">
                @csrf
                <div>
                    <label for="comment-body">Ваш комментарий:</label>
                    <textarea id="comment-body" name="body" required maxlength="5000">{{ old('body') }}</textarea>
                    @error('body') <div style="color:red">{{ $message }}</div> @enderror
                </div>
                <button type="submit">Добавить комментарий</button>
            </form>
        @else
            <p><a href="{{ route('login') }}">Войдите</a>, чтобы оставить комментарий.</p>
        @endauth

        @forelse($comments as $comment)
            <article>
                <p><strong>{{ $comment->user->name }}</strong> · {{ $comment->created_at->format('d.m.Y H:i') }}</p>
                <p style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $comment->body }}</p>
                @if(auth()->id() === $comment->user_id)
                    <a href="{{ route('articles.comments.edit', [$article, $comment]) }}">Редактировать комментарий</a>
                    <form action="{{ route('articles.comments.destroy', [$article, $comment]) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" onclick="return confirm('Удалить комментарий?')">Удалить комментарий</button>
                    </form>
                @endif
            </article>
        @empty
            <p>Комментариев пока нет.</p>
        @endforelse

        {{ $comments->fragment('comments')->links() }}
    </section>
@endsection
