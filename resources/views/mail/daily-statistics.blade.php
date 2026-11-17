<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Статистика сайта за {{ $date }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #4a3f35; line-height: 1.5">
    <h1>Статистика сайта за {{ $date }}</h1>
    <p>Часовой пояс: {{ $timezone }}</p>
    <table style="border-collapse: collapse; width: 100%; max-width: 500px">
        <tr>
            <th style="text-align:left; padding:12px; border:1px solid #ddccb5">Показатель</th>
            <th style="text-align:left; padding:12px; border:1px solid #ddccb5">Количество</th>
        </tr>
        <tr>
            <td style="padding:12px; border:1px solid #ddccb5">Просмотры статей</td>
            <td style="padding:12px; border:1px solid #ddccb5">{{ $viewsCount }}</td>
        </tr>
        <tr>
            <td style="padding:12px; border:1px solid #ddccb5">Новые комментарии</td>
            <td style="padding:12px; border:1px solid #ddccb5">{{ $commentsCount }}</td>
        </tr>
    </table>
    <p>Учитываются открытия страниц статей и все новые комментарии, включая ожидающие модерации.</p>
    <p>Данные за текущий день приведены на момент формирования отчёта.</p>
    <p>{{ config('app.name') }}</p>
</body>
</html>
