<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(Request $request, Article $article): JsonResponse
    {
        Gate::authorize('create', Comment::class);
        $validated = $request->validate(['body' => 'required|string|max:5000']);
        $comment = $article->comments()->create([
            'body' => $validated['body'],
            'user_id' => $request->user()->id,
        ]);
        Cache::forget('articles.show.'.$article->id);

        return response()->json([
            'message' => 'Комментарий добавлен и ожидает модерации.',
            'comment' => $comment->refresh(),
        ], 201);
    }

    public function update(Request $request, Article $article, Comment $comment): JsonResponse
    {
        Gate::authorize('update', $comment);
        $validated = $request->validate(['body' => 'required|string|max:5000']);
        $comment->fill($validated);
        if ($comment->isDirty('body') && ! $request->user()->isModerator()) {
            $comment->moderation_status = Comment::PENDING;
        }
        $comment->save();
        Cache::forget('articles.show.'.$article->id);

        return response()->json(['message' => 'Комментарий обновлён.', 'comment' => $comment]);
    }

    public function destroy(Request $request, Article $article, Comment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);
        $comment->delete();
        Cache::forget('articles.show.'.$article->id);

        return response()->json(['message' => 'Комментарий удалён.']);
    }
}
