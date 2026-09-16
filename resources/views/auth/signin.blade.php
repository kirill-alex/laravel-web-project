@extends('layouts.app')

@section('title', 'Регистрация')

@section('content')
    <h1>Регистрация</h1>

    <form action="{{ url('/signin') }}" method="POST">
        @csrf

        <div>
            <label>Имя:</label>
            <input type="text" name="name" value="{{ old('name') }}">
            @error('name')
                <div style="color:red">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label>Email:</label>
            <input type="email" name="email" value="{{ old('email') }}">
            @error('email')
                <div style="color:red">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label>Пароль:</label>
            <input type="password" name="password">
            @error('password')
                <div style="color:red">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit">Зарегистрироваться</button>
    </form>
@endsection