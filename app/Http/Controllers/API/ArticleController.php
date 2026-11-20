<?php

namespace App\Http\Controllers\API;

use App\Events\NewArticleEvent;
use App\Http\Controllers\Controller;
use App\Jobs\VeryLongJob;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewArticleNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    // READ (список)
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Article::class);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $version = Cache::rememberForever('articles.index.version', fn (): string => (string) Str::uuid());
        $data = Cache::remember('articles.index.'.$version.'.'.$page, now()->addMinutes(5), function () use ($page): array {
            $paginator = Article::orderBy('id')->paginate(5, ['*'], 'page', $page);

            return [
                'items' => $paginator->getCollection()->map(fn (Article $article): array => $article->getAttributes())->all(),
                'total' => $paginator->total(),
            ];
        });
        $articles = new LengthAwarePaginator(Article::hydrate($data['items']), $data['total'], 5, $page, [
            'path' => request()->url(),
            'query' => request()->except('page'),
        ]);

        return response()->json(['articles' => $articles]);
    }

    // READ (одна статья)
    public function show(string $id): JsonResponse
    {
        $data = Cache::rememberForever('articles.show.'.$id, function () use ($id): array {
            $article = Article::findOrFail($id);
            $comments = $article->comments()->where('moderation_status', Comment::APPROVED)
                ->with('user:id,name')->latest('id')->get();

            return [
                'article' => $article->getAttributes(),
                'comments' => $comments->map(fn (Comment $comment): array => [
                    'attributes' => $comment->getAttributes(),
                    'user' => $comment->user->getAttributes(),
                ])->all(),
            ];
        });
        $article = (new Article)->newFromBuilder($data['article']);
        Gate::authorize('view', $article);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $pageComments = collect($data['comments'])->slice(($page - 1) * 10, 10)
            ->map(function (array $row): Comment {
                $comment = (new Comment)->newFromBuilder($row['attributes']);
                $comment->setRelation('user', (new User)->newFromBuilder($row['user']));

                return $comment;
            })->values();
        $comments = new LengthAwarePaginator($pageComments, count($data['comments']), 10, $page, [
            'path' => request()->url(),
            'query' => request()->except('page'),
        ]);

        return response()->json(['article' => $article, 'comments' => $comments]);
    }

    // CREATE — сохранение
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Article::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'preview_image' => 'nullable|string',
            'full_image' => 'nullable|string',
        ]);

        $moderatorAddress = config('mail.moderator_address');
        $recipients = filled($moderatorAddress)
            ? collect([$moderatorAddress])
            : User::whereHas('role', fn (Builder $query) => $query->where('name', Role::MODERATOR))
                ->pluck('email')->unique();

        $article = DB::transaction(function () use ($validated, $recipients): Article {
            $article = Article::create($validated);

            foreach ($recipients as $recipient) {
                VeryLongJob::dispatch($article, $recipient);
            }

            User::whereHas('role', fn (Builder $query) => $query->where('name', Role::READER))
                ->chunkById(100, function (Collection $readers) use ($article): void {
                    Notification::send($readers, new NewArticleNotification($article));
                });

            return $article;
        });

        // A new version makes every cached list page obsolete; old entries expire by TTL.
        Cache::forget('articles.index.version');
        NewArticleEvent::dispatch($article);

        return response()->json(['message' => 'Статья успешно создана.', 'article' => $article], 201);
    }

    // UPDATE — сохранение изменений
    public function update(Request $request, string $id): JsonResponse
    {
        $article = Article::findOrFail($id);
        Gate::authorize('update', $article);

        $presence = $request->isMethod('PATCH') ? 'sometimes|required' : 'required';
        $validated = $request->validate([
            'title' => $presence.'|string|max:255',
            'description' => $presence.'|string',
            'preview_image' => 'nullable|string',
            'full_image' => 'nullable|string',
        ]);

        $article->update($validated);
        $this->flushCachePreservingWorkerSignal();

        return response()->json(['message' => 'Статья обновлена.', 'article' => $article]);
    }

    // DELETE
    public function destroy(string $id): JsonResponse
    {
        $article = Article::findOrFail($id);
        Gate::authorize('delete', $article);
        $article->delete();
        $this->flushCachePreservingWorkerSignal();

        return response()->json(['message' => 'Статья удалена.']);
    }

    private function flushCachePreservingWorkerSignal(): void
    {
        DB::transaction(function (): void {
            // Removing this shared value would stop existing queue workers.
            $queueRestart = Cache::get('illuminate:queue:restart');
            Cache::flush();

            if ($queueRestart !== null) {
                Cache::forever('illuminate:queue:restart', $queueRestart);
            }
        });
    }
}
