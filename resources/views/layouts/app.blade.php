<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Мой сайт')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header>
        <nav>
    <div class="nav-left">
        <a href="{{ url('/') }}">Главная</a>
        <a href="{{ route('articles.index') }}">Статьи</a>
        @can('create', \App\Models\Article::class)
            <a href="{{ route('articles.create') }}">Создать новость</a>
        @endcan
        @can('moderate-content')
            <a href="{{ route('moderation.comments.index') }}">Модерация комментариев</a>
            <a href="{{ route('mailbox.index') }}">Тестовая почта</a>
        @endcan
        <a href="{{ url('/about') }}">О нас</a>
        <a href="{{ url('/contacts') }}">Контакты</a>
    </div>

    <div class="nav-right">
        @auth
            <span class="nav-username">{{ Auth::user()->name }}</span>
            <form action="{{ url('/logout') }}" method="POST" style="display:inline">
                @csrf
                <button type="submit">Выйти</button>
            </form>
        @else
            <a href="{{ url('/login') }}">Войти</a>
            <a href="{{ url('/register') }}">Регистрация</a>
        @endauth
    </div>
</nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer>
        <p>Алексеев Кирилл Владимирович, группа 251-321</p>
    </footer>
</body>
</html>
