<?php

declare(strict_types=1);

namespace Ironflow\Filesystem;

use Ironflow\Application;

/**
 * Simple filesystem helper with multi-disk support.
 *
 * Usage:
 *   Storage::put('avatars/me.jpg', $contents);
 *   Storage::disk('public')->url('avatars/me.jpg');  // → /storage/avatars/me.jpg
 *   Storage::exists('avatars/me.jpg');
 *   Storage::delete('avatars/me.jpg');
 *   Storage::get('avatars/me.jpg');
 *
 * Disks are configured in config/filesystems.php.
 * Default disk: 'local' → storage/app
 * Public disk:  'public' → storage/app/public  (served via /storage symlink)
 */
class Storage
{
    private string $diskName;

    private function __construct(string $disk)
    {
        $this->diskName = $disk;
    }

    // ── Disk selection ───────────────────────────────────────────────

    public static function disk(string $name = 'local'): static
    {
        return new static($name);
    }

    /** @internal Called by static proxy methods — uses the default disk. */
    private static function default(): static
    {
        $diskName = 'local';
        try {
            $config   = Application::getInstance()->getContainer()->make(\Ironflow\Config\Repository::class);
            $diskName = (string) $config->get('filesystems.default', 'local');
        } catch (\Throwable) {
        }
        return new static($diskName);
    }

    // ── Static proxy methods (use default disk) ──────────────────────

    public static function put(string $path, mixed $contents): bool
    {
        return self::default()->write($path, $contents);
    }

    public static function get(string $path): string|false
    {
        return self::default()->read($path);
    }

    public static function exists(string $path): bool
    {
        return self::default()->has($path);
    }

    public static function delete(string $path): bool
    {
        return self::default()->remove($path);
    }

    public static function url(string $path): string
    {
        return self::default()->publicUrl($path);
    }

    public static function path(string $path = ''): string
    {
        return self::default()->absolutePath($path);
    }

    public static function files(string $directory = ''): array
    {
        return self::default()->listFiles($directory);
    }

    public static function makeDirectory(string $path): bool
    {
        return self::default()->mkdir($path);
    }

    // ── Instance methods (disk-specific) ─────────────────────────────

    public function write(string $path, mixed $contents): bool
    {
        $full = $this->fullPath($path);
        $dir  = dirname($full);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        return file_put_contents($full, $contents) !== false;
    }

    public function read(string $path): string|false
    {
        $full = $this->fullPath($path);
        return is_file($full) ? file_get_contents($full) : false;
    }

    public function has(string $path): bool
    {
        return file_exists($this->fullPath($path));
    }

    public function remove(string $path): bool
    {
        $full = $this->fullPath($path);
        return is_file($full) && @unlink($full);
    }

    public function publicUrl(string $path): string
    {
        $diskConfig = $this->diskConfig();
        $base       = rtrim((string) ($diskConfig['url'] ?? '/storage'), '/');
        return $base . '/' . ltrim($path, '/');
    }

    public function absolutePath(string $path = ''): string
    {
        return $this->fullPath($path);
    }

    public function listFiles(string $directory = '', bool $recursive = false): array
    {
        $base  = $this->fullPath($directory);
        $files = [];

        if (!is_dir($base)) {
            return [];
        }

        $pattern = $recursive ? $base . '/**/*' : $base . '/*';
        foreach (glob($pattern) ?: [] as $file) {
            if (is_file($file)) {
                $files[] = $file;
            }
        }

        return $files;
    }

    public function mkdir(string $path): bool
    {
        $full = $this->fullPath($path);
        return is_dir($full) || @mkdir($full, 0755, true);
    }

    // ── Internal ─────────────────────────────────────────────────────

    private function fullPath(string $path): string
    {
        $root = rtrim($this->diskConfig()['root'] ?? $this->defaultRoot(), '/');
        return $path !== '' ? $root . '/' . ltrim($path, '/') : $root;
    }

    private function diskConfig(): array
    {
        try {
            $config = Application::getInstance()->getContainer()->make(\Ironflow\Config\Repository::class);
            return (array) $config->get("filesystems.disks.{$this->diskName}", []);
        } catch (\Throwable) {
            return [];
        }
    }

    private function defaultRoot(): string
    {
        try {
            return Application::getInstance()->getBasePath("storage/app");
        } catch (\Throwable) {
            return sys_get_temp_dir() . '/ironflow';
        }
    }
}
