<?php

declare(strict_types=1);

namespace Core\Tests\Unit;

use Core\Container;
use Core\Events\Dispatcher;
use PHPUnit\Framework\TestCase;

final class SomethingHappened
{
    public function __construct(public readonly string $payload) {}
}

final class SomethingElse {}

class EventDispatcherTest extends TestCase
{
    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new Dispatcher(new Container());
    }

    public function test_closure_listener_receives_event(): void
    {
        $received = null;
        $this->dispatcher->listen(SomethingHappened::class, function (SomethingHappened $e) use (&$received) {
            $received = $e->payload;
        });
        $this->dispatcher->dispatch(new SomethingHappened('hello'));
        $this->assertSame('hello', $received);
    }

    public function test_multiple_listeners_all_called(): void
    {
        $log = [];
        $this->dispatcher->listen(SomethingHappened::class, function () use (&$log) { $log[] = 'a'; });
        $this->dispatcher->listen(SomethingHappened::class, function () use (&$log) { $log[] = 'b'; });
        $this->dispatcher->dispatch(new SomethingHappened('x'));
        $this->assertSame(['a', 'b'], $log);
    }

    public function test_listener_returning_false_stops_propagation(): void
    {
        $log = [];
        $this->dispatcher->listen(SomethingHappened::class, function () use (&$log) {
            $log[] = 'first';
            return false;
        });
        $this->dispatcher->listen(SomethingHappened::class, function () use (&$log) {
            $log[] = 'second';
        });
        $this->dispatcher->dispatch(new SomethingHappened('stop'));
        $this->assertSame(['first'], $log);
    }

    public function test_until_returns_first_non_null_result(): void
    {
        $this->dispatcher->listen(SomethingHappened::class, fn() => null);
        $this->dispatcher->listen(SomethingHappened::class, fn() => 'found');
        $this->dispatcher->listen(SomethingHappened::class, fn() => 'too late');

        $result = $this->dispatcher->until(new SomethingHappened('q'));
        $this->assertSame('found', $result);
    }

    public function test_no_listener_dispatches_silently(): void
    {
        $this->dispatcher->dispatch(new SomethingElse());
        $this->assertTrue(true);
    }

    public function test_dispatch_unrelated_event_not_triggered(): void
    {
        $called = false;
        $this->dispatcher->listen(SomethingHappened::class, function () use (&$called) {
            $called = true;
        });
        $this->dispatcher->dispatch(new SomethingElse());
        $this->assertFalse($called);
    }
}
