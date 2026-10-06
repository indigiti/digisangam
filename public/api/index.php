<?php
declare(strict_types=1);

use DigiSangam\Analytics\AnalyticsService;
use DigiSangam\Agenda\SessionRepository;
use DigiSangam\Agenda\SessionAccessService;
use DigiSangam\Agenda\SessionAttendanceRepository;
use DigiSangam\Venue\SeatAssignmentRepository;
use DigiSangam\Automation\WorkflowEngine;
use DigiSangam\Automation\WorkflowRepository;
use DigiSangam\Badges\BadgeTemplateRepository;
use DigiSangam\Communications\CampaignDispatchService;
use DigiSangam\Communications\CampaignRepository;
use DigiSangam\Exhibitors\ExhibitorRepository;
use DigiSangam\Exhibitors\LeadRepository;
use DigiSangam\Exhibitors\MeetingRepository;
use DigiSangam\Badges\PrintJobRepository;
use DigiSangam\OnGround\OfflineSnapshotService;
use DigiSangam\OnGround\AccessEventRepository;
use DigiSangam\OnGround\AccessPolicyService;
use DigiSangam\Venue\VenueRepository;
use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Auth\AuthenticationException;
use DigiSangam\Auth\AuthorizationException;
use DigiSangam\Auth\AuthService;
use DigiSangam\Commerce\OrderRepository;
use DigiSangam\Core\Csv\CsvService;
use DigiSangam\Core\EventJournal\EventJournal;
use DigiSangam\Core\Http\JsonResponse;
use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Credentials\CredentialService;
use DigiSangam\Events\EventRepository;
use DigiSangam\Invitations\InvitationRepository;
use DigiSangam\Intelligence\EventGraphBuilder;
use DigiSangam\Intelligence\ActionProposalRepository;
use DigiSangam\Intelligence\ApprovedActionService;
use DigiSangam\Intelligence\IntelligenceClient;
use DigiSangam\Notifications\NotificationOutbox;
use DigiSangam\Payments\PaymentService;
use DigiSangam\Payments\PaymentCaptureService;
use DigiSangam\Payments\PaymentWebhookService;
use DigiSangam\Payments\RazorpayCheckoutVerifier;
use DigiSangam\Payments\RazorpayWebhookVerifier;
use DigiSangam\Documents\ReceiptService;
use DigiSangam\OnGround\ScannerService;
use DigiSangam\OnGround\CheckinRepository;
use DigiSangam\PublicFlow\PublicRequestGuard;
use DigiSangam\PublicFlow\RateLimitException;
use DigiSangam\PublicFlow\RegistrationCheckoutService;
use DigiSangam\Registration\RegistrationRepository;
use DigiSangam\Tickets\TicketRepository;
use DigiSangam\Workspace\WorkspaceRepository;

$privateRoot=trim((string)getenv('DIGISANGAM_PRIVATE_ROOT'));
$bootstrapCandidates=array_values(array_filter([
    $privateRoot!=='' ? rtrim($privateRoot,'/').'/app/bootstrap.php' : '',
    dirname(__DIR__,2).'/app/bootstrap.php',
    dirname(__DIR__,3).'/private_html/digisangam/app/bootstrap.php',
]));
$bootstrapFile='';
foreach($bootstrapCandidates as $candidate){
    if(is_file($candidate)){ $bootstrapFile=$candidate; break; }
}
if($bootstrapFile===''){
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error'=>'DIGISANGAM_PRIVATE_RUNTIME_NOT_FOUND']);
    exit;
}
require $bootstrapFile;

$root = defined('DIGISANGAM_ROOT') ? DIGISANGAM_ROOT : dirname(__DIR__,2);
$store = new JsonFileStore(defined('DIGISANGAM_STORAGE_ROOT') ? DIGISANGAM_STORAGE_ROOT : $root . '/storage');
$journal = new EventJournal($store);
$auth = new AuthService($store);
$path = '/' . trim((string)($_GET['path'] ?? ''), '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$body = static function (): array {
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    return is_array($decoded) ? $decoded : [];
};

$eventQuery=trim((string)($_GET['event_id'] ?? ''));
$forEvent=static function(array $rows,string $eventId): array {
    if($eventId==='') return $rows;
    return array_values(array_filter($rows,static fn(array $row): bool => ($row['event_id']??'')===$eventId));
};

$eventView=static function(array $event) use ($store): array {
    $eventId=(string)($event['id']??'');
    $attendees=array_values(array_filter((new AttendeeRepository($store))->all(),static fn(array $row): bool => ($row['event_id']??'')===$eventId));
    $orders=array_values(array_filter((new OrderRepository($store))->all(),static fn(array $row): bool => ($row['event_id']??'')===$eventId));
    $event['registrations']=count($attendees);
    $event['confirmed']=count(array_filter($attendees,static fn(array $row): bool => ($row['status']??'')==='Confirmed'));
    $event['revenue']=array_sum(array_map(static fn(array $row): int => ($row['status']??'')==='paid'?(int)($row['amount']??0):0,$orders));
    return $event;
};

$requireEvent=static function(string $eventId) use ($store): array {
    $eventId=trim($eventId);
    if($eventId==='') throw new InvalidArgumentException('Event is required.');
    $event=(new EventRepository($store))->find($eventId);
    if(!$event) throw new InvalidArgumentException('Event not found.');
    return $event;
};

$assertPublishable=static function(array $candidate) use ($store): void {
    $eventId=(string)($candidate['id']??'');
    $errors=[];
    if(trim((string)($candidate['name']??''))==='') $errors[]='event name';
    if(trim((string)($candidate['start_date']??''))==='') $errors[]='start date';
    if(($candidate['format']??'in_person')!=='virtual' && trim((string)($candidate['location']??''))==='') $errors[]='location';
    if(trim((string)($candidate['branding']['brand_name']??''))==='') $errors[]='brand name';

    $schema=(new RegistrationRepository($store))->schema($eventId);
    if(empty($schema['fields'])) $errors[]='registration fields';
    if(empty($schema['categories'])) $errors[]='registration categories';

    $tickets=array_values(array_filter((new TicketRepository($store))->all(),static fn(array $row): bool =>
        ($row['event_id']??'')===$eventId && ($row['status']??'')==='Active'
    ));
    if($tickets===[]) $errors[]='at least one active ticket';

    if($errors!==[]) throw new InvalidArgumentException('Event cannot be published until configured: '.implode(', ',$errors).'.');
};

$credentialSecret = static function () use ($store): string {
    $env = trim((string)getenv('DIGISANGAM_CREDENTIAL_SECRET'));
    if ($env !== '') return $env;
    $record = $store->read('secrets/credential.json', []);
    if (!empty($record['secret'])) return (string)$record['secret'];
    $secret = bin2hex(random_bytes(32));
    $store->write('secrets/credential.json', ['secret'=>$secret,'created_at'=>date(DATE_ATOM)]);
    return $secret;
};

$publicFlow = static function () use ($store,$credentialSecret): RegistrationCheckoutService {
    return new RegistrationCheckoutService(
        new EventRepository($store),
        new RegistrationRepository($store),
        new InvitationRepository($store),
        new TicketRepository($store),
        new AttendeeRepository($store),
        new OrderRepository($store),
        new NotificationOutbox($store),
        new CredentialService($credentialSecret()),
        new PaymentService(),
    );
};

$paymentCapture = static function () use ($store,$journal): PaymentCaptureService {
    return new PaymentCaptureService(
        new OrderRepository($store),
        new AttendeeRepository($store),
        new RegistrationRepository($store),
        new NotificationOutbox($store),
        $journal,
    );
};

$scanner = static function () use ($store,$credentialSecret): ScannerService {
    return new ScannerService(
        new CredentialService($credentialSecret()),
        new AttendeeRepository($store),
        new OrderRepository($store),
        new CheckinRepository($store),
        new AccessPolicyService(new VenueRepository($store)),
        new AccessEventRepository($store),
    );
};

$automation = static function () use ($store): WorkflowEngine {
    return new WorkflowEngine(
        new WorkflowRepository($store),
        new NotificationOutbox($store),
    );
};

$intelligence = static function (): IntelligenceClient {
    return new IntelligenceClient(
        (string)getenv('DIGISANGAM_INTELLIGENCE_URL'),
        (string)getenv('DIGISANGAM_INTELLIGENCE_TOKEN'),
    );
};

try {
    if ($method === 'GET' && $path === '/auth/status') {
        JsonResponse::send([
            'setup_required'=>$auth->setupRequired(),
            'authenticated'=>$auth->user() !== null,
            'user'=>$auth->user(),
            'csrf_token'=>$auth->user() ? $auth->csrfToken() : null,
        ]);
    }

    if ($method === 'POST' && $path === '/auth/setup') {
        $input = $body();
        $user = $auth->setup((string)($input['name'] ?? ''),(string)($input['email'] ?? ''),(string)($input['password'] ?? ''));
        $journal->append('auth.setup', ['user_id'=>$user['id']]);
        JsonResponse::send(['user'=>$user,'csrf_token'=>$auth->csrfToken()], 201);
    }

    if ($method === 'POST' && $path === '/auth/login') {
        $input = $body();
        $user = $auth->login((string)($input['email'] ?? ''),(string)($input['password'] ?? ''));
        if (!$user) JsonResponse::send(['error'=>'Invalid email or password.'], 401);
        $journal->append('auth.login', ['user_id'=>$user['id']]);
        JsonResponse::send(['user'=>$user,'csrf_token'=>$auth->csrfToken()]);
    }

    if ($method === 'POST' && $path === '/auth/logout') {
        $user = $auth->requireUser();
        $auth->validateCsrf();
        $journal->append('auth.logout', ['user_id'=>$user['id']]);
        $auth->logout();
        JsonResponse::send(['ok'=>true]);
    }

    if ($method === 'POST' && $path === '/webhooks/payments/razorpay') {
        $raw=(string)file_get_contents('php://input');
        $signature=(string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');
        $payload=(new RazorpayWebhookVerifier((string)getenv('RAZORPAY_WEBHOOK_SECRET')))->verify($raw,$signature);
        $result=(new PaymentWebhookService(
            new OrderRepository($store),
            new AttendeeRepository($store),
            new RegistrationRepository($store),
            new NotificationOutbox($store),
            $journal,
        ))->handleRazorpay($payload);
        if(empty($result['duplicate']) && ($result['order']['status'] ?? '')==='paid' && !empty($result['attendee'])){
            $context=(array)$result['attendee'];
            $context['event_id']=$result['order']['event_id'] ?? ($context['event_id'] ?? '');
            $context['order_id']=$result['order']['id'] ?? '';
            $automation()->fire('payment.captured',$context);
            if(($context['status'] ?? '')==='Confirmed') $automation()->fire('attendee.confirmed',$context);
        }
        JsonResponse::send($result);
    }

    // Public attendee-facing API. These routes intentionally do not require an admin session.
    if (str_starts_with($path, '/public/')) {
        if ($auth->setupRequired()) {
            JsonResponse::send(['error'=>'Event platform setup is not complete.'],503);
        }

        if ($method === 'GET' && preg_match('#^/public/events/([^/]+)$#',$path,$m)) {
            JsonResponse::send($publicFlow()->publicEvent($m[1]));
        }

        if ($method === 'POST' && preg_match('#^/public/events/([^/]+)/register$#',$path,$m)) {
            (new PublicRequestGuard($store))->enforce('register:' . $m[1], 12, 600);
            $result=$publicFlow()->register($m[1],$body());
            $journal->append('public.registration_created',[
                'event_id'=>$m[1],
                'attendee_id'=>$result['attendee']['id'] ?? null,
                'ticket_id'=>$result['ticket']['id'] ?? null,
                'order_id'=>$result['order']['id'] ?? null,
            ]);
            $privateAttendee=(new AttendeeRepository($store))->find((string)($result['attendee']['id'] ?? ''));
            if($privateAttendee){
                $automation()->fire('person.registered',$privateAttendee);
                if(($privateAttendee['status'] ?? '')==='Confirmed') $automation()->fire('attendee.confirmed',$privateAttendee);
            }
            JsonResponse::send($result,201);
        }

        if ($method === 'POST' && $path === '/public/payments/razorpay/verify') {
            (new PublicRequestGuard($store))->enforce('payment-verify', 30, 600);
            $input=$body();
            $token=(string)($input['confirmation_token'] ?? '');
            $attendee=(new AttendeeRepository($store))->findByConfirmationToken($token);
            if(!$attendee) JsonResponse::send(['error'=>'Invalid confirmation token.'],422);

            $orderId=(string)($input['order_id'] ?? '');
            $order=(new OrderRepository($store))->find($orderId);
            if(!$order || ($order['attendee_id'] ?? '') !== ($attendee['id'] ?? '')) JsonResponse::send(['error'=>'Order does not match this registration.'],422);

            $providerOrderId=(string)($input['razorpay_order_id'] ?? '');
            if(($order['provider_order_id'] ?? '') !== $providerOrderId) JsonResponse::send(['error'=>'Razorpay order mismatch.'],422);

            $verified=(new RazorpayCheckoutVerifier((string)getenv('RAZORPAY_KEY_SECRET')))->verify(
                $providerOrderId,
                (string)($input['razorpay_payment_id'] ?? ''),
                (string)($input['razorpay_signature'] ?? '')
            );
            if(!$verified) JsonResponse::send(['error'=>'Payment signature verification failed.'],422);

            $capture=$paymentCapture()->capture($orderId,'razorpay',(string)$input['razorpay_payment_id']);
            if(empty($capture['duplicate']) && !empty($capture['attendee'])){
                $context=(array)$capture['attendee'];
                $context['event_id']=$order['event_id'] ?? ($context['event_id'] ?? '');
                $context['order_id']=$orderId;
                $automation()->fire('payment.captured',$context);
                if(($context['status'] ?? '')==='Confirmed') $automation()->fire('attendee.confirmed',$context);
            }
            $result=$publicFlow()->confirmation($token);
            $result['receipt']=(new ReceiptService())->build(
                (array)($result['event'] ?? []),
                (array)($result['attendee'] ?? []),
                (array)($result['ticket'] ?? []),
                (array)($result['order'] ?? []),
                (new WorkspaceRepository($store))->current(),
            );
            JsonResponse::send($result);
        }

        if ($method === 'GET' && preg_match('#^/public/confirmations/([a-f0-9]{32,})$#',$path,$m)) {
            $result=$publicFlow()->confirmation($m[1]);
            $result['receipt']=(new ReceiptService())->build(
                (array)($result['event'] ?? []),
                (array)($result['attendee'] ?? []),
                (array)($result['ticket'] ?? []),
                (array)($result['order'] ?? []),
                (new WorkspaceRepository($store))->current(),
            );
            JsonResponse::send($result);
        }

        if ($method === 'POST' && preg_match('#^/public/concierge/([a-f0-9]{32,})$#',$path,$m)) {
            (new PublicRequestGuard($store))->enforce('concierge', 40, 600);
            $attendee=(new AttendeeRepository($store))->findByConfirmationToken($m[1]);
            if(!$attendee) JsonResponse::send(['error'=>'Registration not found.'],404);
            $input=$body();
            $question=trim((string)($input['question']??''));
            if($question==='') JsonResponse::send(['error'=>'Question is required.'],422);
            $graph=(new EventGraphBuilder($store))->build((string)$attendee['event_id']);
            JsonResponse::send($intelligence()->concierge($question,(string)$attendee['id'],$graph));
        }

        JsonResponse::send(['error'=>'Public endpoint not found.'],404);
    }

    if ($auth->setupRequired()) {
        JsonResponse::send(['error'=>'Initial administrator setup is required.','code'=>'SETUP_REQUIRED'], 428);
    }

    $auth->requireUser();
    if (!in_array($method, ['GET','HEAD'], true)) $auth->validateCsrf();

    if ($method === 'POST' && $path === '/scanner/verify') {
        $auth->requirePermission('attendees.checkin');
        $input=$body();
        JsonResponse::send($scanner()->verify((string)($input['payload'] ?? ''),(string)($input['zone_id'] ?? '')));
    }

    if ($method === 'POST' && $path === '/scanner/checkin') {
        $user=$auth->requirePermission('attendees.checkin');
        $input=$body();
        $result=$scanner()->checkin((string)($input['payload'] ?? ''),(string)$user['id'],(string)($input['zone_id'] ?? ''));
        if(!empty($result['allowed'])){
            $journal->append('attendee.checked_in',[
                'attendee_id'=>$result['attendee']['id'] ?? null,
                'event_id'=>$result['event_id'] ?? null,
                'operator_id'=>$user['id'],
                'already_checked_in'=>$result['already_checked_in'] ?? false,
            ]);
            if(empty($result['already_checked_in'])){
                $privateAttendee=(new AttendeeRepository($store))->find((string)($result['attendee']['id'] ?? ''));
                if($privateAttendee) $automation()->fire('attendee.checked_in',$privateAttendee);
            }
        }
        JsonResponse::send($result,!empty($result['allowed'])?200:422);
    }

    if ($method === 'GET' && $path === '/workspace') {
        $auth->requirePermission('workspace.view');
        JsonResponse::send((new WorkspaceRepository($store))->current());
    }
    if (in_array($method, ['PUT','PATCH'], true) && $path === '/workspace') {
        $auth->requirePermission('workspace.manage');
        $workspace = (new WorkspaceRepository($store))->update($body());
        $journal->append('workspace.updated', ['workspace_id'=>$workspace['id']]);
        JsonResponse::send($workspace);
    }

    if ($method === 'GET' && $path === '/dashboard') {
        $auth->requirePermission('analytics.view');
        JsonResponse::send((new AnalyticsService($store))->dashboard($eventQuery));
    }

    // Phase 3 — EventOS Intelligence
    if ($method === 'GET' && $path === '/intelligence/overview') {
        $auth->requirePermission('intelligence.view');
        $eventId=(string)($_GET['event_id'] ?? 'evt_001');
        $graph=(new EventGraphBuilder($store))->build($eventId);
        JsonResponse::send([
            'event_id'=>$eventId,
            'metrics'=>$graph['metrics'],
            'analysis'=>$intelligence()->analyze($graph),
            'graph'=>[
                'nodes'=>array_map('count',(array)$graph['nodes']),
                'edges'=>count((array)$graph['edges']),
                'generated_at'=>$graph['generated_at'],
            ],
        ]);
    }

    if ($method === 'GET' && $path === '/intelligence/graph') {
        $auth->requirePermission('intelligence.view');
        $eventId=(string)($_GET['event_id'] ?? 'evt_001');
        JsonResponse::send((new EventGraphBuilder($store))->build($eventId));
    }

    if ($method === 'POST' && $path === '/intelligence/copilot') {
        $auth->requirePermission('intelligence.use');
        $input=$body();
        $eventId=(string)($input['event_id'] ?? 'evt_001');
        $question=trim((string)($input['question'] ?? ''));
        if($question==='') JsonResponse::send(['error'=>'Question is required.'],422);
        $graph=(new EventGraphBuilder($store))->build($eventId);
        $result=$intelligence()->copilot($question,$graph);
        $journal->append('intelligence.copilot_asked',[
            'event_id'=>$eventId,
            'question_hash'=>hash('sha256',$question),
        ]);
        JsonResponse::send($result);
    }

    if ($method === 'GET' && $path === '/intelligence/actions') {
        $auth->requirePermission('intelligence.view');
        JsonResponse::send($forEvent((new ActionProposalRepository($store))->all(),$eventQuery));
    }

    if ($method === 'POST' && $path === '/intelligence/actions') {
        $user=$auth->requirePermission('intelligence.use');
        $record=(new ActionProposalRepository($store))->create($body(),(string)$user['id']);
        $journal->append('intelligence.action_proposed',['proposal_id'=>$record['id'],'type'=>$record['type']]);
        JsonResponse::send($record,201);
    }

    if ($method === 'POST' && preg_match('#^/intelligence/actions/([^/]+)/(approve|reject)$#',$path,$m)) {
        $user=$auth->requirePermission('intelligence.manage');
        $decision=$m[2]==='approve'?'approved':'rejected';
        $repo=new ActionProposalRepository($store);
        $record=$repo->decide($m[1],$decision,(string)$user['id']);
        if(!$record) JsonResponse::send(['error'=>'Action proposal not found.'],404);
        if($decision==='approved'){
            $result=(new ApprovedActionService($store))->execute($record);
            $record=$repo->attachResult($m[1],$result) ?? $record;
            $journal->append('intelligence.action_approved',['proposal_id'=>$m[1],'type'=>$record['type']]);
        }else{
            $journal->append('intelligence.action_rejected',['proposal_id'=>$m[1],'type'=>$record['type']]);
        }
        JsonResponse::send($record);
    }

    $events = new EventRepository($store);
    if ($method === 'GET' && $path === '/events') {
        $auth->requirePermission('events.view');
        JsonResponse::send(array_map($eventView,$events->all()));
    }
    if ($method === 'POST' && $path === '/events') {
        $auth->requirePermission('events.manage');
        $input = $body();
        if (trim((string)($input['name'] ?? '')) === '') JsonResponse::send(['error'=>'Event name is required.'], 422);
        $event = $events->create($input);

        $registrationInput=(array)($input['registration']??[]);
        (new RegistrationRepository($store))->save($event['id'],[
            'title'=>(string)($registrationInput['title']??($event['name'].' Registration')),
            'approval_mode'=>(string)($registrationInput['approval_mode']??'auto'),
            'categories'=>(array)($registrationInput['categories']??['General']),
            'fields'=>(array)($registrationInput['fields']??[
                ['id'=>'fld_name','label'=>'Full Name','type'=>'text','required'=>true,'visibility'=>'always'],
                ['id'=>'fld_email','label'=>'Email Address','type'=>'email','required'=>true,'visibility'=>'always'],
                ['id'=>'fld_phone','label'=>'Mobile Number','type'=>'phone','required'=>true,'visibility'=>'always'],
                ['id'=>'fld_category','label'=>'Category','type'=>'select','required'=>true,'visibility'=>'always'],
                ['id'=>'fld_company','label'=>'Company Name','type'=>'text','required'=>false,'visibility'=>'always'],
            ]),
        ]);

        (new VenueRepository($store))->save($event['id'],[
            'name'=>(string)($input['venue_name']??''),
            'address'=>(string)($input['location']??''),
            'zones'=>[],
            'seating'=>[],
        ]);

        $ticketInput=(array)($input['ticket']??[]);
        if(!empty($ticketInput['enabled'])){
            (new TicketRepository($store))->create([
                'event_id'=>$event['id'],
                'name'=>(string)($ticketInput['name']??'General Admission'),
                'price'=>(int)($ticketInput['price']??0),
                'quantity'=>(int)($ticketInput['quantity']??100),
                'status'=>'Active',
                'sale_start'=>(string)($ticketInput['sale_start']??''),
                'sale_end'=>(string)($ticketInput['sale_end']??''),
            ]);
        }

        (new BadgeTemplateRepository($store))->create([
            'event_id'=>$event['id'],
            'name'=>'Standard Badge',
            'category'=>'All',
            'background'=>'#ffffff',
            'accent'=>(string)($event['branding']['primary_color']??'#4f46e5'),
            'show_qr'=>true,
            'fields'=>['name','company','category'],
        ]);

        $journal->append('event.created', ['event_id'=>$event['id'],'name'=>$event['name']]);
        JsonResponse::send($event, 201);
    }
    if (preg_match('#^/events/([^/]+)$#', $path, $m)) {
        $eventId = $m[1];
        if ($method === 'GET') {
            $auth->requirePermission('events.view');
            $event = $events->find($eventId);
            JsonResponse::send($event ? $eventView($event) : ['error'=>'Event not found.'], $event ? 200 : 404);
        }
        if (in_array($method, ['PUT','PATCH'], true)) {
            $auth->requirePermission('events.manage');
            $input=$body();
            $existing=$events->find($eventId);
            if(!$existing) JsonResponse::send(['error'=>'Event not found.'],404);
            $candidate=array_replace_recursive($existing,$input);
            if(in_array((string)($candidate['status']??''),['Published','Live'],true)) $assertPublishable($candidate);
            $event = $events->update($eventId,$input);
            if (!$event) JsonResponse::send(['error'=>'Event not found.'],404);
            $journal->append('event.updated',['event_id'=>$eventId,'status'=>$event['status']??null]);
            JsonResponse::send($eventView($event));
        }
    }

    $registration = new RegistrationRepository($store);
    if (preg_match('#^/events/([^/]+)/registration$#', $path, $m)) {
        $eventId=$m[1];
        if ($method === 'GET') {
            $auth->requirePermission('registration.view');
            JsonResponse::send($registration->schema($eventId));
        }
        if (in_array($method,['PUT','PATCH'],true)) {
            $auth->requirePermission('registration.manage');
            $schema=$registration->save($eventId,$body());
            $journal->append('registration.schema_updated',['event_id'=>$eventId]);
            JsonResponse::send($schema);
        }
    }

    $invitations = new InvitationRepository($store);
    if ($method === 'GET' && $path === '/invitations') {
        $auth->requirePermission('registration.view');
        JsonResponse::send($forEvent($invitations->all(),$eventQuery));
    }
    if ($method === 'POST' && $path === '/invitations') {
        $auth->requirePermission('registration.manage');
        $input=$body();
        if(trim((string)($input['event_id']??''))==='') JsonResponse::send(['error'=>'Event is required.'],422);
        if (!filter_var((string)($input['email'] ?? ''), FILTER_VALIDATE_EMAIL)) JsonResponse::send(['error'=>'Valid email required.'],422);
        $invite=$invitations->create($input);
        $journal->append('invitation.created',['invitation_id'=>$invite['id']]);
        JsonResponse::send($invite,201);
    }

    $attendees = new AttendeeRepository($store);
    if ($method === 'GET' && $path === '/attendees') {
        $auth->requirePermission('attendees.view');
        JsonResponse::send($forEvent($attendees->all(),$eventQuery));
    }
    if ($method === 'POST' && $path === '/attendees') {
        $auth->requirePermission('attendees.manage');
        $input=$body();
        $event=$requireEvent((string)($input['event_id']??''));
        $schema=(new RegistrationRepository($store))->schema((string)$event['id']);
        $category=trim((string)($input['category']??'General'));
        if(!in_array($category,(array)($schema['categories']??[]),true)) throw new InvalidArgumentException('Attendee category is not configured for this event.');
        $ticketId=trim((string)($input['ticket_id']??''));
        if($ticketId!==''){
            $ticket=(new TicketRepository($store))->find($ticketId);
            if(!$ticket || ($ticket['event_id']??'')!==$event['id']) throw new InvalidArgumentException('Ticket does not belong to this event.');
        }
        $record=$attendees->create($input);
        $journal->append('attendee.created',['attendee_id'=>$record['id'],'event_id'=>$record['event_id']]);
        JsonResponse::send($record,201);
    }
    if ($method === 'GET' && $path === '/attendees/export') {
        $auth->requirePermission('attendees.view');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="digisangam-attendees.csv"');
        echo CsvService::encode($forEvent($attendees->all(),$eventQuery));
        exit;
    }
    if ($method === 'POST' && $path === '/attendees/import') {
        $auth->requirePermission('attendees.manage');
        $input=$body();
        $decoded=CsvService::decode((string)($input['csv'] ?? ''));
        $importEvent=trim((string)($input['event_id']??$eventQuery));
        if($importEvent==='') JsonResponse::send(['error'=>'Event is required for import.'],422);
        $decoded=array_map(static fn(array $row): array => $row+['event_id'=>$importEvent],$decoded);
        $result=$attendees->import($decoded);
        $journal->append('attendees.imported',['created'=>$result['created'],'skipped'=>$result['skipped']]);
        JsonResponse::send($result);
    }
    if (preg_match('#^/attendees/([^/]+)/credential$#',$path,$m) && $method === 'GET') {
        $auth->requirePermission('attendees.view');
        $record=$attendees->find($m[1]);
        if(!$record) JsonResponse::send(['error'=>'Attendee not found.'],404);
        JsonResponse::send((new CredentialService($credentialSecret()))->issue((string)$record['id'],(string)($record['event_id'] ?? 'evt_001')));
    }
    if (preg_match('#^/attendees/([^/]+)$#',$path,$m)) {
        $id=$m[1];
        if($method === 'GET') {
            $auth->requirePermission('attendees.view');
            $record=$attendees->find($id);
            JsonResponse::send($record ?? ['error'=>'Attendee not found.'],$record?200:404);
        }
        if(in_array($method,['PUT','PATCH'],true)) {
            $auth->requirePermission('attendees.manage');
            $before=$attendees->find($id);
            $record=$attendees->update($id,$body());
            if(!$record) JsonResponse::send(['error'=>'Attendee not found.'],404);
            $journal->append('attendee.updated',['attendee_id'=>$id,'status'=>$record['status'] ?? null]);
            if(($before['status'] ?? '')!=='Confirmed' && ($record['status'] ?? '')==='Confirmed') $automation()->fire('attendee.confirmed',$record);
            JsonResponse::send($record);
        }
    }

    if ($method === 'POST' && $path === '/credentials/verify') {
        $auth->requirePermission('attendees.view');
        $input=$body();
        $data=(new CredentialService($credentialSecret()))->verify((string)($input['token'] ?? ''));
        JsonResponse::send(['valid'=>$data !== null,'credential'=>$data],$data?200:422);
    }

    $tickets = new TicketRepository($store);
    if ($method === 'GET' && $path === '/tickets') {
        $auth->requirePermission('tickets.view');
        JsonResponse::send($forEvent($tickets->all(),$eventQuery));
    }
    if ($method === 'POST' && $path === '/tickets') {
        $auth->requirePermission('tickets.manage');
        $input=$body();
        $requireEvent((string)($input['event_id']??''));
        $ticket=$tickets->create($input);
        $journal->append('ticket.created',['ticket_id'=>$ticket['id'],'event_id'=>$ticket['event_id']]);
        JsonResponse::send($ticket,201);
    }
    if (preg_match('#^/tickets/([^/]+)$#',$path,$m) && in_array($method,['PUT','PATCH'],true)) {
        $auth->requirePermission('tickets.manage');
        $ticket=$tickets->update($m[1],$body());
        if(!$ticket) JsonResponse::send(['error'=>'Ticket not found.'],404);
        $journal->append('ticket.updated',['ticket_id'=>$m[1]]);
        JsonResponse::send($ticket);
    }

    $orders = new OrderRepository($store);
    if ($method === 'GET' && $path === '/orders') {
        $auth->requirePermission('commerce.view');
        JsonResponse::send($forEvent($orders->all(),$eventQuery));
    }
    if ($method === 'POST' && $path === '/orders') {
        $auth->requirePermission('commerce.manage');
        $input=$body();
        $event=$requireEvent((string)($input['event_id']??''));
        $attendeeId=trim((string)($input['attendee_id']??''));
        if($attendeeId!==''){
            $attendee=(new AttendeeRepository($store))->find($attendeeId);
            if(!$attendee || ($attendee['event_id']??'')!==$event['id']) throw new InvalidArgumentException('Attendee does not belong to this event.');
        }
        $ticketId=trim((string)($input['ticket_id']??''));
        if($ticketId!==''){
            $ticket=(new TicketRepository($store))->find($ticketId);
            if(!$ticket || ($ticket['event_id']??'')!==$event['id']) throw new InvalidArgumentException('Ticket does not belong to this event.');
        }
        $input['currency']=strtoupper((string)($input['currency']??$event['currency']??'INR'));
        $order=$orders->create($input);
        $journal->append('order.created',['order_id'=>$order['id'],'amount'=>$order['amount'],'event_id'=>$order['event_id']]);
        JsonResponse::send($order,201);
    }

    // Phase 2 — Communications
    $campaigns=new CampaignRepository($store);
    if($method==='GET' && $path==='/campaigns'){
        $auth->requirePermission('communications.view');
        JsonResponse::send($forEvent($campaigns->all(),$eventQuery));
    }
    if($method==='POST' && $path==='/campaigns'){
        $auth->requirePermission('communications.manage');
        $record=$campaigns->create($body());
        $journal->append('campaign.created',['campaign_id'=>$record['id']]);
        JsonResponse::send($record,201);
    }
    if(preg_match('#^/campaigns/([^/]+)$#',$path,$m) && in_array($method,['PUT','PATCH'],true)){
        $auth->requirePermission('communications.manage');
        $record=$campaigns->update($m[1],$body());
        if(!$record) JsonResponse::send(['error'=>'Campaign not found.'],404);
        JsonResponse::send($record);
    }
    if($method==='POST' && preg_match('#^/campaigns/([^/]+)/dispatch$#',$path,$m)){
        $auth->requirePermission('communications.manage');
        $result=(new CampaignDispatchService($campaigns,new AttendeeRepository($store),new NotificationOutbox($store)))->dispatch($m[1]);
        $journal->append('campaign.dispatched',['campaign_id'=>$m[1],'queued'=>$result['queued']??0]);
        JsonResponse::send($result);
    }

    // Phase 2 — Automation
    $workflows=new WorkflowRepository($store);
    if($method==='GET' && $path==='/automations'){
        $auth->requirePermission('automation.view');
        JsonResponse::send($forEvent($workflows->all(),$eventQuery));
    }
    if($method==='POST' && $path==='/automations'){
        $auth->requirePermission('automation.manage');
        $record=$workflows->create($body());
        $journal->append('automation.created',['workflow_id'=>$record['id']]);
        JsonResponse::send($record,201);
    }
    if(preg_match('#^/automations/([^/]+)$#',$path,$m) && in_array($method,['PUT','PATCH'],true)){
        $auth->requirePermission('automation.manage');
        $record=$workflows->update($m[1],$body());
        if(!$record) JsonResponse::send(['error'=>'Workflow not found.'],404);
        JsonResponse::send($record);
    }
    if($method==='POST' && $path==='/automations/fire'){
        $auth->requirePermission('automation.manage');
        $input=$body();
        JsonResponse::send((new WorkflowEngine($workflows,new NotificationOutbox($store)))->fire(
            (string)($input['trigger']??''),
            (array)($input['context']??[])
        ));
    }

    // Phase 2 — Badge templates
    $badges=new BadgeTemplateRepository($store);
    if($method==='GET' && $path==='/badges'){
        $auth->requirePermission('badges.view');
        JsonResponse::send($forEvent($badges->all(),$eventQuery));
    }
    if($method==='POST' && $path==='/badges'){
        $auth->requirePermission('badges.manage');
        JsonResponse::send($badges->create($body()),201);
    }
    if(preg_match('#^/badges/([^/]+)$#',$path,$m) && in_array($method,['PUT','PATCH'],true)){
        $auth->requirePermission('badges.manage');
        $record=$badges->update($m[1],$body());
        JsonResponse::send($record??['error'=>'Badge template not found.'],$record?200:404);
    }

    // Phase 2 — Agenda
    $sessions=new SessionRepository($store);
    if($method==='GET' && $path==='/sessions'){
        $auth->requirePermission('agenda.view');
        JsonResponse::send($forEvent($sessions->all(),$eventQuery));
    }
    if($method==='POST' && $path==='/sessions'){
        $auth->requirePermission('agenda.manage');
        JsonResponse::send($sessions->create($body()),201);
    }
    if(preg_match('#^/sessions/([^/]+)$#',$path,$m) && in_array($method,['PUT','PATCH'],true)){
        $auth->requirePermission('agenda.manage');
        $record=$sessions->update($m[1],$body());
        JsonResponse::send($record??['error'=>'Session not found.'],$record?200:404);
    }

    // Phase 2 — Session attendance
    if($method==='GET' && preg_match('#^/sessions/([^/]+)/attendance$#',$path,$m)){
        $auth->requirePermission('agenda.view');
        JsonResponse::send((new SessionAttendanceRepository($store))->all($m[1]));
    }
    if($method==='POST' && preg_match('#^/sessions/([^/]+)/enter$#',$path,$m)){
        $user=$auth->requirePermission('attendees.checkin');
        $input=$body();
        $result=(new SessionAccessService(
            new CredentialService($credentialSecret()),
            new AttendeeRepository($store),
            new OrderRepository($store),
            new SessionRepository($store),
            new SessionAttendanceRepository($store),
        ))->enter($m[1],(string)($input['payload']??''),(string)$user['id']);
        if(!empty($result['allowed']) && empty($result['duplicate'])){
            $journal->append('session.entered',[
                'session_id'=>$m[1],
                'attendee_id'=>$result['attendee']['id']??null,
                'operator_id'=>$user['id'],
            ]);
            $privateAttendee=(new AttendeeRepository($store))->find((string)($result['attendee']['id']??''));
            if($privateAttendee) $automation()->fire('session.entered',$privateAttendee+['session_id'=>$m[1]]);
        }
        JsonResponse::send($result,!empty($result['allowed'])?200:422);
    }

    // Phase 2 — Exhibitors and sponsors
    $exhibitors=new ExhibitorRepository($store);
    if($method==='GET' && $path==='/exhibitors'){
        $auth->requirePermission('exhibitors.view');
        JsonResponse::send($forEvent($exhibitors->all(),$eventQuery));
    }
    if($method==='POST' && $path==='/exhibitors'){
        $auth->requirePermission('exhibitors.manage');
        JsonResponse::send($exhibitors->create($body()),201);
    }
    if(preg_match('#^/exhibitors/([^/]+)$#',$path,$m) && in_array($method,['PUT','PATCH'],true)){
        $auth->requirePermission('exhibitors.manage');
        $record=$exhibitors->update($m[1],$body());
        JsonResponse::send($record??['error'=>'Exhibitor not found.'],$record?200:404);
    }

    // Phase 2 — Exhibitor leads and meetings
    if($method==='GET' && $path==='/leads'){
        $auth->requirePermission('exhibitors.view');
        JsonResponse::send($forEvent((new LeadRepository($store))->all(),$eventQuery));
    }
    if($method==='POST' && $path==='/leads'){
        $auth->requirePermission('exhibitors.manage');
        $record=(new LeadRepository($store))->create($body());
        $journal->append('lead.captured',['lead_id'=>$record['id'],'exhibitor_id'=>$record['exhibitor_id']]);
        JsonResponse::send($record,201);
    }
    if($method==='GET' && $path==='/meetings'){
        $auth->requirePermission('exhibitors.view');
        JsonResponse::send($forEvent((new MeetingRepository($store))->all(),$eventQuery));
    }
    if($method==='POST' && $path==='/meetings'){
        $auth->requirePermission('exhibitors.manage');
        $record=(new MeetingRepository($store))->create($body());
        $journal->append('meeting.booked',['meeting_id'=>$record['id'],'exhibitor_id'=>$record['exhibitor_id']]);
        JsonResponse::send($record,201);
    }
    if(preg_match('#^/meetings/([^/]+)$#',$path,$m) && in_array($method,['PUT','PATCH'],true)){
        $auth->requirePermission('exhibitors.manage');
        $record=(new MeetingRepository($store))->update($m[1],$body());
        JsonResponse::send($record??['error'=>'Meeting not found.'],$record?200:404);
    }

    // Phase 2 — Badge print queue
    if($method==='GET' && $path==='/badge-prints'){
        $auth->requirePermission('badges.view');
        JsonResponse::send($forEvent((new PrintJobRepository($store))->all(),$eventQuery));
    }
    if($method==='POST' && $path==='/badge-prints'){
        $auth->requirePermission('badges.manage');
        $record=(new PrintJobRepository($store))->create($body());
        $journal->append('badge.print_queued',['print_job_id'=>$record['id'],'attendee_id'=>$record['attendee_id']]);
        JsonResponse::send($record,201);
    }
    if(preg_match('#^/badge-prints/([^/]+)$#',$path,$m) && in_array($method,['PUT','PATCH'],true)){
        $auth->requirePermission('badges.manage');
        $record=(new PrintJobRepository($store))->update($m[1],$body());
        JsonResponse::send($record??['error'=>'Print job not found.'],$record?200:404);
    }

    // Phase 2 — Venue, zones and seating
    if($method==='GET' && preg_match('#^/venue/([^/]+)$#',$path,$m)){
        $auth->requirePermission('venue.view');
        JsonResponse::send((new VenueRepository($store))->get($m[1]));
    }
    if(in_array($method,['PUT','PATCH'],true) && preg_match('#^/venue/([^/]+)$#',$path,$m)){
        $auth->requirePermission('venue.manage');
        $record=(new VenueRepository($store))->save($m[1],$body());
        $journal->append('venue.updated',['event_id'=>$m[1]]);
        JsonResponse::send($record);
    }

    if($method==='GET' && preg_match('#^/venue/([^/]+)/seats$#',$path,$m)){
        $auth->requirePermission('venue.view');
        JsonResponse::send((new SeatAssignmentRepository($store))->all($m[1]));
    }
    if($method==='POST' && preg_match('#^/venue/([^/]+)/seats$#',$path,$m)){
        $auth->requirePermission('venue.manage');
        $record=(new SeatAssignmentRepository($store))->assign($m[1],$body());
        $journal->append('seat.assigned',['event_id'=>$m[1],'attendee_id'=>$record['attendee_id'],'seat'=>$record['seat']]);
        JsonResponse::send($record,201);
    }

    // Phase 2 — signed offline package for OnGround clients
    if($method==='GET' && preg_match('#^/onground/snapshot/([^/]+)$#',$path,$m)){
        $auth->requirePermission('onground.view');
        JsonResponse::send((new OfflineSnapshotService(
            new AttendeeRepository($store),
            new TicketRepository($store),
            new VenueRepository($store),
            new SessionRepository($store),
            new BadgeTemplateRepository($store),
            $credentialSecret(),
        ))->build($m[1]));
    }

    if($method==='POST' && $path==='/onground/sync'){
        $user=$auth->requirePermission('attendees.checkin');
        $input=$body();
        $results=[];
        foreach((array)($input['items']??[]) as $item){
            $localId=(string)($item['local_id']??'');
            $result=$scanner()->checkin((string)($item['payload']??''),(string)$user['id'],(string)($item['zone_id']??''));
            $results[]=['local_id'=>$localId,'result'=>$result];
            if(!empty($result['allowed'])){
                $journal->append('attendee.checked_in.offline_sync',[
                    'attendee_id'=>$result['attendee']['id']??null,
                    'event_id'=>$result['event_id']??null,
                    'operator_id'=>$user['id'],
                    'scanned_at'=>$item['scanned_at']??null,
                    'already_checked_in'=>$result['already_checked_in']??false,
                ]);
            }
        }
        JsonResponse::send(['synced'=>count($results),'results'=>$results]);
    }

    JsonResponse::send(['error'=>'Not found','path'=>$path], 404);
} catch (AuthenticationException $e) {
    JsonResponse::send(['error'=>$e->getMessage(),'code'=>'AUTH_REQUIRED'],401);
} catch (AuthorizationException $e) {
    JsonResponse::send(['error'=>$e->getMessage(),'code'=>'FORBIDDEN'],403);
} catch (RateLimitException $e) {
    JsonResponse::send(['error'=>$e->getMessage(),'code'=>'RATE_LIMITED'],429);
} catch (InvalidArgumentException $e) {
    JsonResponse::send(['error'=>$e->getMessage()],422);
} catch (RuntimeException $e) {
    error_log('[DigiSangam API] ' . $e->getMessage());
    JsonResponse::send(['error'=>$e->getMessage()],409);
} catch (Throwable $e) {
    error_log('[DigiSangam API] ' . $e->getMessage());
    JsonResponse::send(['error'=>'Internal server error'],500);
}
