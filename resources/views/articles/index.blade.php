@extends('layouts.app')

@section('title', 'Новости')

@section('content')
    <h1>Новости</h1>
    @can('create', \App\Models\Article::class)
        <a href="{{ url('/articles/create') }}">+ Добавить статью</a>
    @endcan
    <table border="1" cellpadding="8">
        <tr>
            <th>Превью</th>
            <th>Заголовок</th>
            <th>Описание</th>
        </tr>
        @foreach($articles as $article)
        <tr>
            <td><a href="{{ url('/articles/' . $article->id) }}"><img src="{{ $article->preview_image }}" width="100"></a></td>
            <td><a href="{{ url('/articles/' . $article->id) }}">{{ $article->title }}</a></td>
            <td>{{ $article->description }}</td>
        </tr>
    @endforeach
    </table>

{{ $articles->links() }}
@endsection


