<?php
declare(strict_types=1);

use DigiSangam\Accreditation\AccreditationRepository;
use DigiSangam\Core\EventJournal\EventJournal;
use DigiSangam\Credentials\CredentialBindingRepository;
use DigiSangam\Developer\ApiKeyRepository;
use DigiSangam\Developer\WebhookOutboxRepository;
use DigiSangam\Developer\WebhookRepository;
use DigiSangam\Intelligence\EventBlueprintService;
use DigiSangam\Media\MediaRepository;
use DigiSangam\Notifications\LogSmsProvider;
use DigiSangam\OnGround\WalkInRegistrationService;
use DigiSangam\Printing\BadgePrintWorker;
use DigiSangam\Printing\LogPrintProvider;
use DigiSangam\Reports\OperationalReportService;
use DigiSangam\Wallet\WalletPassRepository;
use DigiSangam\Wallet\WalletPassService;
use DigiSangam\Agenda\SessionAccessService;
use DigiSangam\Agenda\SessionAttendanceRepository;
use DigiSangam\Agenda\SessionRepository;
use DigiSangam\Analytics\AnalyticsService;
use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Automation\WorkflowEngine;
use DigiSangam\Automation\WorkflowRepository;
use DigiSangam\Badges\BadgeTemplateRepository;
use DigiSangam\Badges\PrintJobRepository;
use DigiSangam\Commerce\OrderRepository;
use DigiSangam\Communications\CampaignDispatchService;
use DigiSangam\Communications\CampaignRepository;
use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Credentials\CredentialService;
use DigiSangam\Events\EventRepository;
use DigiSangam\Exhibitors\ExhibitorRepository;
use DigiSangam\Exhibitors\LeadRepository;
use DigiSangam\Exhibitors\MeetingRepository;
use DigiSangam\Intelligence\ActionProposalRepository;
use DigiSangam\Intelligence\ApprovedActionService;
use DigiSangam\Intelligence\EventGraphBuilder;
use DigiSangam\Intelligence\IntelligenceClient;
use DigiSangam\Intelligence\LocalIntelligenceEngine;
use DigiSangam\Invitations\InvitationRepository;
use DigiSangam\Notifications\NotificationOutbox;
use DigiSangam\Notifications\NotificationTemplateRenderer;
use DigiSangam\Notifications\NotificationWorker;
use DigiSangam\Notifications\LogEmailProvider;
use DigiSangam\Notifications\LogWhatsAppProvider;
use DigiSangam\OnGround\AccessEventRepository;
use DigiSangam\OnGround\AccessPolicyService;
use DigiSangam\OnGround\CheckinRepository;
use DigiSangam\OnGround\OfflineSnapshotService;
use DigiSangam\OnGround\ScannerService;
use DigiSangam\Payments\RazorpayCheckoutVerifier;
use DigiSangam\Payments\RazorpayWebhookVerifier;
use DigiSangam\Registration\RegistrationRepository;
use DigiSangam\Tickets\TicketRepository;
use DigiSangam\Venue\SeatAssignmentRepository;
use DigiSangam\Venue\VenueRepository;

require dirname(__DIR__) . '/app/bootstrap.php';

function expect(bool $condition,string $message): void {
    if(!$condition) throw new RuntimeException($message);
}

function removeTree(string $root): void {
    if(!is_dir($root)) return;
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $item) $item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
    @rmdir($root);
}

$root=sys_get_temp_dir().'/digisangam-audit-'.bin2hex(random_bytes(5));
mkdir($root,0775,true);

try{
    $store=new JsonFileStore($root);

    // Empty install must be genuinely empty: no demo records.
    expect((new EventRepository($store))->all()===[],'Fresh install contains demo events.');
    expect((new AttendeeRepository($store))->all()===[],'Fresh install contains demo attendees.');
    expect((new TicketRepository($store))->all()===[],'Fresh install contains demo tickets.');
    expect((new SessionRepository($store))->all()===[],'Fresh install contains demo sessions.');
    expect((new BadgeTemplateRepository($store))->all()===[],'Fresh install contains demo badges.');
    expect((new ExhibitorRepository($store))->all()===[],'Fresh install contains demo exhibitors.');
    expect((new WorkflowRepository($store))->all()===[],'Fresh install contains demo workflows.');

    // Atomic JSON transaction.
    $value=$store->transaction('counter.json',static function(array $data): array {
        $next=(int)($data['value']??0)+1;
        return ['data'=>['value'=>$next],'result'=>$next];
    },[]);
    expect($value===1 && ($store->read('counter.json')['value']??0)===1,'JSON transaction failed.');

    // Build a real event from zero state.
    $events=new EventRepository($store);
    $event=$events->create([
        'name'=>'Audit Summit 2027',
        'description'=>'End-to-end audit fixture',
        'type'=>'Conference',
        'category'=>'Technology',
        'format'=>'in_person',
        'start_date'=>'2027-01-10',
        'end_date'=>'2027-01-11',
        'location'=>'Pune, India',
        'venue_name'=>'Audit Convention Centre',
        'currency'=>'INR',
        'privacy'=>'public',
        'branding'=>[
            'brand_name'=>'Audit Summit',
            'primary_color'=>'#4f46e5',
            'secondary_color'=>'#06b6d4',
            'background_color'=>'#0f172a',
        ],
        'organizer'=>['name'=>'Audit Organizer','email'=>'organizer@example.test'],
    ]);
    $eventId=(string)$event['id'];
    expect($eventId!=='' && ($event['branding']['brand_name']??'')==='Audit Summit','Event creation/branding failed.');

    $registration=new RegistrationRepository($store);
    $schema=$registration->save($eventId,[
        'title'=>'Audit Registration',
        'approval_mode'=>'auto',
        'categories'=>['General','VIP'],
        'fields'=>[
            ['id'=>'fld_name','label'=>'Full Name','type'=>'text','required'=>true,'visibility'=>'always'],
            ['id'=>'fld_email','label'=>'Email','type'=>'email','required'=>true,'visibility'=>'always'],
            ['id'=>'fld_category','label'=>'Category','type'=>'select','required'=>true,'visibility'=>'always'],
        ],
    ]);
    expect($schema['event_id']===$eventId && count($schema['categories'])===2,'Registration schema failed.');

    $invalidChoiceBlocked=false;
    try{
        $registration->save($eventId,[
            'fields'=>[
                ['id'=>'fld_name','label'=>'Full Name','type'=>'text','required'=>true,'visibility'=>'always'],
                ['id'=>'fld_email','label'=>'Email','type'=>'email','required'=>true,'visibility'=>'always'],
                ['id'=>'fld_category','label'=>'Category','type'=>'select','required'=>true,'visibility'=>'always'],
                ['id'=>'fld_bad','label'=>'Broken Choice','type'=>'select','required'=>false,'visibility'=>'always'],
            ],
        ]);
    }catch(InvalidArgumentException){$invalidChoiceBlocked=true;}
    expect($invalidChoiceBlocked,'Choice field without options was accepted.');

    $venueRepo=new VenueRepository($store);
    $venue=$venueRepo->save($eventId,[
        'name'=>'Audit Convention Centre',
        'address'=>'Pune, India',
        'zones'=>[
            ['id'=>'zone_general','name'=>'General','capacity'=>100,'categories'=>['General','VIP']],
            ['id'=>'zone_vip','name'=>'VIP','capacity'=>20,'categories'=>['VIP']],
        ],
        'seating'=>[['id'=>'hall_main','name'=>'Main Hall','type'=>'reserved','rows'=>10,'seats_per_row'=>10]],
    ]);
    expect(count($venue['zones'])===2,'Venue setup failed.');

    $tickets=new TicketRepository($store);
    $ticket=$tickets->create(['event_id'=>$eventId,'name'=>'General Admission','price'=>2500,'quantity'=>50,'status'=>'Active']);
    expect(($ticket['event_id']??'')===$eventId,'Ticket was not event-scoped.');
    $reserved=$tickets->reserveOne($ticket['id'],$eventId);
    expect((int)$reserved['sold']===1,'Ticket reservation failed.');
    $tickets->releaseOne($ticket['id'],$eventId);
    expect((int)$tickets->find($ticket['id'])['sold']===0,'Ticket release failed.');

    $attendees=new AttendeeRepository($store);
    $vip=$attendees->create([
        'event_id'=>$eventId,'name'=>'Vip User','email'=>'vip@example.test','phone'=>'919999999999',
        'category'=>'VIP','company'=>'Acme','status'=>'Confirmed','ticket_id'=>$ticket['id'],
    ]);
    $general=$attendees->create([
        'event_id'=>$eventId,'name'=>'General User','email'=>'general@example.test',
        'category'=>'General','company'=>'Beta','status'=>'Pending','ticket_id'=>$ticket['id'],
    ]);
    expect(count($attendees->all())===2,'Attendee creation failed.');

    $invite=(new InvitationRepository($store))->create(['event_id'=>$eventId,'email'=>'invite@example.test','category'=>'VIP']);
    expect(($invite['event_id']??'')===$eventId,'Invitation is not event-scoped.');

    $orders=new OrderRepository($store);
    $order=$orders->create([
        'event_id'=>$eventId,'attendee_id'=>$vip['id'],'ticket_id'=>$ticket['id'],
        'amount'=>2500,'currency'=>'INR','status'=>'paid','provider'=>'manual',
    ]);
    expect(($order['status']??'')==='paid','Order creation failed.');

    $sessionRepo=new SessionRepository($store);
    $session=$sessionRepo->create([
        'event_id'=>$eventId,'title'=>'Opening Keynote','track'=>'Main','room'=>'Main Hall',
        'start_at'=>'2027-01-10T10:00','end_at'=>'2027-01-10T11:00','capacity'=>50,'speakers'=>['Speaker One'],
    ]);

    $badgeRepo=new BadgeTemplateRepository($store);
    $badge=$badgeRepo->create([
        'event_id'=>$eventId,'name'=>'Standard','category'=>'All','accent'=>'#4f46e5','background'=>'#ffffff',
        'show_qr'=>true,'fields'=>['name','company','category'],
    ]);

    $exhibitorRepo=new ExhibitorRepository($store);
    $exhibitor=$exhibitorRepo->create([
        'event_id'=>$eventId,'name'=>'Nova Systems','type'=>'Sponsor','booth'=>'A12',
        'contact_name'=>'Sponsor User','contact_email'=>'sponsor@example.test','staff_quota'=>5,'lead_quota'=>100,
    ]);
    $lead=(new LeadRepository($store))->create([
        'event_id'=>$eventId,'exhibitor_id'=>$exhibitor['id'],'attendee_id'=>$vip['id'],'score'=>80,'intent'=>'hot','notes'=>'Requested demo',
    ]);
    $meeting=(new MeetingRepository($store))->create([
        'event_id'=>$eventId,'exhibitor_id'=>$exhibitor['id'],'attendee_id'=>$vip['id'],'start_at'=>'2027-01-10T14:00',
    ]);
    expect(($lead['intent']??'')==='hot' && ($meeting['status']??'')==='scheduled','Exhibitor operations failed.');

    $seatRepo=new SeatAssignmentRepository($store);
    $seat=$seatRepo->assign($eventId,['attendee_id'=>$vip['id'],'hall_id'=>'hall_main','seat'=>'A12']);
    expect($seat['seat']==='A12','Seat assignment failed.');
    $collision=false;
    try{$seatRepo->assign($eventId,['attendee_id'=>$general['id'],'hall_id'=>'hall_main','seat'=>'A12']);}catch(RuntimeException){$collision=true;}
    expect($collision,'Seat collision was not blocked.');

    // Credentials, check-in and zones.
    $credentials=new CredentialService('test-secret');
    $issued=$credentials->issue($vip['id'],$eventId);
    expect($issued['token']===$credentials->issue($vip['id'],$eventId)['token'],'Credential is not stable.');
    expect(($credentials->verify($issued['token'])['attendee_id']??'')===$vip['id'],'Credential verification failed.');
    expect($credentials->verify($issued['token'].'x')===null,'Tampered credential accepted.');

    $checkins=new CheckinRepository($store);
    $scanner=new ScannerService($credentials,$attendees,$orders,$checkins,new AccessPolicyService($venueRepo),new AccessEventRepository($store));
    $denied=$scanner->verify($credentials->issue($general['id'],$eventId)['payload'],'zone_vip');
    expect(($denied['allowed']??true)===false,'Zone policy allowed General attendee into VIP zone.');
    $checked=$scanner->checkin($issued['payload'],'usr_test','zone_vip');
    expect(($checked['allowed']??false)===true && empty($checked['already_checked_in']),'Valid attendee check-in failed.');
    $duplicate=$scanner->checkin($issued['payload'],'usr_test','zone_vip');
    expect(($duplicate['already_checked_in']??false)===true,'Duplicate event check-in was not detected.');

    // Session attendance.
    $sessionAccess=new SessionAccessService($credentials,$attendees,$orders,$sessionRepo,new SessionAttendanceRepository($store));
    $sessionEntry=$sessionAccess->enter($session['id'],$issued['payload'],'usr_test');
    expect(($sessionEntry['allowed']??false)===true && empty($sessionEntry['duplicate']),'Session entry failed.');
    expect(($sessionAccess->enter($session['id'],$issued['payload'],'usr_test')['duplicate']??false)===true,'Duplicate session entry not detected.');

    // Offline snapshot.
    $snapshot=(new OfflineSnapshotService($attendees,$tickets,$venueRepo,$sessionRepo,$badgeRepo,'test-secret'))->build($eventId);
    expect(count($snapshot['credentials'])===1,'Offline snapshot must contain confirmed attendees only.');
    expect(($snapshot['credentials'][0]['payload']??'')===$issued['payload'],'Offline credential mismatch.');

    // Communications and automation.
    $outbox=new NotificationOutbox($store);
    $workflowRepo=new WorkflowRepository($store);
    $workflow=$workflowRepo->create([
        'event_id'=>$eventId,'name'=>'Welcome','trigger'=>'attendee.confirmed','enabled'=>true,
        'actions'=>[['type'=>'email','template'=>'registration_confirmation'],['type'=>'wait','minutes'=>5],['type'=>'whatsapp','template'=>'registration_confirmation']],
    ]);
    $run=(new WorkflowEngine($workflowRepo,$outbox))->fire('attendee.confirmed',[
        'event_id'=>$eventId,'attendee_id'=>$vip['id'],'email'=>$vip['email'],'phone'=>$vip['phone'],
    ]);
    expect($run['matched']===1,'Workflow did not execute.');
    expect(count($outbox->pending(25))===1,'Delayed workflow action was available too early.');

    $outboxCountBeforeDryRun=count($outbox->all());
    $dryRun=(new WorkflowEngine($workflowRepo,$outbox))->fire('attendee.confirmed',[
        'event_id'=>$eventId,'attendee_id'=>$vip['id'],'email'=>'preview@invalid.example','phone'=>'0000000000',
    ],true);
    expect(($dryRun['dry_run']??false)===true && $dryRun['matched']===1,'Workflow dry-run did not match.');
    expect(count($outbox->all())===$outboxCountBeforeDryRun,'Workflow dry-run mutated the notification outbox.');

    $campaignRepo=new CampaignRepository($store);
    $campaign=$campaignRepo->create([
        'event_id'=>$eventId,'name'=>'VIP Update','channel'=>'email','subject'=>'VIP','content'=>'Hello VIP',
        'segment'=>['status'=>'Confirmed','category'=>'VIP'],
    ]);
    $dispatch=(new CampaignDispatchService($campaignRepo,$attendees,$outbox))->dispatch($campaign['id']);
    expect($dispatch['matched']===1 && $dispatch['queued']===1,'Campaign segmentation failed.');

    $duplicateCampaignBlocked=false;
    try{(new CampaignDispatchService($campaignRepo,$attendees,$outbox))->dispatch($campaign['id']);}catch(RuntimeException){$duplicateCampaignBlocked=true;}
    expect($duplicateCampaignBlocked,'Campaign could be queued twice.');

    $worker=new NotificationWorker(
        $outbox,
        new NotificationTemplateRenderer(),
        new LogEmailProvider(),
        new LogWhatsAppProvider(),
        $campaignRepo,
    );
    $workerResult=$worker->run(25);
    expect(($workerResult['simulated']??0)>=1,'Log providers were not reported as simulated.');
    $campaignAfterWorker=null;
    foreach($campaignRepo->all() as $row) if(($row['id']??'')===$campaign['id']){$campaignAfterWorker=$row;break;}
    expect(($campaignAfterWorker['status']??'')==='simulated','Campaign log-provider delivery was falsely marked as sent.');
    expect((int)($campaignAfterWorker['simulated_count']??0)===1,'Campaign simulated delivery count is incorrect.');

    $print=(new PrintJobRepository($store))->create(['event_id'=>$eventId,'attendee_id'=>$vip['id'],'template_id'=>$badge['id']]);
    expect(($print['status']??'')==='queued','Badge print queue failed.');

    // Live analytics are based on fixture data.
    $dashboard=(new AnalyticsService($store))->dashboard($eventId);
    expect(($dashboard['metrics']['registrations']??0)===2,'Dashboard registration metric is not live.');
    expect(($dashboard['metrics']['confirmed']??0)===1,'Dashboard confirmation metric is incorrect.');
    expect(($dashboard['metrics']['checked_in']??0)===1,'Dashboard check-in metric is incorrect.');
    expect(($dashboard['metrics']['revenue']??0)===2500,'Dashboard revenue metric is incorrect.');

    // Event Graph + intelligence.
    $graph=(new EventGraphBuilder($store))->build($eventId);
    expect(($graph['metrics']['registrations']??0)===2,'Event Graph registration metric incorrect.');
    expect(($graph['metrics']['leads']??0)===1 && ($graph['metrics']['meetings']??0)===1,'Event Graph exhibitor metrics incorrect.');
    expect(($graph['metrics']['zone_occupancy']['zone_vip']??0)===1,'Digital Twin occupancy incorrect.');

    $local=new LocalIntelligenceEngine();
    $analysis=$local->analyze($graph);
    expect(($analysis['lead_scores'][0]['ai_band']??'')==='hot','Lead intelligence failed.');
    expect(str_contains(strtolower((string)$local->copilot('How are registrations doing?',$graph)['answer']),'registration'),'Copilot failed.');
    expect(str_contains((string)$local->concierge('What sessions should I attend?',$vip['id'],$graph)['answer'],'Opening Keynote'),'Concierge failed.');
    expect(((new IntelligenceClient())->analyze($graph)['engine']??'')==='php_fallback','Intelligence fallback failed.');

    // AI governance.
    $proposalRepo=new ActionProposalRepository($store);
    $proposal=$proposalRepo->create([
        'event_id'=>$eventId,'type'=>'campaign_draft','title'=>'AI Draft',
        'payload'=>['channel'=>'email','subject'=>'Draft','content'=>'Draft content','segment'=>['status'=>'Confirmed']],
    ],'usr_ai');
    $blocked=false;
    try{(new ApprovedActionService($store))->execute($proposal);}catch(RuntimeException){$blocked=true;}
    expect($blocked,'Unapproved AI action executed.');
    $approved=$proposalRepo->decide($proposal['id'],'approved','usr_manager');
    expect((new ApprovedActionService($store))->execute($approved)['type']==='campaign_draft','Approved AI draft failed.');

    // Remaining roadmap modules: accreditation.
    $accreditationRepo=new AccreditationRepository($store);
    $accreditation=$accreditationRepo->create([
        'event_id'=>$eventId,'attendee_id'=>$vip['id'],'type'=>'Media','quota_pool'=>'Media 50',
        'valid_from'=>'2027-01-10','valid_until'=>'2027-01-11',
    ]);
    $accreditation=$accreditationRepo->update($accreditation['id'],['status'=>'approved'],'usr_manager');
    $accreditation=$accreditationRepo->update($accreditation['id'],['status'=>'activated'],'usr_manager');
    expect(($accreditation['status']??'')==='activated','Accreditation lifecycle failed.');

    // Real media persistence uses private storage and metadata.
    $tmpMedia=tempnam($root,'media-');
    file_put_contents($tmpMedia,'DigiSangam upload fixture');
    $media=(new MediaRepository($store))->saveUpload([
        'error'=>UPLOAD_ERR_OK,'size'=>filesize($tmpMedia),'tmp_name'=>$tmpMedia,'name'=>'fixture.txt',
    ],$eventId,'accreditation_document',false);
    expect(($media['event_id']??'')===$eventId && ($media['mime']??'')==='text/plain','Media upload persistence failed.');

    // Wallet issuance lifecycle is available even before provider credentials are configured.
    $wallet=(new WalletPassService(new WalletPassRepository($store),'wallet-secret'))->issue($event,$vip,$issued['payload'],'google');
    expect(($wallet['platform']??'')==='google' && in_array(($wallet['status']??''),['ready','provider_configuration_required'],true),'Wallet pass issuance failed.');

    // Developer API keys are hashed, scoped and event-bound.
    $apiKeys=new ApiKeyRepository($store);
    $key=$apiKeys->create('Audit integration',['events.read','attendees.read'],$eventId);
    expect(str_starts_with((string)($key['key']??''),'dsk_'),'Developer API key was not issued.');
    expect(($apiKeys->authenticate($key['key'],'attendees.read',$eventId)['id']??'')===($key['id']??''),'Developer API key authentication failed.');
    expect($apiKeys->authenticate($key['key'],'analytics.read',$eventId)===null,'Developer API scope enforcement failed.');

    // Webhook endpoints queue signed asynchronous deliveries from the journal.
    $webhooks=new WebhookRepository($store);
    $webhook=$webhooks->create(['event_id'=>$eventId,'url'=>'https://example.invalid/digisangam','events'=>['audit.test']]);
    (new EventJournal($store))->append('audit.test',['event_id'=>$eventId,'attendee_id'=>$vip['id']]);
    $webhookQueue=(new WebhookOutboxRepository($store))->all();
    expect(count($webhookQueue)===1 && ($webhookQueue[0]['webhook_id']??'')===$webhook['id'],'Developer webhook outbox was not queued.');

    // NFC/RFID binding is resolved by the same scanner policy engine.
    $bindings=new CredentialBindingRepository($store);
    $binding=$bindings->bind($eventId,$vip['id'],'rfid','A1B2C3');
    $boundScanner=new ScannerService($credentials,$attendees,$orders,$checkins,new AccessPolicyService($venueRepo),new AccessEventRepository($store),$bindings);
    $rfidVerify=$boundScanner->verify('rfid:A1B2C3','zone_vip');
    expect(($rfidVerify['allowed']??false)===true && ($rfidVerify['credential_source']??'')==='RFID','RFID credential binding failed.');

    // Badge printer worker reaches a real provider abstraction; log provider is truthfully simulated.
    $print2=(new PrintJobRepository($store))->create(['event_id'=>$eventId,'attendee_id'=>$vip['id'],'template_id'=>$badge['id']]);
    $printRun=(new BadgePrintWorker(new PrintJobRepository($store),$attendees,$badgeRepo,new LogPrintProvider()))->run(10);
    expect(($printRun['simulated']??0)>=1,'Badge print provider abstraction failed.');

    // SMS uses the same retryable notification worker and is not falsely marked delivered with log provider.
    $smsOutbox=new NotificationOutbox($store);
    $smsOutbox->queue('sms','custom_campaign',['phone'=>'919999999999'],['event_id'=>$eventId,'content'=>'SMS audit']);
    $smsWorker=new NotificationWorker($smsOutbox,new NotificationTemplateRenderer(),new LogEmailProvider(),new LogWhatsAppProvider(),null,new LogSmsProvider());
    $smsResult=$smsWorker->run(25);
    expect(($smsResult['simulated']??0)>=1,'SMS notification provider path failed.');

    // AI Event Builder produces an editable complete draft blueprint.
    $blueprint=(new EventBlueprintService())->build('Create a hybrid expo for VIP speakers sponsors and exhibitors with approval');
    expect(($blueprint['type']??'')==='Expo' && ($blueprint['format']??'')==='hybrid' && in_array('VIP',$blueprint['registration']['categories']??[],true),'AI Event Builder blueprint failed.');

    // Advanced reports aggregate and export real event data.
    $reports=new OperationalReportService($store);
    $report=$reports->summary($eventId);
    expect(($report['registrations']??0)===2 && ($report['approved_accreditations']??0)===1,'Operational report summary failed.');
    expect(str_contains($reports->export($eventId,'attendees'),'Vip User'),'Operational CSV export failed.');

    // Walk-in registration creates a real attendee and stable credential.
    $walkin=(new WalkInRegistrationService($events,$registration,$tickets,$attendees,$orders,$credentials))->register($eventId,[
        'name'=>'Walk In User','email'=>'walkin@example.test','phone'=>'918888888888','category'=>'General','payment_settled'=>true,
    ]);
    expect(($walkin['attendee']['source']??'')==='walk_in' && !empty($walkin['credential']['payload']),'Walk-in registration failed.');

    // Payment signatures.
    $providerOrder='order_test';$payment='pay_test';$secret='rzp-secret';
    expect((new RazorpayCheckoutVerifier($secret))->verify($providerOrder,$payment,hash_hmac('sha256',$providerOrder.'|'.$payment,$secret)),'Checkout signature failed.');
    $raw='{"event":"payment.captured"}';$webhookSecret='webhook-secret';
    expect(((new RazorpayWebhookVerifier($webhookSecret))->verify($raw,hash_hmac('sha256',$raw,$webhookSecret))['event']??'')==='payment.captured','Webhook signature failed.');

    fwrite(STDOUT,"DigiSangam zero-state full-module smoke audit passed.\n");
} finally {
    removeTree($root);
}
