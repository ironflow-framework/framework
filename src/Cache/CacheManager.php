<?php

declare(strict_types=1);

namespace Ironflow\Cache;

/**
 * Simple file-based cache. Uses APCu when available, otherwise stores
 * serialized PHP in storage/cache/app/.
 */
class CacheManager
{
    public function __construct(private readonly string $path)
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (function_exists('apcu_fetch')) {
            $value = apcu_fetch($key, $success);
            return $success ? $value : $default;
        }

        $file = $this->filepath($key);
        if (!is_file($file)) {
            return $default;
        }

        $data = unserialize((string) file_get_contents($file));
        if ($data['expires'] !== 0 && $data['expires'] < time()) {
            unlink($file);
            return $default;
        }

        return $data['value'];
    }

    public function put(string $key, mixed $value, int $ttl = 3600): void
    {
        if (function_exists('apcu_store')) {
            apcu_store($key, $value, $ttl);
            return;
        }

        file_put_contents($this->filepath($key), serialize([
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ]));
    }

    public function has(string $key): bool
    {
        return $this->get($key, '__MISS__') !== '__MISS__';
    }

    public function forget(string $key): void
    {
        if (function_exists('apcu_delete')) {
            apcu_delete($key);
        }
        $file = $this->filepath($key);
        if (is_file($file)) {
            unlink($file);
        }
    }

    public function flush(): void
    {
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
        foreach (glob($this->path . '/*.cache') ?: [] as $file) {
            unlink($file);
        }
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }
        $value = $callback();
        $this->put($key, $value, $ttl);
        return $value;
    }

    private function filepath(string $key): string
    {
        return $this->path . '/' . md5($key) . '.cache';
    }
}
