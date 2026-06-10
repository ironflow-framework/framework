<?php

declare(strict_types=1);

namespace Ironflow\Auth;

use Ironflow\Database\Connection;
use Ironflow\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/**
 * JWT authentication guard (stateless, for APIs).
 * Reads the Bearer token from the Authorization header.
 */
class JwtGuard implements GuardInterface
{
    private ?object $user = null;
    private bool $resolved = false;

    public function __construct(
        private readonly Connection $db,
        private readonly array $config,
        private ?Request $request = null
    ) {
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
        $this->user = null;
        $this->resolved = false;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function user(): ?object
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $token = $this->request?->bearerToken();

        if ($token === null) {
            return null;
        }

        try {
            $secret = $this->config['secret'] ?? $_ENV['JWT_SECRET'] ?? '';
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            $table = $this->config['table'] ?? 'users';
            $row = $this->db->selectOne("SELECT * FROM {$table} WHERE id = ?", [$decoded->sub]);

            $this->user = $row ? (object) $row : null;
        } catch (Throwable) {
            $this->user = null;
        }

        return $this->user;
    }

    public function id(): int|string|null
    {
        return $this->user()?->id ?? null;
    }

    public function attempt(array $credentials): bool
    {
        return false; // JWT guard uses createToken() directly
    }

    public function login(object $user): void
    {
        $this->user = $user;
    }

    public function logout(): void
    {
        $this->user = null;
    }

    public function createToken(object $user, array $claims = []): string
    {
        $secret = $this->config['secret'] ?? $_ENV['JWT_SECRET'] ?? '';
        $ttl = (int) ($this->config['ttl'] ?? $_ENV['JWT_TTL'] ?? 3600);
        $now = time();

        $payload = array_merge([
            'iss' => $_ENV['APP_URL'] ?? 'ironflow',
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $now + $ttl,
        ], $claims);

        return JWT::encode($payload, $secret, 'HS256');
    }
}
