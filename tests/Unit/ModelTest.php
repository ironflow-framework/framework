<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit;

use Ironflow\Database\Connection;
use Ironflow\Database\Model;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
{
    private static Connection $conn;

    public static function setUpBeforeClass(): void
    {
        self::$conn = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        self::$conn->statement('
            CREATE TABLE articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                body TEXT,
                published INTEGER NOT NULL DEFAULT 0,
                created_at TEXT,
                updated_at TEXT
            )
        ');
        Model::setConnection(self::$conn);
    }

    protected function tearDown(): void
    {
        self::$conn->statement('DELETE FROM articles');
    }

    public function test_create_and_find(): void
    {
        $article = ArticleModel::create(['title' => 'Hello', 'body' => 'World', 'published' => false]);
        $this->assertNotNull($article->id);
        $found = ArticleModel::find($article->id);
        $this->assertInstanceOf(ArticleModel::class, $found);
        $this->assertSame('Hello', $found->title);
    }

    public function test_save_updates_existing(): void
    {
        $article = ArticleModel::create(['title' => 'Draft', 'body' => 'text', 'published' => false]);
        $article->title = 'Updated';
        $article->save();

        $found = ArticleModel::find($article->id);
        $this->assertSame('Updated', $found->title);
    }

    public function test_delete(): void
    {
        $article = ArticleModel::create(['title' => 'To Delete', 'body' => '', 'published' => false]);
        $id = $article->id;
        $article->delete();
        $this->assertNull(ArticleModel::find($id));
    }

    public function test_all(): void
    {
        ArticleModel::create(['title' => 'A', 'body' => '', 'published' => false]);
        ArticleModel::create(['title' => 'B', 'body' => '', 'published' => false]);
        $all = ArticleModel::all();
        $this->assertCount(2, $all);
    }

    public function test_find_or_fail_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        ArticleModel::findOrFail(99999);
    }

    public function test_dirty_tracking(): void
    {
        $article = ArticleModel::create(['title' => 'Clean', 'body' => '', 'published' => false]);
        $article->refresh();
        $this->assertFalse($article->isDirty());
        $article->title = 'Dirty';
        $this->assertTrue($article->isDirty());
        $this->assertTrue($article->isDirty('title'));
    }

    public function test_cast_boolean(): void
    {
        $article = ArticleModel::create(['title' => 'Cast', 'body' => '', 'published' => 1]);
        $found = ArticleModel::find($article->id);
        $this->assertIsBool($found->published);
        $this->assertTrue($found->published);
    }

    public function test_first_or_create(): void
    {
        $a = ArticleModel::firstOrCreate(['title' => 'Unique'], ['body' => 'body1', 'published' => false]);
        $b = ArticleModel::firstOrCreate(['title' => 'Unique'], ['body' => 'body2', 'published' => false]);
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, ArticleModel::query()->where('title', '=', 'Unique')->count());
    }

    public function test_to_array_hides_hidden_fields(): void
    {
        $article = ArticleModel::create(['title' => 'Visible', 'body' => 'secret', 'published' => false]);
        $array = $article->toArray();
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayNotHasKey('body', $array);
    }
}

class ArticleModel extends Model
{
    protected string $table = 'articles';
    protected array $fillable = ['title', 'body', 'published'];
    protected array $casts = ['published' => 'bool'];
    protected array $hidden = ['body'];
}
