@extends('layouts.app')

@section('title', 'Главная')

@section('content')
    <h1>Новости</h1>
    <table border="1" cellpadding="8">
        <tr>
            <th>Превью</th>
            <th>Заголовок</th>
            <th>Описание</th>
        </tr>
        @foreach($items as $item)
            <tr>
                <td>
                    <a href="{{ url('/gallery/' . $item['id']) }}">
                        <img src="{{ asset($item['preview_image']) }}" width="100" alt="{{ $item['title'] }}">
                    </a>
                </td>
                <td>{{ $item['title'] }}</td>
                <td>{{ $item['description'] }}</td>
            </tr>
        @endforeach
    </table>
@endsection