@extends('layouts.app')

@section('title', 'Новости')

@section('content')
    <h1>Новости</h1>
    @if(session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif
    @if(session('warning'))
        <p role="alert">{{ session('warning') }}</p>
    @endif
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
            <td><a href="{{ url('/articles/' . $article->id) }}"><img class="article-thumbnail" src="{{ asset($article->preview_image ?: 'images/cat1.jpg') }}" alt="{{ $article->title }}" width="120" height="90" loading="lazy" onerror="this.onerror=null; this.src='{{ asset('images/cat1.jpg') }}';"></a></td>
            <td><a href="{{ url('/articles/' . $article->id) }}">{{ $article->title }}</a></td>
            <td>{{ $article->description }}</td>
        </tr>
    @endforeach
    </table>

{{ $articles->links() }}
@endsection


