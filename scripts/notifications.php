<?php
declare(strict_types=1);

use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Notifications\NotificationOutbox;
use DigiSangam\Notifications\NotificationProviderFactory;
use DigiSangam\Notifications\NotificationTemplateRenderer;
use DigiSangam\Notifications\NotificationWorker;

require dirname(__DIR__) . '/app/bootstrap.php';

$store=new JsonFileStore(defined('DIGISANGAM_STORAGE_ROOT') ? DIGISANGAM_STORAGE_ROOT : dirname(__DIR__).'/storage');
$worker=new NotificationWorker(
    new NotificationOutbox($store),
    new NotificationTemplateRenderer(),
    NotificationProviderFactory::email(),
    NotificationProviderFactory::whatsapp(),
);
$result=$worker->run((int)($argv[1]??25));
fwrite(STDOUT,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
exit($result['failed']>0?2:0);
