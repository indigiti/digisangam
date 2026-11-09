<?php
declare(strict_types=1);

use DigiSangam\Auth\AuthService;
use DigiSangam\Auth\AuthenticationException;
use DigiSangam\Core\Storage\JsonFileStore;

require dirname(__DIR__).'/app/bootstrap.php';

$root=sys_get_temp_dir().'/digisangam-auth-p0-'.bin2hex(random_bytes(5));
mkdir($root,0700,true);

try {
    $auth=new AuthService(new JsonFileStore($root));
    $primary=$auth->setup('Primary Admin','primary@example.test','correct-password-2026');
    if($auth->user()['id']!==$primary['id']) throw new RuntimeException('Setup did not establish an authenticated session.');
    $secondary=$auth->createUser('Backup Admin','backup@example.test','backup-password-2026','super_admin');
    $auth->updateUser($primary['id'],['active'=>false]);
    if($auth->user()!==null) throw new RuntimeException('Deactivated users must lose their existing session.');
    try {
        $auth->requireUser();
        throw new RuntimeException('Deactivated user retained protected access.');
    } catch(AuthenticationException) {}
    if(!$auth->login('backup@example.test','backup-password-2026')) {
        throw new RuntimeException('Active backup administrator should still be able to sign in.');
    }
    if(($auth->user()['id']??'')!==$secondary['id']) throw new RuntimeException('Backup administrator session failed.');
    try {
        $auth->setup('Second Setup','other@example.test','other-password-2026');
        throw new RuntimeException('Second administrator setup unexpectedly succeeded.');
    } catch(RuntimeException $exception) {
        if($exception->getMessage()==='Second administrator setup unexpectedly succeeded.') throw $exception;
    }
    try {
        $auth->createUser('Duplicate','BACKUP@example.test','different-password-2026','viewer');
        throw new RuntimeException('Duplicate email unexpectedly succeeded.');
    } catch(InvalidArgumentException) {}
    echo "Authentication P0 security checks passed.\n";
} finally {
    $iterator=new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach($iterator as $item) {
        $item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
    }
    rmdir($root);
}
