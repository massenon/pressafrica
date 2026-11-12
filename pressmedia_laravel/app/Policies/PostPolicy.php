<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Authors work on their own posts; anyone who may publish (editors, admins) may act on all posts.
 * Matches the prototype, where an author's list is "My posts".
 */
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('posts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('posts.create');
    }

    public function update(User $user, Post $post): bool
    {
        return $user->can('posts.edit') && ($user->can('posts.publish') || $post->author_id === $user->id);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->can('posts.delete') && ($user->can('posts.publish') || $post->author_id === $user->id);
    }
}
