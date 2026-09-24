@extends('layouts.app')

@section('title', 'Новая статья')

@section('content')
    <h1>Создать статью</h1>

    <form action="{{ url('/articles') }}" method="POST">
        @csrf

        <div>
            <label>Заголовок:</label>
            <input type="text" name="title" value="{{ old('title') }}">
            @error('title') <div style="color:red">{{ $message }}</div> @enderror
        </div>

        <div>
            <label>Описание:</label>
            <textarea name="description">{{ old('description') }}</textarea>
            @error('description') <div style="color:red">{{ $message }}</div> @enderror
        </div>

        <div>
            <label>Превью-картинка (URL):</label>
            <input type="text" name="preview_image" value="{{ old('preview_image') }}">
        </div>

        <div>
            <label>Полная картинка (URL):</label>
            <input type="text" name="full_image" value="{{ old('full_image') }}">
        </div>

        <button type="submit">Создать</button>
    </form>
@endsection