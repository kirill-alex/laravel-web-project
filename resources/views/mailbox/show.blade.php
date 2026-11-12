@extends('layouts.app')

@section('title', $message->subject)

@section('content')
    <h1>{{ $message->subject }}</h1>
    <p>От: {{ implode(', ', $message->sender) }}</p>
    <p>Кому: {{ implode(', ', $message->recipients) }}</p>
    <p>{{ $message->created_at->format('d.m.Y H:i') }}</p>
    @if($message->html_body)
        <iframe title="Содержимое письма" sandbox srcdoc="{{ $message->html_body }}" style="width:100%; min-height:450px; border:1px solid #ddccb5; background:white"></iframe>
    @else
        <p style="white-space: pre-wrap">{{ $message->text_body }}</p>
    @endif
    <p><a href="{{ route('mailbox.index') }}">← К списку писем</a></p>
@endsection
