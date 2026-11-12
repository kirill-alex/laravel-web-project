<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use App\Policies\ArticlePolicy;
use App\Policies\CommentPolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Article::class, ArticlePolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);

        Gate::before(function (User $user, string $ability): ?bool {
            return $user->isModerator() ? true : null;
        });

        Gate::define('moderate-content', fn (User $user): Response => Response::deny('Управление статьями и комментариями доступно только модератору.')
        );
    }
}
