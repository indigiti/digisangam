<?php
declare(strict_types=1);

namespace DigiSangam\Auth;

use DigiSangam\Core\Storage\JsonFileStore;

final class AuthService
{
    public function __construct(private readonly JsonFileStore $store)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('digisangam_session');
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'path' => '/',
            ]);
            session_start();
        }
    }

    public function setupRequired(): bool
    {
        return count($this->users()) === 0;
    }

    public function setup(string $name, string $email, string $password): array
    {
        if (!$this->setupRequired()) throw new \RuntimeException('Setup is already complete.');
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('A valid email is required.');
        if (strlen($password) < 10) throw new \InvalidArgumentException('Password must be at least 10 characters.');

        $user = [
            'id' => 'usr_' . bin2hex(random_bytes(6)),
            'name' => trim($name) ?: 'Administrator',
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'super_admin',
            'workspace_id' => 'ws_default',
            'created_at' => date(DATE_ATOM),
        ];
        $this->store->write('auth/users.json', [$user]);
        $this->authenticateSession($user);
        return $this->publicUser($user);
    }

    public function login(string $email, string $password): ?array
    {
        $email = strtolower(trim($email));
        foreach ($this->users() as $user) {
            if (hash_equals((string)$user['email'], $email) && password_verify($password, (string)$user['password_hash'])) {
                $this->authenticateSession($user);
                return $this->publicUser($user);
            }
        }
        return null;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }

    public function user(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) return null;
        foreach ($this->users() as $user) {
            if (($user['id'] ?? null) === $id) return $this->publicUser($user);
        }
        return null;
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
        return (string)$_SESSION['csrf'];
    }

    public function requireUser(): array
    {
        $user = $this->user();
        if (!$user) throw new AuthenticationException('Authentication required.');
        return $user;
    }

    public function requirePermission(string $permission): array
    {
        $user = $this->requireUser();
        if (!Authorization::allows((string)$user['role'], $permission)) {
            throw new AuthorizationException('Permission denied.');
        }
        return $user;
    }

    public function validateCsrf(): void
    {
        $sent = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $expected = (string)($_SESSION['csrf'] ?? '');
        if ($sent === '' || $expected === '' || !hash_equals($expected, $sent)) {
            throw new AuthorizationException('Invalid CSRF token.');
        }
    }

    private function users(): array
    {
        return $this->store->read('auth/users.json', []);
    }

    private function authenticateSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['workspace_id'] = $user['workspace_id'] ?? 'ws_default';
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }

    private function publicUser(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }
}
