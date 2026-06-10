<?php

declare(strict_types=1);

namespace Core\Tests\Unit;

use Core\Database\Connection;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        // ':memory:' contains ':', so Connection::buildParams() skips base_path()
        $this->connection = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->connection->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, age INTEGER, active INTEGER DEFAULT 1)');
        $this->connection->statement("INSERT INTO users (name, email, age, active) VALUES ('Alice', 'alice@test.com', 30, 1)");
        $this->connection->statement("INSERT INTO users (name, email, age, active) VALUES ('Bob', 'bob@test.com', 25, 0)");
        $this->connection->statement("INSERT INTO users (name, email, age, active) VALUES ('Carol', 'carol@test.com', 35, 1)");
    }

    public function test_get_all(): void
    {
        $rows = $this->connection->table('users')->get();
        $this->assertCount(3, $rows);
    }

    public function test_where_clause(): void
    {
        $rows = $this->connection->table('users')->where('active', '=', 1)->get();
        $this->assertCount(2, $rows);
    }

    public function test_first(): void
    {
        $row = $this->connection->table('users')->where('name', '=', 'Bob')->first();
        $this->assertNotNull($row);
        $this->assertSame('Bob', $row->name);
    }

    public function test_find(): void
    {
        $row = $this->connection->table('users')->find(1);
        $this->assertNotNull($row);
        $this->assertSame('Alice', $row->name);
    }

    public function test_count(): void
    {
        $this->assertSame(3, $this->connection->table('users')->count());
        $this->assertSame(2, $this->connection->table('users')->where('active', '=', 1)->count());
    }

    public function test_pluck(): void
    {
        $names = $this->connection->table('users')->orderBy('name')->pluck('name');
        $this->assertSame(['Alice', 'Bob', 'Carol'], $names);
    }

    public function test_order_by(): void
    {
        $rows = $this->connection->table('users')->orderBy('age', 'DESC')->get();
        $this->assertSame('Carol', $rows->first()->name);
    }

    public function test_limit_and_offset(): void
    {
        $rows = $this->connection->table('users')->orderBy('id')->limit(2)->offset(1)->get();
        $this->assertCount(2, $rows);
        $this->assertSame('Bob', $rows->first()->name);
    }

    public function test_where_in(): void
    {
        $rows = $this->connection->table('users')->whereIn('name', ['Alice', 'Carol'])->get();
        $this->assertCount(2, $rows);
    }

    public function test_where_not_in(): void
    {
        $rows = $this->connection->table('users')->whereNotIn('name', ['Alice'])->get();
        $this->assertCount(2, $rows);
    }

    public function test_insert_get_id(): void
    {
        $id = $this->connection->table('users')->insertGetId(['name' => 'Dave', 'email' => 'dave@test.com', 'age' => 28, 'active' => 1]);
        $this->assertIsInt($id);
        $this->assertSame(4, $id);
    }

    public function test_update(): void
    {
        $this->connection->table('users')->where('name', '=', 'Bob')->updateWhere(['active' => 1]);
        $bob = $this->connection->table('users')->where('name', '=', 'Bob')->first();
        $this->assertSame(1, (int) $bob->active);
    }

    public function test_delete(): void
    {
        $this->connection->table('users')->where('name', '=', 'Bob')->deleteWhere();
        $this->assertSame(2, $this->connection->table('users')->count());
    }

    public function test_sum_avg_min_max(): void
    {
        $this->assertSame(90.0, $this->connection->table('users')->sum('age'));
        $this->assertSame(30.0, $this->connection->table('users')->avg('age'));
        $this->assertSame(25.0, $this->connection->table('users')->min('age'));
        $this->assertSame(35.0, $this->connection->table('users')->max('age'));
    }

    public function test_to_sql(): void
    {
        [$sql, $bindings] = $this->connection->table('users')->where('active', '=', 1)->orderBy('name')->toSql();
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertContains(1, $bindings);
    }

    public function test_when_applies_only_if_true(): void
    {
        $rows = $this->connection->table('users')->when(true, fn($q) => $q->where('active', '=', 0))->get();
        $this->assertCount(1, $rows);

        $rows2 = $this->connection->table('users')->when(false, fn($q) => $q->where('active', '=', 0))->get();
        $this->assertCount(3, $rows2);
    }
}
