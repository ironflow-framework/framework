<?php

declare(strict_types=1);

namespace Core\Middleware;

use Core\Exceptions\HttpException;
use Core\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Simple in-memory rate limiter (APCu when available, file-based fallback).
 * Usage: ->middleware('throttle:60,1') = 60 requests per 1 minute.
 */
class ThrottleRequests
{
    public function handle(Request $request, callable $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $key = 'throttle:' . ($request->getClientIp() ?? 'unknown') . ':' . $request->getPathInfo();
        $ttl = $decayMinutes * 60;

        [$hits, $remaining] = $this->incrementAndCheck($key, $maxAttempts, $ttl);

        if ($hits > $maxAttempts) {
            throw new HttpException(429, 'Too Many Requests.');
        }

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit',     (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $remaining));

        return $response;
    }

    private function incrementAndCheck(string $key, int $max, int $ttl): array
    {
        if (function_exists('apcu_fetch')) {
            $hits = (int) apcu_fetch($key);
            if ($hits === 0) {
                apcu_store($key, 1, $ttl);
                return [1, $max - 1];
            }
            apcu_inc($key);
            $hits++;
            return [$hits, $max - $hits];
        }

        // File-based fallback
        $dir  = sys_get_temp_dir() . '/ironflow_throttle';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/' . md5($key) . '.json';
        $data = is_file($file) ? json_decode(file_get_contents($file), true) : null;

        $now = time();
        if (!$data || $data['expires_at'] < $now) {
            $data = ['hits' => 1, 'expires_at' => $now + $ttl];
        } else {
            $data['hits']++;
        }

        file_put_contents($file, json_encode($data));
        return [$data['hits'], $max - $data['hits']];
    }
}
