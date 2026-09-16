@extends('layouts.app')

@section('title', 'Новости')

@section('content')
    <h1>Новости</h1>
    <table border="1" cellpadding="8">
        <tr>
            <th>Превью</th>
            <th>Заголовок</th>
            <th>Описание</th>
        </tr>
        @foreach($articles as $article)
            <tr>
                <td>
                    @if($article->preview_image)
                        <img src="{{ $article->preview_image }}" width="100">
                    @endif
                </td>
                <td>{{ $article->title }}</td>
                <td>{{ $article->description }}</td>
            </tr>
        @endforeach
    </table>
@endsection