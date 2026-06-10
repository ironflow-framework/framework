<?php

declare(strict_types=1);

namespace Core\Auth;

use Core\Database\Connection;
use Core\Session\SessionManager;

/**
 * Manages multiple auth guards (session + jwt).
 * Proxies calls to the default guard (typically 'session' for web).
 */
class AuthManager
{
    /** @var array<string, GuardInterface> */
    private array $guards = [];

    private string $defaultGuard;

    public function __construct(
        private readonly Connection $db,
        private readonly SessionManager $session,
        private readonly array $config
    ) {
        $this->defaultGuard = $config['defaults']['guard'] ?? 'session';
    }

    public function guard(string $name = null): GuardInterface
    {
        $name ??= $this->defaultGuard;

        if (!isset($this->guards[$name])) {
            $this->guards[$name] = $this->createGuard($name);
        }

        return $this->guards[$name];
    }

    public function check(): bool
    {
        return $this->guard()->check();
    }

    public function user(): ?object
    {
        return $this->guard()->user();
    }

    public function id(): int|string|null
    {
        return $this->guard()->id();
    }

    public function attempt(array $credentials): bool
    {
        return $this->guard()->attempt($credentials);
    }

    public function login(object $user): void
    {
        $this->guard()->login($user);
    }

    public function logout(): void
    {
        $this->guard()->logout();
    }

    public function createToken(object $user, array $claims = []): string
    {
        $guard = $this->guard('jwt');
        if ($guard instanceof JwtGuard) {
            return $guard->createToken($user, $claims);
        }
        throw new \RuntimeException('JWT guard not configured.');
    }

    private function createGuard(string $name): GuardInterface
    {
        $guardConfig = $this->config['guards'][$name] ?? [];

        return match ($guardConfig['driver'] ?? $name) {
            'session' => new SessionGuard($this->session, $this->db, $guardConfig),
            'jwt'     => new JwtGuard($this->db, $guardConfig),
            default   => throw new \InvalidArgumentException("Unknown auth guard driver [{$name}]."),
        };
    }
}
