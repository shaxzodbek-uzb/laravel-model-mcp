<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A model with NO policy — used to prove the fail-closed default.
 */
class Tag extends Model
{
    protected $table = 'tags';

    protected $fillable = ['name'];
}
