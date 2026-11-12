<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CommentController extends Controller
{
    public function store(Request $request, Article $article): RedirectResponse
    {
        Gate::authorize('create', Comment::class);

        $validated = $request->validate(['body' => 'required|string|max:5000']);

        $article->comments()->create([
            'body' => $validated['body'],
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('articles.show', $article)
            ->with('status', 'Комментарий добавлен и ожидает модерации.');
    }

    public function edit(Request $request, Article $article, Comment $comment): View
    {
        Gate::authorize('update', $comment);

        return view('comments.edit', compact('article', 'comment'));
    }

    public function update(Request $request, Article $article, Comment $comment): RedirectResponse
    {
        Gate::authorize('update', $comment);

        $validated = $request->validate(['body' => 'required|string|max:5000']);
        $comment->fill($validated);
        if ($comment->isDirty('body') && ! $request->user()->isModerator()) {
            $comment->moderation_status = Comment::PENDING;
        }
        $comment->save();

        return redirect()->route('articles.show', $article)->with('status',
            $comment->moderation_status === Comment::PENDING
                ? 'Комментарий обновлён и ожидает модерации.'
                : 'Комментарий обновлён.'
        );
    }

    public function destroy(Request $request, Article $article, Comment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return redirect()->route('articles.show', $article)->with('status', 'Комментарий удалён.');
    }
}
