<?php
declare(strict_types=1);
use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Badges\BadgeTemplateRepository;
use DigiSangam\Badges\PrintJobRepository;
use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Printing\BadgePrintWorker;
use DigiSangam\Printing\LogPrintProvider;
use DigiSangam\Printing\RawTcpPrintProvider;
require dirname(__DIR__).'/app/bootstrap.php';
$store=new JsonFileStore(defined('DIGISANGAM_STORAGE_ROOT')?DIGISANGAM_STORAGE_ROOT:dirname(__DIR__).'/storage');
$provider=strtolower(trim((string)getenv('DIGISANGAM_PRINT_PROVIDER')))==='raw_tcp'
    ? new RawTcpPrintProvider((string)getenv('PRINTER_HOST'),max(1,(int)(getenv('PRINTER_PORT')?:9100)))
    : new LogPrintProvider();
$result=(new BadgePrintWorker(new PrintJobRepository($store),new AttendeeRepository($store),new BadgeTemplateRepository($store),$provider))->run((int)($argv[1]??20));
fwrite(STDOUT,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
exit($result['failed']>0?2:0);
