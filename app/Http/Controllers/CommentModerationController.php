<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CommentModerationController extends Controller
{
    public function index(): View
    {
        Gate::authorize('moderate-content');

        $comments = Comment::where('moderation_status', Comment::PENDING)
            ->with(['article', 'user'])->oldest('id')->paginate(10);

        return view('moderation.comments', ['comments' => $comments]);
    }

    public function approve(Comment $comment): RedirectResponse
    {
        Gate::authorize('moderate-content');
        $this->decide($comment, Comment::APPROVED);

        return redirect()->route('moderation.comments.index')->with('status', 'Комментарий одобрен.');
    }

    public function reject(Comment $comment): RedirectResponse
    {
        Gate::authorize('moderate-content');
        $this->decide($comment, Comment::REJECTED);

        return redirect()->route('moderation.comments.index')->with('status', 'Комментарий отклонён.');
    }

    private function decide(Comment $comment, string $status): void
    {
        $updated = Comment::whereKey($comment->id)->where('moderation_status', Comment::PENDING)
            ->update(['moderation_status' => $status]);

        abort_unless($updated === 1, 409, 'Этот комментарий уже прошёл модерацию. Обновите список.');
    }
}
