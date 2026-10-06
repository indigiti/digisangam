<?php
declare(strict_types=1);

use DigiSangam\Agenda\SessionRepository;
use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Automation\WorkflowEngine;
use DigiSangam\Automation\WorkflowRepository;
use DigiSangam\Badges\BadgeTemplateRepository;
use DigiSangam\Communications\CampaignDispatchService;
use DigiSangam\Communications\CampaignRepository;
use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Credentials\CredentialService;
use DigiSangam\Notifications\NotificationOutbox;
use DigiSangam\OnGround\OfflineSnapshotService;
use DigiSangam\Payments\RazorpayCheckoutVerifier;
use DigiSangam\Payments\RazorpayWebhookVerifier;
use DigiSangam\Tickets\TicketRepository;
use DigiSangam\Venue\VenueRepository;

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
    $issuedAgain=$credentials->issue('TKT1','evt_001');
    expect($issued['token']===$issuedAgain['token'],'v2 credential is not stable for offline matching.');
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

    // Phase 2: automation execution and delayed actions.
    $outbox=new NotificationOutbox($store);
    $engine=new WorkflowEngine(new WorkflowRepository($store),$outbox);
    $run=$engine->fire('attendee.confirmed',[
        'event_id'=>'evt_001','attendee_id'=>'TKT1','email'=>'test@example.test','phone'=>'919999999999',
        'confirmation_token'=>'abc123',
    ]);
    expect($run['matched']===1,'Automation workflow did not match the confirmation trigger.');
    expect(count($store->read('notifications/outbox.json',[]))===2,'Automation did not queue both actions.');
    expect(count($outbox->pending(25))===1,'Delayed automation action became available too early.');

    // Phase 2: campaign segmentation queues only matching attendees.
    $store->write('attendees/index.json',[
        ['id'=>'TKT_A','event_id'=>'evt_001','name'=>'A','email'=>'a@example.test','phone'=>'911111111111','status'=>'Confirmed','category'=>'VIP','company'=>'Acme','confirmation_token'=>'tokA'],
        ['id'=>'TKT_B','event_id'=>'evt_001','name'=>'B','email'=>'b@example.test','phone'=>'922222222222','status'=>'Pending','category'=>'General','company'=>'Beta','confirmation_token'=>'tokB'],
    ]);
    $campaigns=new CampaignRepository($store);
    $campaign=$campaigns->create([
        'event_id'=>'evt_001','name'=>'VIP update','channel'=>'email','subject'=>'VIP','content'=>'Hello VIP',
        'segment'=>['status'=>'Confirmed','category'=>'VIP'],
    ]);
    $dispatch=(new CampaignDispatchService($campaigns,new AttendeeRepository($store),$outbox))->dispatch($campaign['id']);
    expect($dispatch['matched']===1 && $dispatch['queued']===1,'Campaign segment dispatch failed.');

    // Phase 2: offline snapshot contains the exact stable QR payload.
    $snapshot=(new OfflineSnapshotService(
        new AttendeeRepository($store),
        new TicketRepository($store),
        new VenueRepository($store),
        new SessionRepository($store),
        new BadgeTemplateRepository($store),
        'test-secret',
    ))->build('evt_001');
    expect(count($snapshot['credentials'])===1,'Offline snapshot did not include confirmed attendees only.');
    $offlinePayload=$snapshot['credentials'][0]['payload']??'';
    $expectedPayload=$credentials->issue('TKT_A','evt_001')['payload'];
    expect($offlinePayload===$expectedPayload,'Offline snapshot credential does not match attendee QR.');
    expect(!empty($snapshot['signature']),'Offline snapshot was not signed.');

    fwrite(STDOUT,"DigiSangam Phase 1 + Phase 2 smoke tests passed.\n");
}finally{
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $item) $item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
    @rmdir($root);
}
