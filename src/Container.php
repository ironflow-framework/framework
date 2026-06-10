<?php

declare(strict_types=1);

namespace Core;

use Ironflow\Attributes\Inject;
use Ironflow\Exceptions\ContainerException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

/**
 * IoC Container with auto-resolution, singleton/transient scopes,
 * interface binding, named bindings, and PHP 8 attribute-based injection.
 *
 * Provider encapsulation: every binding tracks its owner module so cross-module
 * access can be validated at resolution time.
 */
class Container
{
    /** @var array<string, array{factory: callable, singleton: bool}> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, string> Module owner for each binding (FQCN) */
    private array $bindingOwners = [];

    /** @var array<string, string[]> Exported bindings per module */
    private array $moduleExports = [];

    /** @var string|null The currently resolving module context */
    private ?string $resolvingModule = null;

    // ───────────────────────── Registration ─────────────────────────

    public function bind(string $abstract, callable|string $concrete, bool $singleton = false, ?string $module = null): void
    {
        if (is_string($concrete)) {
            $concrete = fn(Container $c) => $c->make($concrete);
        }

        $this->bindings[$abstract] = [
            'factory' => $concrete,
            'singleton' => $singleton,
        ];

        if ($module !== null) {
            $this->bindingOwners[$abstract] = $module;
        }
    }

    public function singleton(string $abstract, callable|string $concrete, ?string $module = null): void
    {
        $this->bind($abstract, $concrete, singleton: true, module: $module);
    }

    public function instance(string $abstract, object $instance, ?string $module = null): void
    {
        $this->instances[$abstract] = $instance;
        if ($module !== null) {
            $this->bindingOwners[$abstract] = $module;
        }
    }

    /** Register module exports so cross-module access can be verified. */
    public function registerModuleExports(string $moduleClass, array $exports): void
    {
        $this->moduleExports[$moduleClass] = $exports;
    }

    // ───────────────────────── Resolution ───────────────────────────

    /**
     * @template T
     * @param class-string<T> $abstract
     * @return T
     */
    public function make(string $abstract, array $overrides = [], ?string $callerModule = null): object
    {
        $this->validateModuleAccess($abstract, $callerModule);

        // Already a shared instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract]; // @phpstan-ignore-line
        }

        // Explicit binding
        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];
            $result = ($binding['factory'])($this, $overrides);

            if ($binding['singleton']) {
                $this->instances[$abstract] = $result;
            }

            return $result; // @phpstan-ignore-line
        }

        // Auto-resolve via reflection
        return $this->autoResolve($abstract, $overrides);
    }

    /** Resolve without module-access checking (used internally). */
    public function makeInternal(string $abstract, array $overrides = []): object
    {
        return $this->make($abstract, $overrides, callerModule: null);
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract]) || isset($this->bindings[$abstract]);
    }

    /** Set the current resolving module context (used by ModuleManager). */
    public function setResolvingModule(?string $module): void
    {
        $this->resolvingModule = $module;
    }

    // ───────────────────────── Auto-resolution ──────────────────────

    private function autoResolve(string $class, array $overrides): object
    {
        try {
            $ref = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new ContainerException("Cannot resolve [{$class}]: " . $e->getMessage(), 0, $e);
        }

        if (!$ref->isInstantiable()) {
            throw new ContainerException("Class [{$class}] is not instantiable. Did you forget to bind an interface?");
        }

        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $args = $this->resolveParameters($constructor->getParameters(), $overrides);

        return $ref->newInstanceArgs($args);
    }

    /**
     * @param ReflectionParameter[] $params
     */
    private function resolveParameters(array $params, array $overrides): array
    {
        $resolved = [];

        foreach ($params as $param) {
            $name = $param->getName();

            // Manual override
            if (array_key_exists($name, $overrides)) {
                $resolved[] = $overrides[$name];
                continue;
            }

            // #[Inject('key')] attribute
            $injectAttrs = $param->getAttributes(Inject::class);
            if (!empty($injectAttrs)) {
                $key = $injectAttrs[0]->newInstance()->key;
                $resolved[] = $this->resolveInjectKey($key);
                continue;
            }

            // Type-hinted class
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $resolved[] = $this->make($type->getName());
                continue;
            }

            // Optional parameter with default
            if ($param->isOptional()) {
                $resolved[] = $param->getDefaultValue();
                continue;
            }

            throw new ContainerException(
                "Cannot resolve parameter [{$name}] — no type hint, no default, no #[Inject]."
            );
        }

        return $resolved;
    }

    private function resolveInjectKey(string $key): mixed
    {
        // config.app.name → Config facade
        if (str_starts_with($key, 'config.')) {
            $configKey = substr($key, 7);
            /** @var Config\Repository $config */
            $config = $this->make(\Ironflow\Config\Repository::class);
            return $config->get($configKey);
        }

        // Named binding
        if ($this->has($key)) {
            return $this->make($key);
        }

        throw new ContainerException("Cannot resolve #[Inject('{$key}')]: no matching binding or config key.");
    }

    // ───────────────────────── Module access control ─────────────────

    private function validateModuleAccess(string $abstract, ?string $callerModule): void
    {
        $owner = $this->bindingOwners[$abstract] ?? null;

        if ($owner === null || $callerModule === null || $owner === $callerModule) {
            return;
        }

        $exports = $this->moduleExports[$owner] ?? [];

        if (!in_array($abstract, $exports, true)) {
            $short = class_basename($abstract);
            throw new ContainerException(
                "[{$short}] belongs to module [{$owner}] which is not exported for module [{$callerModule}]."
            );
        }
    }
}

// ── Tiny helper used in Container ──────────────────────────────────────────

function class_basename(string $class): string
{
    $parts = explode('\\', $class);
    return end($parts);
}
