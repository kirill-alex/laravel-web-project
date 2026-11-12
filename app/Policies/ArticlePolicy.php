<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ArticlePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Article $article): bool
    {
        return true;
    }

    public function create(User $user): Response
    {
        return Response::deny('Создавать статьи может только модератор.');
    }

    public function update(User $user, Article $article): Response
    {
        return Response::deny('Редактировать статьи может только модератор.');
    }

    public function delete(User $user, Article $article): Response
    {
        return Response::deny('Удалять статьи может только модератор.');
    }
}
