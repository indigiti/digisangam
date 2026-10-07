<?php
declare(strict_types=1);

use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Commerce\OrderExpiryService;
use DigiSangam\Commerce\OrderRepository;
use DigiSangam\Core\EventJournal\EventJournal;
use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Operations\WorkerHeartbeatRepository;
use DigiSangam\Tickets\TicketRepository;

require dirname(__DIR__).'/app/bootstrap.php';

$store=new JsonFileStore(defined('DIGISANGAM_STORAGE_ROOT')?DIGISANGAM_STORAGE_ROOT:dirname(__DIR__).'/storage');
$result=(new OrderExpiryService(
    new OrderRepository($store),
    new TicketRepository($store),
    new AttendeeRepository($store),
    new EventJournal($store),
))->run((int)($argv[1]??100));

(new WorkerHeartbeatRepository($store))->beat('order-expiry',$result,300);
fwrite(STDOUT,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
exit(0);
