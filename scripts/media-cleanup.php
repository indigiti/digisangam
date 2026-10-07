<?php
declare(strict_types=1);

use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Media\MediaCleanupService;
use DigiSangam\Media\MediaRepository;
use DigiSangam\Operations\WorkerHeartbeatRepository;

require dirname(__DIR__).'/app/bootstrap.php';

$store=new JsonFileStore(defined('DIGISANGAM_STORAGE_ROOT')?DIGISANGAM_STORAGE_ROOT:dirname(__DIR__).'/storage');
$result=(new MediaCleanupService($store,new MediaRepository($store)))->run(
    (int)(getenv('DIGISANGAM_MEDIA_ORPHAN_TTL_SECONDS')?:86400),
    (int)($argv[1]??200),
);
(new WorkerHeartbeatRepository($store))->beat('media-cleanup',$result,3600);
fwrite(STDOUT,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
exit(0);
