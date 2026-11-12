<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommentController extends Controller
{
    public function store(Request $request, Article $article): RedirectResponse
    {
        $validated = $request->validate(['body' => 'required|string|max:5000']);

        $article->comments()->create([
            'body' => $validated['body'],
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('articles.show', $article)->with('status', 'Комментарий добавлен.');
    }

    public function edit(Request $request, Article $article, Comment $comment): View
    {
        abort_unless($comment->user_id === $request->user()->id, 403);

        return view('comments.edit', compact('article', 'comment'));
    }

    public function update(Request $request, Article $article, Comment $comment): RedirectResponse
    {
        abort_unless($comment->user_id === $request->user()->id, 403);

        $validated = $request->validate(['body' => 'required|string|max:5000']);
        $comment->update($validated);

        return redirect()->route('articles.show', $article)->with('status', 'Комментарий обновлён.');
    }

    public function destroy(Request $request, Article $article, Comment $comment): RedirectResponse
    {
        abort_unless($comment->user_id === $request->user()->id, 403);

        $comment->delete();

        return redirect()->route('articles.show', $article)->with('status', 'Комментарий удалён.');
    }
}
