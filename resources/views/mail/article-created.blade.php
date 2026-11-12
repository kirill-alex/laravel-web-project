<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Новая статья</title>
</head>
<body style="font-family: Arial, sans-serif; color: #4a3f35; line-height: 1.5">
    <h1>На сайте добавлена новая статья</h1>
    <h2>{{ $article->title }}</h2>
    <p style="white-space: pre-wrap">{{ $article->description }}</p>
    <p>Дата публикации: {{ $article->created_at->format('d.m.Y H:i') }}</p>
    <p><a href="{{ route('articles.show', $article) }}">Открыть статью</a></p>
    <p>{{ config('app.name') }}</p>
</body>
</html>
