<?php

declare(strict_types=1);

namespace Ironflow\Console\Commands;

use Ironflow\Console\Command;

/**
 * Development server with auto-restart, colored request logging,
 * optional CSS watcher, and automatic port increment.
 *
 * Output format:
 *   [12:00:00]  GET      200    12ms  /
 *   [12:00:00]  POST     302     4ms  /login
 *   [12:00:00]  GET      404     2ms  /missing
 */
class ServeCommand extends Command
{
    protected string $signature   = 'serve {--host=localhost} {--port=8080} {--watch-css}';
    protected string $description = 'Start the development server with live request logging and auto-restart';

    // ── ANSI colour constants ────────────────────────────────────────
    private const RESET    = "\033[0m";
    private const BOLD     = "\033[1m";
    private const DIM      = "\033[2m";
    private const INDIGO   = "\033[38;5;105m";
    private const CYAN     = "\033[38;5;45m";
    private const GREEN    = "\033[38;5;82m";
    private const YELLOW   = "\033[38;5;220m";
    private const RED      = "\033[38;5;196m";
    private const GREY     = "\033[38;5;244m";
    private const WHITE    = "\033[38;5;255m";
    private const ORANGE   = "\033[38;5;208m";
    private const BG_INDIGO = "\033[48;5;57m";
    private const BG_CYAN   = "\033[48;5;31m";
    private const BG_YELLOW = "\033[48;5;136m";
    private const BG_RED    = "\033[48;5;88m";
    private const BG_GREY   = "\033[48;5;238m";

    private bool $running = true;
    /** @var resource|null */
    private $cssProcess = null;

    protected function handle(): int
    {
        $host     = (string) ($this->option('host') ?? 'localhost');
        $port     = (int)    ($this->option('port') ?? 8080);
        $watchCss = (bool)   $this->option('watch-css');
        $root     = base_path('public');
        $router   = base_path('server.php');

        // Auto-increment port if already in use
        $port = $this->findFreePort($host, $port);

        $this->setupSignalHandlers();

        if ($watchCss) {
            $this->startCssWatcher();
        }

        $attempt = 0;
        $url     = "http://{$host}:{$port}";
        $this->printBanner($url, $root, $watchCss);

        while ($this->running) {
            $crashed = $this->startServer($host, (string) $port, $root, $router);

            if (!$this->running) {
                break; // clean Ctrl+C shutdown
            }

            // Server crashed unexpectedly
            $attempt++;
            $delay = min(10, 2 ** min($attempt - 1, 3)); // 1 → 2 → 4 → 8 → 10 s
            echo PHP_EOL
                . self::RED . self::BOLD . '  ✖ Server crashed'
                . self::RESET . self::GREY . " (exit code {$crashed})"
                . self::RESET . PHP_EOL;
            echo self::YELLOW
                . "  ↻ Restarting in {$delay}s… (attempt #{$attempt})"
                . self::RESET . PHP_EOL . PHP_EOL;

            for ($i = 0; $i < $delay; $i++) {
                sleep(1);
            }
        }

        $this->stopCssWatcher();

        echo PHP_EOL . self::DIM . '  Server stopped.' . self::RESET . PHP_EOL;
        return self::SUCCESS;
    }

    // ── Signal handling ──────────────────────────────────────────────

    private function setupSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        $stop = function () {
            $this->running = false;
        };

        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT,  $stop);
        pcntl_signal(SIGHUP,  $stop);
    }

    // ── Port finder ──────────────────────────────────────────────────

    private function findFreePort(string $host, int $port): int
    {
        while ($port < 65535) {
            $socket = @fsockopen($host, $port, $errno, $errstr, 0.1);
            if ($socket === false) {
                return $port; // port is free
            }
            fclose($socket);
            echo self::YELLOW . "  Port {$port} is in use, trying " . ($port + 1) . '…' . self::RESET . PHP_EOL;
            $port++;
        }
        return $port;
    }

    // ── CSS watcher ──────────────────────────────────────────────────

    private function startCssWatcher(): void
    {
        $skeletonRoot = dirname(base_path()); // project root (skeleton/)
        if (!file_exists("{$skeletonRoot}/package.json")) {
            echo self::YELLOW . '  --watch-css: package.json not found, skipping CSS watcher.' . self::RESET . PHP_EOL;
            return;
        }

        $cmd  = 'npm run dev 2>&1';
        $desc = [1 => ['pipe', 'w']];
        $proc = proc_open($cmd, $desc, $pipes, $skeletonRoot);
        if (is_resource($proc)) {
            $this->cssProcess = $proc;
            stream_set_blocking($pipes[1], false);
            echo self::CYAN . '  CSS watcher started (npm run dev).' . self::RESET . PHP_EOL;
        }
    }

    private function stopCssWatcher(): void
    {
        if ($this->cssProcess !== null && is_resource($this->cssProcess)) {
            proc_terminate($this->cssProcess);
            proc_close($this->cssProcess);
            $this->cssProcess = null;
        }
    }

    // ── Server process ───────────────────────────────────────────────

    /**
     * Run the PHP built-in server. Returns the exit code when it stops.
     * Returns 0 on a clean shutdown ($this->running = false), non-zero on crash.
     *
     * @phpstan-impure
     */
    private function startServer(string $host, string $port, string $root, string $router): int
    {
        $cmd = sprintf('php -S %s:%s -t "%s" "%s"', $host, $port, $root, $router);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            $this->error('Failed to start PHP built-in server.');
            return 1;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $pending = [];

        while ($this->running) {
            $read   = [$pipes[1], $pipes[2]];
            $write  = null;
            $except = null;

            $changed = stream_select($read, $write, $except, 0, 100_000);

            if ($changed === false) {
                break;
            }

            foreach ($read as $stream) {
                $raw = fgets($stream);
                if ($raw === false) {
                    continue;
                }
                $raw = rtrim($raw);
                if ($raw === '') {
                    continue;
                }
                $this->parseLine($raw, $pending);
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = $status['exitcode'];
                proc_close($process);
                return $exitCode;
            }
        }

        // Clean shutdown: terminate the child
        proc_terminate($process, defined('SIGTERM') ? SIGTERM : 15);

        // Drain remaining output
        while (($line = fgets($pipes[2])) !== false) {
            // discard
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return 0;
    }

    // ── Line parser ──────────────────────────────────────────────────

    private function parseLine(string $line, array &$pending): void
    {
        $clean = preg_replace('/^\[.*?\]\s*/', '', $line) ?? $line;

        // Request log: "ip:port [status]: METHOD /uri"
        if (preg_match(
            '/^(\[?[\da-fA-F:.]+\]?):?(\d+)\s+\[(\d+)\]:\s+(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)\s+(.+)$/',
            $clean,
            $m
        )) {
            [, $ip, $clientPort, $status, $method, $uri] = $m;
            $key   = "{$ip}:{$clientPort}";
            $start = $pending[$key] ?? microtime(true);
            unset($pending[$key]);
            $ms = (int) round((microtime(true) - $start) * 1000);
            $path = strtok($uri, '?') ?: $uri;
            $this->printRequest($method, (int) $status, $ms, $path, $ip);
            return;
        }

        // "Accepted" → record start time
        if (preg_match('/^(\[?[\da-fA-F:.]+\]?):?(\d+)\s+Accepted$/', $clean, $m)) {
            $pending["{$m[1]}:{$m[2]}"] = microtime(true);
            return;
        }

        if (str_contains($clean, 'Closing')) {
            return;
        }

        // PHP fatal / parse errors
        if (str_contains($line, 'PHP Fatal') || str_contains($line, 'PHP Parse error')) {
            echo self::RED . '  [PHP ERROR] ' . self::RESET . self::DIM . $clean . self::RESET . PHP_EOL;
            return;
        }

        // "Address already in use" — port conflict
        if (str_contains($line, 'Address already in use')) {
            echo self::RED . '  Address already in use. Use --port=XXXX to specify another port.' . self::RESET . PHP_EOL;
            $this->running = false;
            return;
        }

        // Startup messages
        if (
            str_contains($line, 'Development Server') ||
            str_contains($line, 'started')            ||
            str_contains($line, 'Listening on')
        ) {
            echo self::DIM . self::GREY . '  ' . $clean . self::RESET . PHP_EOL;
            return;
        }

        // Dim passthrough
        echo self::DIM . '  ' . $clean . self::RESET . PHP_EOL;
    }

    // ── Request line formatter ───────────────────────────────────────

    private function printRequest(
        string $method,
        int    $status,
        int    $ms,
        string $uri,
        string $ip
    ): void {
        $time   = date('H:i:s');
        $padUri = mb_strimwidth($uri, 0, 50, '…');

        [$bgMethod] = $this->methodColour($method);
        $methodBadge = $bgMethod . self::BOLD . self::WHITE
            . ' ' . str_pad($method, 7) . ' '
            . self::RESET;

        $statusColour = $this->statusColour($status);
        $statusStr    = $statusColour . self::BOLD . str_pad((string) $status, 5) . self::RESET;

        $latencyColour = match (true) {
            $ms < 50  => self::GREEN,
            $ms < 200 => self::YELLOW,
            default   => self::RED,
        };
        $latencyStr = $latencyColour . str_pad("{$ms}ms", 8, ' ', STR_PAD_LEFT) . self::RESET;

        $pathColour = match (true) {
            $status >= 500 => self::RED,
            $status >= 400 => self::ORANGE,
            $status >= 300 => self::CYAN,
            default        => self::WHITE,
        };
        $pathStr = $pathColour . $padUri . self::RESET;
        $ipStr   = self::DIM . self::GREY . $ip . self::RESET;

        echo sprintf(
            "  %s  %s  %s  %s  %s   %s\n",
            self::DIM . self::GREY . "[{$time}]" . self::RESET,
            $methodBadge,
            $statusStr,
            $latencyStr,
            $pathStr,
            $ipStr
        );
    }

    // ── Banner ───────────────────────────────────────────────────────

    private function printBanner(string $url, string $root, bool $watchCss): void
    {
        $line = str_repeat('─', 56);

        echo PHP_EOL;
        echo self::BOLD . self::INDIGO . "  ⚡ IronFlow" . self::RESET . self::WHITE . "  Dev Server" . self::RESET . PHP_EOL;
        echo self::GREY . "  {$line}" . self::RESET . PHP_EOL;
        echo self::GREY . "  Local:    " . self::RESET . self::CYAN . self::BOLD . $url . self::RESET . PHP_EOL;
        echo self::GREY . "  Root:     " . self::RESET . self::DIM . $root . self::RESET . PHP_EOL;
        echo self::GREY . "  Engine:   " . self::RESET . self::DIM . 'PHP ' . PHP_VERSION . self::RESET . PHP_EOL;
        if ($watchCss) {
            echo self::GREY . "  CSS:      " . self::RESET . self::GREEN . '⟳ watching' . self::RESET . PHP_EOL;
        }
        echo self::GREY . "  {$line}" . self::RESET . PHP_EOL;
        echo self::DIM . "  Press Ctrl+C to stop." . self::RESET . PHP_EOL . PHP_EOL;
    }

    // ── Colour maps ──────────────────────────────────────────────────

    /** @return array{string, string} [background, foreground] */
    private function methodColour(string $method): array
    {
        return match ($method) {
            'GET'          => [self::BG_INDIGO, self::WHITE],
            'POST'         => [self::BG_CYAN,   self::WHITE],
            'PUT', 'PATCH' => [self::BG_YELLOW, self::WHITE],
            'DELETE'       => [self::BG_RED,    self::WHITE],
            default        => [self::BG_GREY,   self::WHITE],
        };
    }

    private function statusColour(int $status): string
    {
        return match (true) {
            $status >= 500 => self::RED,
            $status >= 400 => self::ORANGE,
            $status >= 300 => self::CYAN,
            $status >= 200 => self::GREEN,
            default        => self::GREY,
        };
    }
}