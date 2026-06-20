<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tests\Fixtures\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
