<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Мой сайт')</title>
</head>
<body>
    <header>
        <nav>
            <a href="{{ url('/') }}">Главная</a>
            <a href="{{ url('/about') }}">О нас</a>
            <a href="{{ url('/contacts') }}">Контакты</a>
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