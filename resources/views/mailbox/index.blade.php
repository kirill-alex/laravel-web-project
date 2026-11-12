@extends('layouts.app')

@section('title', 'Тестовая почта')

@section('content')
    <h1>Тестовая почта</h1>
    <p>Письма сохраняются здесь. На настоящие почтовые адреса они не отправляются.</p>

    @if($messages->isEmpty())
        <p>Писем пока нет. Создайте статью — уведомление появится здесь после обработки очереди.</p>
    @else
        <table>
            <thead><tr><th>Тема</th><th>Получатель</th><th>Дата</th></tr></thead>
            <tbody>
                @foreach($messages as $message)
                    <tr>
                        <td><a href="{{ route('mailbox.show', $message) }}">{{ $message->subject }}</a></td>
                        <td>{{ implode(', ', $message->recipients) }}</td>
                        <td>{{ $message->created_at->format('d.m.Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $messages->links() }}
    @endif
@endsection
