<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tests\Fixtures\Models;

use Blaze\ModelMcp\Tests\Fixtures\Enums\PostStatus;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = ['title', 'body', 'status', 'published', 'user_id', 'team_id'];

    protected $hidden = ['secret_notes'];

    protected $casts = [
        'status' => PostStatus::class,
        'published' => 'boolean',
    ];
}
