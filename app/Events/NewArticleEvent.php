<?php

namespace App\Events;

use App\Models\Article;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewArticleEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public Article $article) {}

    public function broadcastOn(): Channel
    {
        return new Channel('articles');
    }

    /** @return array{article: array{id: int, title: string, url: string}} */
    public function broadcastWith(): array
    {
        return [
            'article' => [
                'id' => $this->article->id,
                'title' => $this->article->title,
                'url' => route('articles.show', $this->article),
            ],
        ];
    }

    public function broadcastWhen(): bool
    {
        return ! in_array(config('broadcasting.default'), [null, 'null'], true);
    }
}
