<?php

declare(strict_types=1);

namespace Core\Events;

use Core\Container;

/**
 * Event Dispatcher — decoupled pub/sub between modules.
 * Events are plain readonly classes; listeners are resolved via the Container.
 *
 * Usage:
 *   Event::dispatch(new PostPublished($post));
 *   Event::listen(PostPublished::class, NotifySubscribersListener::class);
 */
class Dispatcher
{
    /** @var array<string, array<callable|string>> */
    private array $listeners = [];

    public function __construct(private readonly Container $container) {}

    public function listen(string $eventClass, callable|string $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): void
    {
        $class = get_class($event);

        foreach ($this->getListenersForEvent($class) as $listener) {
            $result = $this->callListener($listener, $event);

            // A listener returning false stops propagation
            if ($result === false) {
                break;
            }
        }
    }

    /**
     * Dispatch and return whether any listener stopped propagation.
     */
    public function until(object $event): bool
    {
        $class = get_class($event);

        foreach ($this->getListenersForEvent($class) as $listener) {
            $result = $this->callListener($listener, $event);
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    public function hasListeners(string $eventClass): bool
    {
        return !empty($this->getListenersForEvent($eventClass));
    }

    public function forget(string $eventClass): void
    {
        unset($this->listeners[$eventClass]);
    }

    private function getListenersForEvent(string $class): array
    {
        return $this->listeners[$class] ?? [];
    }

    private function callListener(callable|string $listener, object $event): mixed
    {
        if (is_string($listener)) {
            $instance = $this->container->make($listener);
            return $instance->handle($event);
        }

        return $listener($event);
    }
}
