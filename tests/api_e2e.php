<?php
declare(strict_types=1);

$base=rtrim((string)(getenv('DIGISANGAM_TEST_BASE')?:'http://127.0.0.1:8099'),'/');
$cookie=tempnam(sys_get_temp_dir(),'ds-cookie-');
if($cookie===false) throw new RuntimeException('Unable to create cookie jar.');

function fail(string $message,mixed $context=null): never {
    fwrite(STDERR,"E2E FAIL: ".$message.PHP_EOL);
    if($context!==null) fwrite(STDERR,(is_string($context)?$context:json_encode($context,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)).PHP_EOL);
    exit(1);
}

function request(string $method,string $path,?array $payload=null,string $csrf='',array $extraHeaders=[]): array {
    global $base,$cookie;
    if(!function_exists('curl_init')) fail('PHP cURL extension is required for HTTP E2E.');
    $ch=curl_init($base.$path);
    $headers=['Accept: application/json'];
    if($payload!==null) $headers[]='Content-Type: application/json';
    if($csrf!=='') $headers[]='X-CSRF-Token: '.$csrf;
    foreach($extraHeaders as $header) $headers[]=$header;
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_COOKIEJAR=>$cookie,
        CURLOPT_COOKIEFILE=>$cookie,
        CURLOPT_TIMEOUT=>15,
        CURLOPT_CONNECTTIMEOUT=>3,
    ]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $raw=curl_exec($ch);
    $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    $error=curl_error($ch);
    curl_close($ch);
    if($raw===false||$error!=='') fail('HTTP transport error: '.$error);
    $data=json_decode((string)$raw,true);
    if(!is_array($data)) fail('Invalid JSON response from '.$method.' '.$path,$raw);
    if($status<200||$status>=300) fail($method.' '.$path.' returned '.$status,$data);
    return ['status'=>$status,'data'=>$data];
}

try{
    $status=request('GET','/api/v1/auth/status')['data'];
    if(empty($status['setup_required'])) fail('Fresh HTTP runtime did not require setup.',$status);

    $setup=request('POST','/api/v1/auth/setup',[
        'name'=>'E2E Administrator',
        'email'=>'admin@example.test',
        'password'=>'StrongPass123!',
    ])['data'];
    $csrf=(string)($setup['csrf_token']??'');
    if($csrf==='') fail('Setup did not issue CSRF token.',$setup);

    $team=request('GET','/api/v1/team')['data'];
    if(count($team['users']??[])!==1||!in_array('event_manager',$team['roles']??[],true)) fail('Team/RBAC bootstrap is incorrect.',$team);
    $member=request('POST','/api/v1/team',[
        'name'=>'E2E Event Manager',
        'email'=>'manager@example.test',
        'password'=>'StrongPass456!',
        'role'=>'viewer',
    ],$csrf)['data'];
    if(($member['role']??'')!=='viewer'||isset($member['password_hash'])) fail('Team user creation leaked/returned invalid data.',$member);
    $member=request('PATCH','/api/v1/team/'.rawurlencode((string)$member['id']),['role'=>'event_manager'],$csrf)['data'];
    if(($member['role']??'')!=='event_manager') fail('Team role update failed.',$member);

    $event=request('POST','/api/v1/events',[
        'name'=>'HTTP Audit Summit',
        'description'=>'Created through the production API entrypoint.',
        'type'=>'Conference',
        'category'=>'Technology',
        'format'=>'in_person',
        'start_date'=>'2027-02-10',
        'end_date'=>'2027-02-11',
        'timezone'=>'Asia/Kolkata',
        'location'=>'Pune, India',
        'venue_name'=>'Audit Hall',
        'currency'=>'INR',
        'privacy'=>'public',
        'branding'=>[
            'brand_name'=>'Audit Brand',
            'primary_color'=>'#4f46e5',
            'secondary_color'=>'#06b6d4',
            'background_color'=>'#0f172a',
        ],
        'organizer'=>['name'=>'E2E Organizer','email'=>'organizer@example.test'],
        'registration'=>[
            'title'=>'HTTP Registration',
            'approval_mode'=>'auto',
            'categories'=>['General','VIP'],
        ],
        'ticket'=>[
            'enabled'=>true,
            'name'=>'General Admission',
            'price'=>0,
            'quantity'=>25,
        ],
    ],$csrf)['data'];
    $eventId=(string)($event['id']??'');
    if($eventId==='') fail('Event creation returned no ID.',$event);

    $events=request('GET','/api/v1/events')['data'];
    if(count($events)!==1||($events[0]['id']??'')!==$eventId) fail('Event list contains unexpected/demo data.',$events);

    $registration=request('GET','/api/v1/events/'.rawurlencode($eventId).'/registration')['data'];
    if(($registration['title']??'')!=='HTTP Registration') fail('Registration initialization not persisted.',$registration);
    if(($registration['categories']??[])!==['General','VIP']) fail('Registration categories not persisted.',$registration);

    $preview=request('GET','/api/v1/events/'.rawurlencode($eventId).'/preview')['data'];
    if(empty($preview['preview'])||($preview['event']['status']??'')!=='Draft') fail('Authenticated draft preview failed.',$preview);

    $tickets=request('GET','/api/v1/tickets?event_id='.rawurlencode($eventId))['data'];
    if(count($tickets)!==1||($tickets[0]['name']??'')!=='General Admission') fail('Initial ticket not created/scoped.',$tickets);
    $ticketId=(string)$tickets[0]['id'];

    $published=request('PATCH','/api/v1/events/'.rawurlencode($eventId),['status'=>'Published'],$csrf)['data'];
    if(($published['status']??'')!=='Published') fail('Event publish failed.',$published);

    $public=request('GET','/api/v1/public/events/'.rawurlencode($eventId))['data'];
    if(($public['event']['branding']['brand_name']??'')!=='Audit Brand') fail('Public branding missing.',$public);
    if(count($public['tickets']??[])!==1) fail('Public ticket list is incorrect.',$public);

    $discovery=request('GET','/api/v1/public/events')['data'];
    if(($discovery['total']??0)!==1||($discovery['events'][0]['id']??'')!==$eventId) fail('Public discovery list is incorrect.',$discovery);
    if(($discovery['events'][0]['min_price']??-1)!==0||!in_array('Technology',$discovery['categories']??[],true)) fail('Public discovery metadata is incorrect.',$discovery);

    $registrationResult=request('POST','/api/v1/public/events/'.rawurlencode($eventId).'/register',[
        'ticket_id'=>$ticketId,
        'answers'=>[
            'fld_name'=>'Public Attendee',
            'fld_email'=>'attendee@example.test',
            'fld_phone'=>'919876543210',
            'fld_category'=>'VIP',
            'fld_company'=>'Audit Co',
        ],
        'website'=>'',
    ])['data'];
    if(($registrationResult['attendee']['status']??'')!=='Confirmed') fail('Free auto-approved attendee was not confirmed.',$registrationResult);
    $attendeeId=(string)($registrationResult['attendee']['id']??'');
    $credential=(string)($registrationResult['credential']['payload']??'');
    if($attendeeId===''||$credential==='') fail('Registration did not issue attendee/credential.',$registrationResult);

    $adminAttendees=request('GET','/api/v1/attendees?event_id='.rawurlencode($eventId))['data'];
    if(count($adminAttendees)!==1||($adminAttendees[0]['id']??'')!==$attendeeId) fail('Attendee list is not event-scoped.',$adminAttendees);

    $verified=request('POST','/api/v1/scanner/verify',['payload'=>$credential],$csrf)['data'];
    if(empty($verified['allowed'])) fail('Scanner verify rejected valid credential.',$verified);
    $checkin=request('POST','/api/v1/scanner/checkin',['payload'=>$credential],$csrf)['data'];
    if(empty($checkin['allowed'])||!empty($checkin['already_checked_in'])) fail('First check-in failed.',$checkin);
    $live=request('GET','/api/v1/onground/live?event_id='.rawurlencode($eventId))['data'];
    if(($live['currently_inside']??0)!==1) fail('Live entry monitor did not show checked-in visitor.',$live);
    $duplicate=request('POST','/api/v1/scanner/checkin',['payload'=>$credential],$csrf)['data'];
    if(empty($duplicate['already_checked_in'])) fail('Duplicate check-in not detected.',$duplicate);
    $exit=request('POST','/api/v1/scanner/exit',['payload'=>$credential],$csrf)['data'];
    if(($exit['reason']??'')!=='EXIT_RECORDED') fail('Venue exit was not recorded.',$exit);
    $liveAfterExit=request('GET','/api/v1/onground/live?event_id='.rawurlencode($eventId))['data'];
    if(($liveAfterExit['currently_inside']??-1)!==0) fail('Live entry monitor did not clear exited visitor.',$liveAfterExit);
    request('POST','/api/v1/scanner/checkin',['payload'=>$credential],$csrf);

    $dashboard=request('GET','/api/v1/dashboard?event_id='.rawurlencode($eventId))['data'];
    if(($dashboard['metrics']['registrations']??0)!==1) fail('Dashboard registration metric incorrect.',$dashboard);
    if(($dashboard['metrics']['checked_in']??0)!==1) fail('Dashboard check-in metric incorrect.',$dashboard);

    $intelligence=request('GET','/api/v1/intelligence/overview?event_id='.rawurlencode($eventId))['data'];
    if(($intelligence['event_id']??'')!==$eventId) fail('Intelligence is not scoped to the selected event.',$intelligence);

    $paidTicket=request('POST','/api/v1/tickets',[
        'event_id'=>$eventId,
        'name'=>'Paid Admission',
        'price'=>1500,
        'quantity'=>10,
        'status'=>'Active',
    ],$csrf)['data'];
    $paidRegistration=request('POST','/api/v1/public/events/'.rawurlencode($eventId).'/register',[
        'ticket_id'=>$paidTicket['id'],
        'answers'=>[
            'fld_name'=>'Manual Payment Attendee',
            'fld_email'=>'manual-pay@example.test',
            'fld_phone'=>'919811111111',
            'fld_category'=>'General',
            'fld_company'=>'Manual Co',
        ],
        'website'=>'',
    ])['data'];
    if(($paidRegistration['order']['status']??'')!=='pending'||($paidRegistration['attendee']['status']??'')!=='Pending') fail('Manual paid registration should remain pending before settlement.',$paidRegistration);
    $paidOrderId=(string)$paidRegistration['order']['id'];
    $captured=request('POST','/api/v1/orders/'.rawurlencode($paidOrderId).'/capture',['payment_reference'=>'CASH-TEST-001'],$csrf)['data'];
    if(($captured['order']['status']??'')!=='paid'||($captured['attendee']['status']??'')!=='Confirmed') fail('Manual payment capture did not confirm attendee.',$captured);
    $refunded=request('POST','/api/v1/orders/'.rawurlencode($paidOrderId).'/refund',['payment_reference'=>'REFUND-TEST-001'],$csrf)['data'];
    if(($refunded['order']['status']??'')!=='refunded'||($refunded['attendee']['status']??'')!=='Pending') fail('Manual refund did not revoke paid attendee state.',$refunded);
    $ticketsAfterRefund=request('GET','/api/v1/tickets?event_id='.rawurlencode($eventId))['data'];
    $refundedTicket=array_values(array_filter($ticketsAfterRefund,static fn(array $t): bool => ($t['id']??'')===($paidTicket['id']??'')))[0]??null;
    if(!$refundedTicket||($refundedTicket['sold']??-1)!==0) fail('Refund did not release ticket inventory.',$ticketsAfterRefund);

    $inviteEvent=request('POST','/api/v1/events',[
        'name'=>'Invite Only Audit',
        'start_date'=>'2027-02-20',
        'end_date'=>'2027-02-20',
        'location'=>'Pune, India',
        'privacy'=>'public',
        'registration'=>['title'=>'Invite Registration','approval_mode'=>'invite_only','categories'=>['General']],
        'ticket'=>['enabled'=>true,'name'=>'Invite Pass','price'=>0,'quantity'=>5],
    ],$csrf)['data'];
    $inviteEventId=(string)$inviteEvent['id'];
    request('PATCH','/api/v1/events/'.rawurlencode($inviteEventId),['status'=>'Published'],$csrf);
    $invite=request('POST','/api/v1/invitations',[
        'event_id'=>$inviteEventId,'email'=>'invite-http@example.test','category'=>'General',
    ],$csrf)['data'];
    $invitePublic=request('GET','/api/v1/public/invitations/'.rawurlencode((string)$invite['token']))['data'];
    if(($invitePublic['email']??'')!=='invite-http@example.test') fail('Public invitation validation failed.',$invitePublic);
    $inviteTickets=request('GET','/api/v1/tickets?event_id='.rawurlencode($inviteEventId))['data'];
    $inviteRegistration=request('POST','/api/v1/public/events/'.rawurlencode($inviteEventId).'/register',[
        'ticket_id'=>$inviteTickets[0]['id'],
        'invitation_token'=>$invite['token'],
        'answers'=>[
            'fld_name'=>'Invited HTTP User',
            'fld_email'=>'invite-http@example.test',
            'fld_phone'=>'919800000123',
            'fld_category'=>'General',
        ],
        'website'=>'',
    ])['data'];
    if(($inviteRegistration['attendee']['status']??'')!=='Confirmed') fail('Invitation registration did not confirm attendee.',$inviteRegistration);
    $inviteRows=request('GET','/api/v1/invitations?event_id='.rawurlencode($inviteEventId))['data'];
    if(($inviteRows[0]['status']??'')!=='accepted') fail('Invitation was not marked accepted.',$inviteRows);

    $event2=request('POST','/api/v1/events',[
        'name'=>'Isolation Event',
        'start_date'=>'2027-03-01',
        'end_date'=>'2027-03-01',
        'location'=>'Mumbai, India',
        'registration'=>['categories'=>['General']],
        'ticket'=>['enabled'=>true,'name'=>'Second Event Ticket','price'=>0,'quantity'=>5],
    ],$csrf)['data'];
    $event2Id=(string)$event2['id'];
    $tickets2=request('GET','/api/v1/tickets?event_id='.rawurlencode($event2Id))['data'];
    if(count($tickets2)!==1||($tickets2[0]['event_id']??'')!==$event2Id) fail('Cross-event ticket isolation failed.',$tickets2);

    // Remaining roadmap HTTP routes.
    $blueprint=request('POST','/api/v1/intelligence/event-builder',[
        'prompt'=>'Create a hybrid expo for VIP sponsors exhibitors with approval',
    ],$csrf)['data'];
    if(($blueprint['type']??'')!=='Expo'||($blueprint['format']??'')!=='hybrid') fail('AI Event Builder route failed.',$blueprint);

    $accreditation=request('POST','/api/v1/accreditation',[
        'event_id'=>$eventId,'attendee_id'=>$attendeeId,'type'=>'Media','quota_pool'=>'Media 25',
    ],$csrf)['data'];
    $accreditation=request('PATCH','/api/v1/accreditation/'.rawurlencode((string)$accreditation['id']),['status'=>'approved'],$csrf)['data'];
    if(($accreditation['status']??'')!=='approved') fail('Accreditation lifecycle route failed.',$accreditation);

    $binding=request('POST','/api/v1/credential-bindings',[
        'event_id'=>$eventId,'attendee_id'=>$attendeeId,'type'=>'rfid','uid'=>'E2E123',
    ],$csrf)['data'];
    $rfid=request('POST','/api/v1/scanner/verify',['payload'=>'rfid:E2E123'],$csrf)['data'];
    if(empty($rfid['allowed'])||($rfid['credential_source']??'')!=='RFID') fail('RFID scanner route failed.',$rfid);

    $walkin=request('POST','/api/v1/onground/walk-in',[
        'event_id'=>$eventId,'name'=>'HTTP Walk In','email'=>'http-walkin@example.test','phone'=>'918000000001','category'=>'General','payment_settled'=>true,
    ],$csrf)['data'];
    if(($walkin['attendee']['source']??'')!=='walk_in'||empty($walkin['credential']['payload'])) fail('Walk-in API failed.',$walkin);

    $report=request('GET','/api/v1/reports?event_id='.rawurlencode($eventId))['data'];
    if(($report['registrations']??0)<2||($report['approved_accreditations']??0)!==1) fail('Operational reports API failed.',$report);
    $reportDefinition=request('POST','/api/v1/report-definitions',[
        'event_id'=>$eventId,'name'=>'Confirmed HTTP attendees','dataset'=>'attendees',
        'columns'=>['name','email','status'],'filters'=>['status'=>'Confirmed'],
    ],$csrf)['data'];
    $reportDefinitions=request('GET','/api/v1/report-definitions?event_id='.rawurlencode($eventId))['data'];
    if(count($reportDefinitions)!==1||($reportDefinitions[0]['id']??'')!==($reportDefinition['id']??'')) fail('Custom report builder API failed.',$reportDefinitions);

    $wallet=request('POST','/api/v1/public/confirmations/'.rawurlencode((string)$registrationResult['confirmation_token']).'/wallet',[
        'platform'=>'google',
    ])['data'];
    if(($wallet['platform']??'')!=='google') fail('Wallet issuance API failed.',$wallet);

    $devKey=request('POST','/api/v1/developer/keys',[
        'name'=>'HTTP integration','event_id'=>$eventId,'scopes'=>['events.read','attendees.read'],
    ],$csrf)['data'];
    if(!str_starts_with((string)($devKey['key']??''),'dsk_')) fail('Developer API key creation failed.',$devKey);
    $developerAttendees=request('GET','/api/v1/developer/v1/events/'.rawurlencode($eventId).'/attendees',null,'',[
        'X-DigiSangam-Key: '.$devKey['key'],
    ])['data'];
    if(count($developerAttendees)<2) fail('Developer API attendee endpoint failed.',$developerAttendees);

    $webhook=request('POST','/api/v1/developer/webhooks',[
        'event_id'=>$eventId,'url'=>'https://example.invalid/digisangam','events'=>['attendee.checked_in'],
    ],$csrf)['data'];
    if(empty($webhook['secret'])) fail('Webhook creation did not issue signing secret.',$webhook);
    $webhooks=request('GET','/api/v1/developer/webhooks?event_id='.rawurlencode($eventId))['data'];
    if(isset($webhooks[0]['secret'])||empty($webhooks[0]['secret_configured'])) fail('Webhook list leaked signing secret.',$webhooks);

    fwrite(STDOUT,"DigiSangam HTTP API E2E audit passed.\n");
} finally {
    @unlink($cookie);
}
