<?php

namespace App\Http\Controllers;

use App\Mail\ArticleCreatedMail;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ArticleController extends Controller
{
    // READ (список)
    public function index()
    {
        Gate::authorize('viewAny', Article::class);
        $articles = Article::paginate(5);

        return view('articles.index', ['articles' => $articles]);
    }

    // READ (одна статья)
    public function show($id)
    {
        $article = Article::findOrFail($id);
        Gate::authorize('view', $article);
        $comments = $article->comments()->where('moderation_status', Comment::APPROVED)
            ->with('user')->latest('id')->paginate(10);

        return view('articles.show', ['article' => $article, 'comments' => $comments]);
    }

    // CREATE — форма создания
    public function create()
    {
        Gate::authorize('create', Article::class);

        return view('articles.create');
    }

    // CREATE — сохранение
    public function store(Request $request)
    {
        Gate::authorize('create', Article::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'preview_image' => 'nullable|string',
            'full_image' => 'nullable|string',
        ]);

        $article = Article::create($validated);

        $moderatorAddress = config('mail.moderator_address');
        $recipients = filled($moderatorAddress)
            ? collect([$moderatorAddress])
            : User::whereHas('role', fn (Builder $query) => $query->where('name', Role::MODERATOR))
                ->pluck('email')->unique();

        $notificationFailed = $recipients->isEmpty();
        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient)->send(new ArticleCreatedMail($article));
            } catch (TransportExceptionInterface $exception) {
                report($exception);
                $notificationFailed = true;
            }
        }

        if ($notificationFailed) {
            return redirect('/articles')->with('warning',
                'Статья сохранена, но не все уведомления отправлены. Проверьте настройки почты и адрес модератора.'
            );
        }

        return redirect('/articles')->with('status', 'Статья успешно создана.');
    }

    // UPDATE — форма редактирования
    public function edit($id)
    {
        $article = Article::findOrFail($id);
        Gate::authorize('update', $article);

        return view('articles.edit', ['article' => $article]);
    }

    // UPDATE — сохранение изменений
    public function update(Request $request, $id)
    {
        $article = Article::findOrFail($id);
        Gate::authorize('update', $article);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'preview_image' => 'nullable|string',
            'full_image' => 'nullable|string',
        ]);

        $article->update($validated);

        return redirect('/articles');
    }

    // DELETE
    public function destroy($id)
    {
        $article = Article::findOrFail($id);
        Gate::authorize('delete', $article);
        $article->delete();

        return redirect('/articles');
    }
}
