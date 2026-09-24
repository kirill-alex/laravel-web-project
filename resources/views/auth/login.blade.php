@extends('layouts.app')

@section('title', 'Вход')

@section('content')
    <h1>Вход</h1>

    <form action="{{ url('/login') }}" method="POST">
        @csrf

        <div>
            <label>Email:</label>
            <input type="email" name="email" value="{{ old('email') }}">
            @error('email') <div style="color:red">{{ $message }}</div> @enderror
        </div>

        <div>
            <label>Пароль:</label>
            <input type="password" name="password">
        </div>

        <button type="submit">Войти</button>
    </form>
@endsection