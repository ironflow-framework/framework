<?php

declare(strict_types=1);

namespace IronFlow\Auth\Guards;

use IronFlow\Auth\Contracts\GuardInterface;
use IronFlow\Database\Model;
use IronFlow\Support\Security\Hasher;

class SessionGuard implements GuardInterface
{
    private ?Model $user = null;
    private array $config;
    private string $userModel;
    private string $sessionKey;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->userModel = $config['model'];
        $this->sessionKey = $config['session_key'];
    }

    public function attempt(array $credentials): bool
    {
        if ($this->validate($credentials)) {
            $user = $this->retrieveByCredentials($credentials);
            $this->login($user);
            return true;
        }
        return false;
    }

    public function login(Model $user): void
    {
        $this->user = $user;
        session()->set($this->sessionKey, $user->id);
        session()->regenerate();
    }

    public function logout(): void
    {
        $this->user = null;
        session()->remove($this->sessionKey);
        session()->regenerate();
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function user(): ?Model
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $id = session()->get($this->sessionKey);
        if ($id === null) {
            return null;
        }

        $this->user = $this->retrieveById($id);
        return $this->user;
    }

    public function id(): ?int
    {
        return $this->user()?->id;
    }

    public function validate(array $credentials): bool
    {
        $user = $this->retrieveByCredentials($credentials);
        if (!$user) {
            return false;
        }

        return $this->hasValidCredentials($user, $credentials);
    }

    protected function retrieveById(int $id): ?Model
    {
        return $this->createModel()->find($id);
    }

    protected function retrieveByCredentials(array $credentials): ?Model
    {
        if (empty($credentials) || !isset($credentials['email'])) {
            return null;
        }

        return $this->createModel()::where('email', $credentials['email'])->first();
    }

    protected function hasValidCredentials(Model $user, array $credentials): bool
    {
        return Hasher::verify(
            $credentials['password'],
            $user->password
        );
    }

    protected function createModel(): Model
    {
        $class = '\\' . ltrim($this->userModel, '\\');
        return new $class;
    }
}
