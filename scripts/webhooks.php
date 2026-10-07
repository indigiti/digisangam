<?php
declare(strict_types=1);
use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Operations\WorkerHeartbeatRepository;
use DigiSangam\Developer\WebhookOutboxRepository;
use DigiSangam\Developer\WebhookWorker;
require dirname(__DIR__).'/app/bootstrap.php';
$store=new JsonFileStore(defined('DIGISANGAM_STORAGE_ROOT')?DIGISANGAM_STORAGE_ROOT:dirname(__DIR__).'/storage');
$result=(new WebhookWorker(new WebhookOutboxRepository($store)))->run((int)($argv[1]??25));
(new WorkerHeartbeatRepository($store))->beat('webhooks',$result,300);
fwrite(STDOUT,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
exit($result['failed']>0?2:0);
