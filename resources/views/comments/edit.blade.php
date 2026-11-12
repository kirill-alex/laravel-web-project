@extends('layouts.app')

@section('title', 'Редактировать комментарий')

@section('content')
    <h1>Редактировать комментарий</h1>
    <p>Статья: {{ $article->title }}</p>

    <form action="{{ route('articles.comments.update', [$article, $comment]) }}" method="POST">
        @csrf
        @method('PUT')
        <div>
            <label for="comment-body">Комментарий:</label>
            <textarea id="comment-body" name="body" required maxlength="5000">{{ old('body', $comment->body) }}</textarea>
            @error('body') <div style="color:red">{{ $message }}</div> @enderror
        </div>
        <button type="submit">Сохранить комментарий</button>
    </form>

    <a href="{{ route('articles.show', $article) }}#comments">← Назад к статье</a>
@endsection
