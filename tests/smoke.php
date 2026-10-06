<?php
declare(strict_types=1);

use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Credentials\CredentialService;
use DigiSangam\Payments\RazorpayCheckoutVerifier;
use DigiSangam\Payments\RazorpayWebhookVerifier;
use DigiSangam\Tickets\TicketRepository;

require dirname(__DIR__) . '/app/bootstrap.php';

function expect(bool $condition,string $message): void {
    if(!$condition) throw new RuntimeException($message);
}

$root=sys_get_temp_dir().'/digisangam-smoke-'.bin2hex(random_bytes(5));
mkdir($root,0775,true);

try{
    $store=new JsonFileStore($root);
    $value=$store->transaction('counter.json',static function(array $data): array {
        $next=(int)($data['value']??0)+1;
        return ['data'=>['value'=>$next],'result'=>$next];
    },[]);
    expect($value===1,'JSON transaction result failed.');
    expect(($store->read('counter.json')['value']??0)===1,'JSON transaction persistence failed.');

    $credentials=new CredentialService('test-secret');
    $issued=$credentials->issue('TKT1','evt_001');
    expect(($credentials->verify($issued['token'])['attendee_id']??'')==='TKT1','Credential verification failed.');
    expect($credentials->verify($issued['token'].'tampered')===null,'Tampered credential was accepted.');

    $providerOrder='order_test';
    $payment='pay_test';
    $secret='rzp-secret';
    $signature=hash_hmac('sha256',$providerOrder.'|'.$payment,$secret);
    expect((new RazorpayCheckoutVerifier($secret))->verify($providerOrder,$payment,$signature),'Checkout signature verification failed.');

    $raw='{"event":"payment.captured"}';
    $webhookSecret='webhook-secret';
    $webhookSignature=hash_hmac('sha256',$raw,$webhookSecret);
    expect(((new RazorpayWebhookVerifier($webhookSecret))->verify($raw,$webhookSignature)['event']??'')==='payment.captured','Webhook signature verification failed.');

    $tickets=new TicketRepository($store);
    $before=$tickets->find('tic_5');
    $reserved=$tickets->reserveOne('tic_5','evt_001');
    expect((int)$reserved['sold']===(int)$before['sold']+1,'Ticket reservation failed.');
    $tickets->releaseOne('tic_5','evt_001');
    expect((int)$tickets->find('tic_5')['sold']===(int)$before['sold'],'Ticket release failed.');

    fwrite(STDOUT,"DigiSangam smoke tests passed.\n");
}finally{
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $item) $item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
    @rmdir($root);
}
