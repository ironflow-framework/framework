<?php

declare(strict_types=1);

namespace Core\Tests\Unit;

use Core\Container;
use Core\Exceptions\ContainerException;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function test_bind_and_make_closure(): void
    {
        $this->container->bind('greeting', fn() => 'Hello IronFlow');
        $this->assertSame('Hello IronFlow', $this->container->make('greeting'));
    }

    public function test_singleton_returns_same_instance(): void
    {
        $this->container->singleton('counter', fn() => new \stdClass());
        $a = $this->container->make('counter');
        $b = $this->container->make('counter');
        $this->assertSame($a, $b);
    }

    public function test_instance_binds_existing_object(): void
    {
        $obj = new \stdClass();
        $obj->value = 42;
        $this->container->instance('thing', $obj);
        $this->assertSame($obj, $this->container->make('thing'));
    }

    public function test_auto_resolve_concrete_class(): void
    {
        $result = $this->container->make(SimpleService::class);
        $this->assertInstanceOf(SimpleService::class, $result);
    }

    public function test_auto_resolve_with_constructor_injection(): void
    {
        $result = $this->container->make(ServiceWithDep::class);
        $this->assertInstanceOf(ServiceWithDep::class, $result);
        $this->assertInstanceOf(SimpleService::class, $result->dep);
    }

    public function test_make_overrides_applied(): void
    {
        $override = new SimpleService();
        $result = $this->container->make(ServiceWithDep::class, [
            SimpleService::class => $override,
        ]);
        $this->assertSame($override, $result->dep);
    }

    public function test_bind_overwrites_previous(): void
    {
        $this->container->bind('val', fn() => 1);
        $this->container->bind('val', fn() => 2);
        $this->assertSame(2, $this->container->make('val'));
    }

    public function test_non_existent_abstract_throws(): void
    {
        $this->expectException(ContainerException::class);
        $this->container->make('NonExistentClass_XYZ_ABC');
    }
}

// ── Inline fixtures ───────────────────────────────────────────────────────────

class SimpleService {}

class ServiceWithDep
{
    public function __construct(public readonly SimpleService $dep) {}
}
