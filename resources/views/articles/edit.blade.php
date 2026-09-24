@extends('layouts.app')

@section('title', 'Редактировать статью')

@section('content')
    <h1>Редактировать статью</h1>

    <form action="{{ url('/articles/' . $article->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div>
            <label>Заголовок:</label>
            <input type="text" name="title" value="{{ old('title', $article->title) }}">
            @error('title') <div style="color:red">{{ $message }}</div> @enderror
        </div>

        <div>
            <label>Описание:</label>
            <textarea name="description">{{ old('description', $article->description) }}</textarea>
            @error('description') <div style="color:red">{{ $message }}</div> @enderror
        </div>

        <div>
            <label>Превью-картинка (URL):</label>
            <input type="text" name="preview_image" value="{{ old('preview_image', $article->preview_image) }}">
        </div>

        <div>
            <label>Полная картинка (URL):</label>
            <input type="text" name="full_image" value="{{ old('full_image', $article->full_image) }}">
        </div>

        <button type="submit">Сохранить</button>
    </form>
@endsection