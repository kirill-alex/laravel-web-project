<details class="notification-menu">
    <summary>Уведомления ({{ $unreadArticleNotifications->count() }})</summary>
    <div class="notification-dropdown">
        <p class="notification-heading">Непрочитанные уведомления</p>
        @forelse($unreadArticleNotifications as $notification)
            <form class="notification-item" action="{{ route('notifications.read', $notification->id) }}" method="POST">
                @csrf
                <button class="notification-link" type="submit">
                    <span class="notification-caption">Новая статья</span>
                    <strong>{{ $notification->data['title'] }}</strong>
                </button>
            </form>
        @empty
            <p class="notification-empty">Новых уведомлений нет.</p>
        @endforelse
    </div>
</details>
