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
            'active' => true,
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
            if (($user['active']??true)!==true) continue;
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

    public function allUsers(): array
    {
        return array_map(fn(array $user): array => $this->publicUser($user),$this->users());
    }

    public function createUser(string $name,string $email,string $password,string $role): array
    {
        $name=trim($name);
        $email=strtolower(trim($email));
        $role=trim($role);
        if($name==='') throw new \InvalidArgumentException('Name is required.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('A valid email is required.');
        if(strlen($password)<10) throw new \InvalidArgumentException('Password must be at least 10 characters.');
        if(!in_array($role,Authorization::roles(),true)) throw new \InvalidArgumentException('Invalid role.');
        $users=$this->users();
        foreach($users as $user) if(strtolower((string)($user['email']??''))===$email) throw new \InvalidArgumentException('A user with this email already exists.');
        $record=[
            'id'=>'usr_'.bin2hex(random_bytes(6)),
            'name'=>$name,
            'email'=>$email,
            'password_hash'=>password_hash($password,PASSWORD_DEFAULT),
            'role'=>$role,
            'active'=>true,
            'workspace_id'=>'ws_default',
            'created_at'=>date(DATE_ATOM),
        ];
        $users[]=$record;
        $this->store->write('auth/users.json',$users);
        return $this->publicUser($record);
    }

    public function updateUser(string $id,array $input): ?array
    {
        $users=$this->users();$updated=null;
        foreach($users as &$user){
            if(($user['id']??'')!==$id) continue;
            $nextRole=array_key_exists('role',$input)?trim((string)$input['role']):(string)($user['role']??'viewer');
            $nextActive=array_key_exists('active',$input)?(bool)$input['active']:(bool)($user['active']??true);
            if(!in_array($nextRole,Authorization::roles(),true)) throw new \InvalidArgumentException('Invalid role.');
            if(($user['role']??'')==='super_admin' && (!$nextActive||$nextRole!=='super_admin')){
                $otherAdmins=array_filter($users,static fn(array $row): bool =>
                    ($row['id']??'')!==$id && ($row['role']??'')==='super_admin' && ($row['active']??true)===true
                );
                if($otherAdmins===[]) throw new \RuntimeException('At least one active super administrator is required.');
            }
            if(array_key_exists('name',$input)){
                $name=trim((string)$input['name']);
                if($name==='') throw new \InvalidArgumentException('Name is required.');
                $user['name']=$name;
            }
            $user['role']=$nextRole;
            $user['active']=$nextActive;
            if(!empty($input['password'])){
                $password=(string)$input['password'];
                if(strlen($password)<10) throw new \InvalidArgumentException('Password must be at least 10 characters.');
                $user['password_hash']=password_hash($password,PASSWORD_DEFAULT);
            }
            $user['updated_at']=date(DATE_ATOM);
            $updated=$this->publicUser($user);
            break;
        }
        unset($user);
        if($updated!==null) $this->store->write('auth/users.json',$users);
        return $updated;
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
