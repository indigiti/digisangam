<?php
declare(strict_types=1);
use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Badges\BadgeTemplateRepository;
use DigiSangam\Badges\PrintJobRepository;
use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Credentials\CredentialService;
use DigiSangam\Operations\WorkerHeartbeatRepository;
use DigiSangam\Printing\BadgePrintWorker;
use DigiSangam\Printing\CupsPrintProvider;
use DigiSangam\Printing\LogPrintProvider;
use DigiSangam\Printing\RawTcpPrintProvider;
require dirname(__DIR__).'/app/bootstrap.php';
$store=new JsonFileStore(defined('DIGISANGAM_STORAGE_ROOT')?DIGISANGAM_STORAGE_ROOT:dirname(__DIR__).'/storage');
$providerName=strtolower(trim((string)getenv('DIGISANGAM_PRINT_PROVIDER')));
$provider=match($providerName){
    'raw_tcp'=>new RawTcpPrintProvider((string)getenv('PRINTER_HOST'),max(1,(int)(getenv('PRINTER_PORT')?:9100))),
    'cups'=>new CupsPrintProvider((string)getenv('PRINTER_QUEUE')),
    default=>new LogPrintProvider(),
};
$credentialSecret=trim((string)getenv('DIGISANGAM_CREDENTIAL_SECRET'));
if($credentialSecret===''){
    $record=$store->read('secrets/credential.json',[]);
    $credentialSecret=(string)($record['secret']??'');
    if($credentialSecret===''){
        $credentialSecret=bin2hex(random_bytes(32));
        $store->write('secrets/credential.json',['secret'=>$credentialSecret,'created_at'=>date(DATE_ATOM)]);
    }
}
$result=(new BadgePrintWorker(
    new PrintJobRepository($store),
    new AttendeeRepository($store),
    new BadgeTemplateRepository($store),
    $provider,
    new CredentialService($credentialSecret),
))->run((int)($argv[1]??20));
(new WorkerHeartbeatRepository($store))->beat('badge-print',$result,300);
fwrite(STDOUT,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
exit($result['failed']>0?2:0);
