<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Database\Model;

class ArticleModel extends Model
{
    protected string $table    = 'articles';
    protected array  $fillable = ['title', 'body', 'published'];
    protected array  $casts    = ['published' => 'bool'];
    protected array  $hidden   = ['body'];
}
