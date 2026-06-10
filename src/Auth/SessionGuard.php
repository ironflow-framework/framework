<?php

declare(strict_types=1);

namespace Ironflow\Auth;

use Ironflow\Database\Connection;
use Ironflow\Session\SessionManager;

/**
 * Session-based authentication guard.
 * Stores the authenticated user id in the session.
 */
class SessionGuard implements GuardInterface
{
    private ?object $user = null;

    public function __construct(
        private readonly SessionManager $session,
        private readonly Connection $db,
        private readonly array $config
    ) {
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function user(): ?object
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $id = $this->session->get('auth_user_id');
        if ($id === null) {
            return null;
        }

        $table = $this->config['table'] ?? 'users';
        $row = $this->db->selectOne("SELECT * FROM {$table} WHERE id = ?", [$id]);

        if ($row === null) {
            $this->session->forget('auth_user_id');
            return null;
        }

        $this->user = (object) $row;
        return $this->user;
    }

    public function id(): int|string|null
    {
        return $this->session->get('auth_user_id');
    }

    public function attempt(array $credentials): bool
    {
        $table = $this->config['table'] ?? 'users';
        $username = $this->config['username'] ?? 'email';

        $row = $this->db->selectOne(
            "SELECT * FROM {$table} WHERE {$username} = ?",
            [$credentials[$username] ?? '']
        );

        if ($row === null) {
            return false;
        }

        if (!Hash::verify($credentials['password'] ?? '', $row['password'])) {
            return false;
        }

        $this->login((object) $row);
        return true;
    }

    public function login(object $user): void
    {
        $this->session->put('auth_user_id', $user->id);
        $this->session->regenerate();
        $this->user = $user;
    }

    public function logout(): void
    {
        $this->session->forget('auth_user_id');
        $this->session->regenerate();
        $this->user = null;
    }
}
