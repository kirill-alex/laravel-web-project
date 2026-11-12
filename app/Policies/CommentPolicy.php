<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class CommentPolicy
{
    public function create(User $user): Response
    {
        return $user->role?->name === Role::READER
            ? Response::allow()
            : Response::deny('Добавление комментариев недоступно для вашей роли.');
    }

    public function update(User $user, Comment $comment): Response
    {
        return Gate::forUser($user)->inspect('manage-comment', $comment);
    }

    public function delete(User $user, Comment $comment): Response
    {
        return Gate::forUser($user)->inspect('manage-comment', $comment);
    }
}
