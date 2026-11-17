<?php

namespace App\Notifications;

use App\Models\Article;
use Illuminate\Notifications\Notification;

class NewArticleNotification extends Notification
{
    public function __construct(public Article $article) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{article_id: int, title: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'article_id' => $this->article->id,
            'title' => $this->article->title,
        ];
    }
}
