<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Notifications\NewArticleNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, string $notification): RedirectResponse
    {
        $notice = $request->user()->notifications()
            ->where('type', NewArticleNotification::class)->findOrFail($notification);
        $article = Article::find($notice->data['article_id'] ?? null);

        $notice->markAsRead();

        if (! $article) {
            return redirect()->route('articles.index')
                ->with('status', 'Статья из уведомления уже удалена.');
        }

        return redirect()->route('articles.show', $article);
    }
}
