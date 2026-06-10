<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit;

use Ironflow\Support\Collection;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    private function col(array $items = []): Collection
    {
        return new Collection($items);
    }

    public function test_map(): void
    {
        $result = $this->col([1, 2, 3])->map(fn($x) => $x * 2);
        $this->assertSame([2, 4, 6], $result->values()->toArray());
    }

    public function test_filter(): void
    {
        $result = $this->col([1, 2, 3, 4])->filter(fn($x) => $x % 2 === 0);
        $this->assertSame([2, 4], $result->values()->toArray());
    }

    public function test_reject(): void
    {
        $result = $this->col([1, 2, 3, 4])->reject(fn($x) => $x % 2 === 0);
        $this->assertSame([1, 3], $result->values()->toArray());
    }

    public function test_reduce(): void
    {
        $sum = $this->col([1, 2, 3, 4])->reduce(fn($carry, $x) => $carry + $x, 0);
        $this->assertSame(10, $sum);
    }

    public function test_pluck(): void
    {
        $data = [['name' => 'Alice'], ['name' => 'Bob']];
        $names = $this->col($data)->pluck('name');
        $this->assertSame(['Alice', 'Bob'], $names->values()->toArray());
    }

    public function test_first_and_last(): void
    {
        $c = $this->col([10, 20, 30]);
        $this->assertSame(10, $c->first());
        $this->assertSame(30, $c->last());
    }

    public function test_contains(): void
    {
        $c = $this->col([1, 2, 3]);
        $this->assertTrue($c->contains(2));
        $this->assertFalse($c->contains(99));
    }

    public function test_count(): void
    {
        $this->assertSame(3, $this->col([1, 2, 3])->count());
    }

    public function test_isEmpty_and_isNotEmpty(): void
    {
        $this->assertTrue($this->col([])->isEmpty());
        $this->assertFalse($this->col([1])->isEmpty());
        $this->assertTrue($this->col([1])->isNotEmpty());
    }

    public function test_unique(): void
    {
        $result = $this->col([1, 2, 2, 3, 3, 3])->unique()->values()->toArray();
        $this->assertSame([1, 2, 3], $result);
    }

    public function test_chunk(): void
    {
        $chunks = $this->col([1, 2, 3, 4, 5])->chunk(2);
        $this->assertSame(3, $chunks->count());
        $this->assertSame([1, 2], $chunks->first()->toArray());
    }

    public function test_sum(): void
    {
        $this->assertSame(15.0, $this->col([1, 2, 3, 4, 5])->sum());
    }

    public function test_avg(): void
    {
        $this->assertSame(3.0, $this->col([1, 2, 3, 4, 5])->avg());
    }

    public function test_min_and_max(): void
    {
        $c = $this->col([3, 1, 4, 1, 5, 9]);
        $this->assertSame(1.0, $c->min());
        $this->assertSame(9.0, $c->max());
    }

    public function test_sortBy(): void
    {
        $data = [['n' => 3], ['n' => 1], ['n' => 2]];
        $sorted = $this->col($data)->sortBy('n')->values()->pluck('n')->toArray();
        $this->assertSame([1, 2, 3], $sorted);
    }

    public function test_groupBy(): void
    {
        $data = [
            ['type' => 'a', 'v' => 1],
            ['type' => 'b', 'v' => 2],
            ['type' => 'a', 'v' => 3],
        ];
        $grouped = $this->col($data)->groupBy('type');
        $this->assertSame(2, $grouped->count());
        $this->assertSame(2, $grouped->first()->count());
    }

    public function test_toJson(): void
    {
        $json = $this->col([1, 2, 3])->toJson();
        $this->assertSame('[1,2,3]', $json);
    }

    public function test_merge(): void
    {
        $a = $this->col([1, 2]);
        $b = $this->col([3, 4]);
        $merged = $a->merge($b);
        $this->assertSame([1, 2, 3, 4], $merged->values()->toArray());
    }

    public function test_take_and_skip(): void
    {
        $c = $this->col([1, 2, 3, 4, 5]);
        $this->assertSame([1, 2], $c->take(2)->values()->toArray());
        $this->assertSame([3, 4, 5], $c->skip(2)->values()->toArray());
    }

    public function test_flatten(): void
    {
        $result = $this->col([[1, 2], [3, [4, 5]]])->flatten()->values()->toArray();
        $this->assertSame([1, 2, 3, 4, 5], $result);
    }
}
