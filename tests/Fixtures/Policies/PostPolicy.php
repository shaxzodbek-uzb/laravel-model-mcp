<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tests\Fixtures\Policies;

use Blaze\ModelMcp\Tests\Fixtures\Models\Post;
use Blaze\ModelMcp\Tests\Fixtures\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only the author may modify a post.
     */
    public function update(User $user, Post $post): bool
    {
        return (int) $user->getKey() === (int) $post->user_id;
    }

    public function delete(User $user, Post $post): bool
    {
        return (int) $user->getKey() === (int) $post->user_id;
    }
}
