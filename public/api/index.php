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
use DigiSangam\Auth\Authorization;
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
use DigiSangam\PublicFlow\PublicDiscoveryService;
use DigiSangam\Registration\RegistrationRepository;
use DigiSangam\Tickets\TicketRepository;
use DigiSangam\Workspace\WorkspaceRepository;
use DigiSangam\Accreditation\AccreditationRepository;
use DigiSangam\Credentials\CredentialBindingRepository;
use DigiSangam\Developer\ApiKeyRepository;
use DigiSangam\Developer\WebhookRepository;
use DigiSangam\Intelligence\EventBlueprintService;
use DigiSangam\Media\MediaRepository;
use DigiSangam\OnGround\WalkInRegistrationService;
use DigiSangam\Operations\OperationsHealthService;
use DigiSangam\Operations\WorkerHeartbeatRepository;
use DigiSangam\Reports\OperationalReportService;
use DigiSangam\Reports\ReportDefinitionRepository;
use DigiSangam\Wallet\WalletPassRepository;
use DigiSangam\Wallet\WalletPassService;

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
        new MediaRepository($store),
    );
};

$paymentCapture = static function () use ($store,$journal): PaymentCaptureService {
    return new PaymentCaptureService(
        new OrderRepository($store),
        new AttendeeRepository($store),
        new RegistrationRepository($store),
        new NotificationOutbox($store),
        $journal,
        new TicketRepository($store),
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
        new CredentialBindingRepository($store),
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
            new TicketRepository($store),
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

        if ($method === 'GET' && preg_match('#^/public/invitations/([a-f0-9]{32,})$#',$path,$m)) {
            $invite=(new InvitationRepository($store))->findByToken($m[1]);
            if(!$invite || in_array(($invite['status']??'pending'),['revoked','accepted'],true)) JsonResponse::send(['error'=>'Invitation not found or no longer valid.'],404);
            JsonResponse::send([
                'event_id'=>$invite['event_id'],
                'email'=>$invite['email'],
                'category'=>$invite['category'],
                'status'=>$invite['status'],
            ]);
        }

        if ($method === 'GET' && $path === '/public/events') {
            JsonResponse::send((new PublicDiscoveryService(new EventRepository($store),new TicketRepository($store)))->browse());
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

        if ($method === 'POST' && preg_match('#^/public/confirmations/([a-f0-9]{32,})/retry-payment$#',$path,$m)) {
            (new PublicRequestGuard($store))->enforce('payment-retry:'.$m[1],6,600);
            JsonResponse::send($publicFlow()->retryPayment($m[1]),201);
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

        if ($method === 'GET' && preg_match('#^/public/media/([^/]+)$#',$path,$m)) {
            $media=(new MediaRepository($store))->stream($m[1]);
            if(empty($media['public'])) JsonResponse::send(['error'=>'Media not found.'],404);
            header('Content-Type: '.(string)$media['mime']);
            header('Content-Length: '.(string)$media['size']);
            header('Cache-Control: public, max-age=86400');
            header('X-Content-Type-Options: nosniff');
            readfile((string)$media['path']);
            exit;
        }

        if ($method === 'POST' && preg_match('#^/public/events/([^/]+)/media$#',$path,$m)) {
            (new PublicRequestGuard($store))->enforce('media-upload:'.$m[1],20,600);
            $publicFlow()->publicEvent($m[1]);
            if(empty($_FILES['file'])) JsonResponse::send(['error'=>'File is required.'],422);
            $record=(new MediaRepository($store))->saveUpload($_FILES['file'],$m[1],(string)($_POST['kind']??'registration_file'),false);
            JsonResponse::send($record,201);
        }

        if ($method === 'POST' && preg_match('#^/public/confirmations/([a-f0-9]{32,})/wallet$#',$path,$m)) {
            (new PublicRequestGuard($store))->enforce('wallet:'.$m[1],20,600);
            $attendee=(new AttendeeRepository($store))->findByConfirmationToken($m[1]);
            if(!$attendee) JsonResponse::send(['error'=>'Registration not found.'],404);
            if(($attendee['status']??'')!=='Confirmed') JsonResponse::send(['error'=>'Wallet pass is available after confirmation.'],422);
            $event=(new EventRepository($store))->find((string)$attendee['event_id']);
            if(!$event) JsonResponse::send(['error'=>'Event not found.'],404);
            $input=$body();
            $credential=(new CredentialService($credentialSecret()))->issue((string)$attendee['id'],(string)$attendee['event_id']);
            $pass=(new WalletPassService(new WalletPassRepository($store),$credentialSecret()))->issue($event,$attendee,(string)$credential['payload'],(string)($input['platform']??'google'));
            JsonResponse::send($pass,201);
        }

        JsonResponse::send(['error'=>'Public endpoint not found.'],404);
    }

    if (str_starts_with($path,'/developer/v1/')) {
        $key=(string)($_SERVER['HTTP_X_DIGISANGAM_KEY']??'');
        if($key==='') JsonResponse::send(['error'=>'Developer API key is required.'],401);
        if(!preg_match('#^/developer/v1/events/([^/]+)(?:/(attendees|sessions|analytics))?$#',$path,$m)) JsonResponse::send(['error'=>'Developer endpoint not found.'],404);
        $eventId=$m[1];$resource=$m[2]??'event';
        $scope=match($resource){'attendees'=>'attendees.read','sessions'=>'sessions.read','analytics'=>'analytics.read',default=>'events.read'};
        $authorized=(new ApiKeyRepository($store))->authenticate($key,$scope,$eventId);
        if(!$authorized) JsonResponse::send(['error'=>'Invalid API key or scope.'],403);
        $requireEvent($eventId);
        if($resource==='attendees') JsonResponse::send($forEvent((new AttendeeRepository($store))->all(),$eventId));
        if($resource==='sessions') JsonResponse::send($forEvent((new SessionRepository($store))->all(),$eventId));
        if($resource==='analytics') JsonResponse::send((new AnalyticsService($store))->dashboard($eventId));
        JsonResponse::send($eventView((new EventRepository($store))->find($eventId)));
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

    if ($method === 'POST' && $path === '/scanner/exit') {
        $user=$auth->requirePermission('attendees.checkin');
        $input=$body();
        $result=$scanner()->exit((string)($input['payload']??''),(string)$user['id'],(string)($input['zone_id']??''));
        if(!empty($result['allowed']) && empty($result['already_exited'])){
            $journal->append('attendee.exited',[
                'attendee_id'=>$result['attendee']['id']??null,
                'event_id'=>$result['event_id']??null,
                'operator_id'=>$user['id'],
                'zone_id'=>$result['access_event']['event']['zone_id']??null,
            ]);
            $privateAttendee=(new AttendeeRepository($store))->find((string)($result['attendee']['id']??''));
            if($privateAttendee) $automation()->fire('attendee.exited',$privateAttendee);
        }
        JsonResponse::send($result,!empty($result['allowed'])?200:422);
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

    if ($method === 'GET' && $path === '/operations/health') {
        $auth->requirePermission('workspace.view');
        JsonResponse::send((new OperationsHealthService($store,new WorkerHeartbeatRepository($store)))->status());
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

    if ($method === 'GET' && $path === '/team') {
        $auth->requirePermission('workspace.view');
        JsonResponse::send(['users'=>$auth->allUsers(),'roles'=>Authorization::roles()]);
    }
    if ($method === 'POST' && $path === '/team') {
        $actor=$auth->requirePermission('workspace.manage');
        $input=$body();
        $user=$auth->createUser(
            (string)($input['name']??''),
            (string)($input['email']??''),
            (string)($input['password']??''),
            (string)($input['role']??'viewer')
        );
        $journal->append('team.user_created',['user_id'=>$user['id'],'role'=>$user['role'],'actor_id'=>$actor['id']]);
        JsonResponse::send($user,201);
    }
    if (preg_match('#^/team/([^/]+)$#',$path,$m) && in_array($method,['PUT','PATCH'],true)) {
        $actor=$auth->requirePermission('workspace.manage');
        $user=$auth->updateUser($m[1],$body());
        if(!$user) JsonResponse::send(['error'=>'User not found.'],404);
        $journal->append('team.user_updated',['user_id'=>$user['id'],'role'=>$user['role'],'active'=>$user['active']??true,'actor_id'=>$actor['id']]);
        JsonResponse::send($user);
    }

    // Remaining roadmap modules
    if ($method === 'GET' && $path === '/accreditation') {
        $auth->requirePermission('accreditation.view');
        JsonResponse::send($forEvent((new AccreditationRepository($store))->all(),$eventQuery));
    }
    if ($method === 'POST' && $path === '/accreditation') {
        $actor=$auth->requirePermission('accreditation.manage');$input=$body();$eventId=(string)($input['event_id']??'');$requireEvent($eventId);
        $attendee=(new AttendeeRepository($store))->find((string)($input['attendee_id']??''));
        if(!$attendee||($attendee['event_id']??'')!==$eventId) JsonResponse::send(['error'=>'Attendee does not belong to this event.'],422);
        $record=(new AccreditationRepository($store))->create($input);
        $journal->append('accreditation.submitted',['event_id'=>$eventId,'accreditation_id'=>$record['id'],'attendee_id'=>$record['attendee_id'],'actor_id'=>$actor['id']]);
        JsonResponse::send($record,201);
    }
    if (preg_match('#^/accreditation/([^/]+)$#',$path,$m) && in_array($method,['PATCH','PUT'],true)) {
        $actor=$auth->requirePermission('accreditation.manage');
        $record=(new AccreditationRepository($store))->update($m[1],$body(),(string)$actor['id']);
        if(!$record) JsonResponse::send(['error'=>'Accreditation record not found.'],404);
        $journal->append('accreditation.updated',['event_id'=>$record['event_id'],'accreditation_id'=>$record['id'],'status'=>$record['status'],'actor_id'=>$actor['id']]);
        JsonResponse::send($record);
    }

    if ($method === 'POST' && $path === '/onground/walk-in') {
        $actor=$auth->requirePermission('onground.manage');$input=$body();$eventId=(string)($input['event_id']??'');$requireEvent($eventId);
        $service=new WalkInRegistrationService(new EventRepository($store),new RegistrationRepository($store),new TicketRepository($store),new AttendeeRepository($store),new OrderRepository($store),new CredentialService($credentialSecret()));
        $result=$service->register($eventId,$input);
        $journal->append('walkin.registered',['event_id'=>$eventId,'attendee_id'=>$result['attendee']['id'],'actor_id'=>$actor['id']]);
        $automation()->fire('person.registered',$result['attendee']);
        if(($result['attendee']['status']??'')==='Confirmed')$automation()->fire('attendee.confirmed',$result['attendee']);
        JsonResponse::send($result,201);
    }

    if ($method === 'GET' && $path === '/report-definitions') {
        $auth->requirePermission('reports.view');
        JsonResponse::send($forEvent((new ReportDefinitionRepository($store))->all(),$eventQuery));
    }
    if ($method === 'POST' && $path === '/report-definitions') {
        $auth->requirePermission('reports.manage');$input=$body();$requireEvent((string)($input['event_id']??''));
        JsonResponse::send((new ReportDefinitionRepository($store))->create($input),201);
    }
    if (preg_match('#^/report-definitions/([^/]+)$#',$path,$m) && in_array($method,['PATCH','PUT'],true)) {
        $auth->requirePermission('reports.manage');$row=(new ReportDefinitionRepository($store))->update($m[1],$body());JsonResponse::send($row??['error'=>'Report definition not found.'],$row?200:404);
    }
    if ($method === 'DELETE' && preg_match('#^/report-definitions/([^/]+)$#',$path,$m)) {
        $auth->requirePermission('reports.manage');JsonResponse::send(['deleted'=>(new ReportDefinitionRepository($store))->delete($m[1])]);
    }
    if ($method === 'GET' && preg_match('#^/reports/export-definition/([^/]+)$#',$path,$m)) {
        $auth->requirePermission('reports.view');$definition=(new ReportDefinitionRepository($store))->find($m[1]);
        if(!$definition) JsonResponse::send(['error'=>'Report definition not found.'],404);
        $csv=(new OperationalReportService($store))->exportDefinition($definition);
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="digisangam-custom-report.csv"');echo $csv;exit;
    }

    if ($method === 'GET' && $path === '/reports') {
        $auth->requirePermission('reports.view');$requireEvent($eventQuery);
        JsonResponse::send((new OperationalReportService($store))->summary($eventQuery));
    }
    if ($method === 'GET' && $path === '/reports/export') {
        $auth->requirePermission('reports.view');$requireEvent($eventQuery);$type=(string)($_GET['type']??'attendees');
        $csv=(new OperationalReportService($store))->export($eventQuery,$type);
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="digisangam-'.$type.'.csv"');echo $csv;exit;
    }

    if ($method === 'POST' && $path === '/intelligence/event-builder') {
        $auth->requirePermission('events.manage');$input=$body();
        JsonResponse::send($intelligence()->eventBuilder((string)($input['prompt']??'')));
    }

    if ($method === 'POST' && $path === '/media') {
        $auth->requirePermission('media.manage');
        $eventId=trim((string)($_POST['event_id']??''));$requireEvent($eventId);
        if(empty($_FILES['file'])) JsonResponse::send(['error'=>'File is required.'],422);
        $record=(new MediaRepository($store))->saveUpload($_FILES['file'],$eventId,(string)($_POST['kind']??'asset'),(bool)($_POST['public']??false));
        JsonResponse::send($record,201);
    }
    if ($method === 'GET' && preg_match('#^/media/([^/]+)$#',$path,$m)) {
        $auth->requirePermission('media.view');$media=(new MediaRepository($store))->stream($m[1]);
        header('Content-Type: '.(string)$media['mime']);header('Content-Length: '.(string)$media['size']);header('X-Content-Type-Options: nosniff');header('Content-Disposition: inline; filename="'.addslashes((string)($media['name']??$media['id'])).'"');readfile((string)$media['path']);exit;
    }

    if ($method === 'GET' && $path === '/wallet-passes') {
        $auth->requirePermission('wallet.view');JsonResponse::send($forEvent((new WalletPassRepository($store))->all(),$eventQuery));
    }

    if ($method === 'GET' && $path === '/credential-bindings') {
        $auth->requirePermission('credentials.view');JsonResponse::send($forEvent((new CredentialBindingRepository($store))->all(),$eventQuery));
    }
    if ($method === 'POST' && $path === '/credential-bindings') {
        $auth->requirePermission('credentials.manage');$input=$body();$eventId=(string)($input['event_id']??'');$requireEvent($eventId);
        $attendee=(new AttendeeRepository($store))->find((string)($input['attendee_id']??''));
        if(!$attendee||($attendee['event_id']??'')!==$eventId) JsonResponse::send(['error'=>'Attendee does not belong to this event.'],422);
        JsonResponse::send((new CredentialBindingRepository($store))->bind($eventId,(string)$attendee['id'],(string)($input['type']??''),(string)($input['uid']??'')),201);
    }
    if ($method === 'POST' && preg_match('#^/credential-bindings/([^/]+)/revoke$#',$path,$m)) {
        $auth->requirePermission('credentials.manage');$row=(new CredentialBindingRepository($store))->revoke($m[1]);JsonResponse::send($row??['error'=>'Binding not found.'],$row?200:404);
    }

    if ($method === 'GET' && $path === '/developer/keys') {
        $auth->requirePermission('developer.view');JsonResponse::send((new ApiKeyRepository($store))->publicList());
    }
    if ($method === 'POST' && $path === '/developer/keys') {
        $auth->requirePermission('developer.manage');$input=$body();JsonResponse::send((new ApiKeyRepository($store))->create((string)($input['name']??''),(array)($input['scopes']??[]),(string)($input['event_id']??'')),201);
    }
    if ($method === 'POST' && preg_match('#^/developer/keys/([^/]+)/revoke$#',$path,$m)) {
        $auth->requirePermission('developer.manage');$row=(new ApiKeyRepository($store))->revoke($m[1]);JsonResponse::send($row??['error'=>'API key not found.'],$row?200:404);
    }
    if ($method === 'GET' && $path === '/developer/webhook-deliveries') {
        $auth->requirePermission('developer.view');
        $eventId=trim((string)($_GET['event_id']??''));
        $rows=(new WebhookOutboxRepository($store))->all();
        if($eventId!=='') $rows=array_values(array_filter($rows,static fn(array $row): bool => (string)($row['data']['event_id']??'')===$eventId));
        $rows=array_slice($rows,0,100);
        $safe=array_map(static function(array $row): array {
            unset($row['secret']);
            return $row;
        },$rows);
        JsonResponse::send($safe);
    }

    if ($method === 'GET' && $path === '/developer/webhooks') {
        $auth->requirePermission('developer.view');JsonResponse::send($forEvent((new WebhookRepository($store))->publicList(),$eventQuery));
    }
    if ($method === 'POST' && $path === '/developer/webhooks') {
        $auth->requirePermission('developer.manage');$input=$body();$requireEvent((string)($input['event_id']??''));JsonResponse::send((new WebhookRepository($store))->create($input),201);
    }
    if (preg_match('#^/developer/webhooks/([^/]+)$#',$path,$m) && in_array($method,['PATCH','PUT'],true)) {
        $auth->requirePermission('developer.manage');$row=(new WebhookRepository($store))->update($m[1],$body());JsonResponse::send($row??['error'=>'Webhook not found.'],$row?200:404);
    }

    if ($method === 'GET' && $path === '/dashboard') {
        $auth->requirePermission('analytics.view');
        JsonResponse::send((new AnalyticsService($store))->dashboard($eventQuery));
    }

    // Phase 3 — EventOS Intelligence
    if ($method === 'GET' && $path === '/intelligence/overview') {
        $auth->requirePermission('intelligence.view');
        $eventId=trim((string)($_GET['event_id'] ?? ''));
        $requireEvent($eventId);
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
        $eventId=trim((string)($_GET['event_id'] ?? ''));
        $requireEvent($eventId);
        JsonResponse::send((new EventGraphBuilder($store))->build($eventId));
    }

    if ($method === 'POST' && $path === '/intelligence/copilot') {
        $auth->requirePermission('intelligence.use');
        $input=$body();
        $eventId=trim((string)($input['event_id'] ?? ''));
        $requireEvent($eventId);
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

    if($method==='GET' && preg_match('#^/events/([^/]+)/preview$#',$path,$m)){
        $auth->requirePermission('events.view');
        $requireEvent($m[1]);
        JsonResponse::send($publicFlow()->previewEvent($m[1]));
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

    if ($method === 'POST' && preg_match('#^/invitations/([^/]+)/send$#',$path,$m)) {
        $auth->requirePermission('registration.manage');
        $invite=$invitations->find($m[1]);
        if(!$invite) JsonResponse::send(['error'=>'Invitation not found.'],404);
        if(($invite['status']??'')!=='pending') throw new RuntimeException('Only pending invitations can be sent.');
        $event=$requireEvent((string)$invite['event_id']);
        $message=(new NotificationOutbox($store))->queue('email','event_invitation',['email'=>$invite['email']],[
            'event_id'=>$invite['event_id'],'event_name'=>$event['name']??'Event',
            'invitation_id'=>$invite['id'],'invitation_token'=>$invite['token'],'category'=>$invite['category']??'General',
        ]);
        $invite=$invitations->markSent((string)$invite['id'],(string)$message['id'])??$invite;
        $journal->append('invitation.queued',['invitation_id'=>$invite['id'],'event_id'=>$invite['event_id'],'message_id'=>$message['id']]);
        JsonResponse::send(['invitation'=>$invite,'message'=>$message]);
    }

    if ($method === 'POST' && preg_match('#^/invitations/([^/]+)/revoke$#',$path,$m)) {
        $auth->requirePermission('registration.manage');
        $invite=$invitations->revoke($m[1]);
        if(!$invite) JsonResponse::send(['error'=>'Invitation not found.'],404);
        $journal->append('invitation.revoked',['invitation_id'=>$invite['id'],'event_id'=>$invite['event_id']]);
        JsonResponse::send($invite);
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
        $decoded=array_map(static fn(array $row): array => $row+['event_id'=>$importEvent,'source'=>'import'],$decoded);
        $result=$attendees->import($decoded);
        $journal->append('attendees.imported',['created'=>$result['created'],'skipped'=>$result['skipped']]);
        JsonResponse::send($result);
    }
    if (preg_match('#^/attendees/([^/]+)/credential$#',$path,$m) && $method === 'GET') {
        $auth->requirePermission('attendees.view');
        $record=$attendees->find($m[1]);
        if(!$record) JsonResponse::send(['error'=>'Attendee not found.'],404);
        if(($record['status']??'')!=='Confirmed') throw new RuntimeException('Credential is available only for confirmed attendees.');
        $latestOrder=(new OrderRepository($store))->findLatestByAttendee((string)$record['id']);
        if($latestOrder && (int)($latestOrder['amount']??0)>0 && ($latestOrder['status']??'')!=='paid'){
            throw new RuntimeException('Credential cannot be issued until the paid ticket order is settled.');
        }
        JsonResponse::send((new CredentialService($credentialSecret()))->issue((string)$record['id'],(string)($record['event_id'] ?? '')));
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
            $updateInput=$body();
            if(($updateInput['status']??'')==='Confirmed'){
                $latestOrder=(new OrderRepository($store))->findLatestByAttendee($id);
                if($latestOrder && (int)($latestOrder['amount']??0)>0 && ($latestOrder['status']??'')!=='paid'){
                    throw new RuntimeException('Paid ticket order must be settled before attendee confirmation.');
                }
            }
            $record=$attendees->update($id,$updateInput);
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
        $reservedTicket=null;
        if($ticketId!==''){
            $ticket=(new TicketRepository($store))->find($ticketId);
            if(!$ticket || ($ticket['event_id']??'')!==$event['id']) throw new InvalidArgumentException('Ticket does not belong to this event.');
            $reservedTicket=(new TicketRepository($store))->reserveOne($ticketId,(string)$event['id']);
            $input['amount']=(int)($reservedTicket['price']??0);
        }
        $input['currency']=strtoupper((string)($event['currency']??'INR'));
        $input['provider']='manual';
        $input['status']=((int)($input['amount']??0)===0)?'paid':'pending';
        try{
            $order=$orders->create($input);
            if($reservedTicket && ($order['status']??'')==='paid'){
                $reservedTicket=(new TicketRepository($store))->commitReservation($ticketId,(string)$event['id']);
            }
        }catch(Throwable $e){
            if($reservedTicket) (new TicketRepository($store))->releaseReservation($ticketId,(string)$event['id']);
            throw $e;
        }
        $journal->append('order.created',['order_id'=>$order['id'],'amount'=>$order['amount'],'event_id'=>$order['event_id']]);
        JsonResponse::send($order,201);
    }

    if($method==='POST' && preg_match('#^/orders/([^/]+)/capture$#',$path,$m)){
        $auth->requirePermission('commerce.manage');
        $order=$orders->find($m[1]);
        if(!$order) JsonResponse::send(['error'=>'Order not found.'],404);
        $requireEvent((string)($order['event_id']??''));
        if(($order['status']??'')==='refunded') throw new RuntimeException('Refunded orders cannot be captured.');
        $input=$body();
        $reference=trim((string)($input['payment_reference']??''));
        if($reference==='') $reference='MANUAL-'.strtoupper(substr((string)$order['id'],-8));
        $capture=$paymentCapture()->capture((string)$order['id'],'manual',$reference);
        if(empty($capture['duplicate'])&&!empty($capture['attendee'])){
            $context=(array)$capture['attendee'];
            $context['event_id']=$order['event_id']??($context['event_id']??'');
            $context['order_id']=$order['id'];
            $automation()->fire('payment.captured',$context);
            if(($context['status']??'')==='Confirmed') $automation()->fire('attendee.confirmed',$context);
        }
        JsonResponse::send($capture);
    }

    if($method==='POST' && preg_match('#^/orders/([^/]+)/refund$#',$path,$m)){
        $auth->requirePermission('commerce.manage');
        $order=$orders->find($m[1]);
        if(!$order) JsonResponse::send(['error'=>'Order not found.'],404);
        $requireEvent((string)($order['event_id']??''));
        if(($order['status']??'')!=='paid') throw new RuntimeException('Only paid orders can be refunded.');
        $input=$body();
        $reference=trim((string)($input['payment_reference']??$order['payment_reference']??''));
        $order=$orders->updatePayment((string)$order['id'],[
            'status'=>'refunded',
            'provider'=>(string)($order['provider']??'manual'),
            'payment_reference'=>$reference,
        ])??$order;
        $attendee=null;
        if(!empty($order['attendee_id'])){
            $attendee=(new AttendeeRepository($store))->update((string)$order['attendee_id'],['status'=>'Pending']);
        }
        if(!empty($order['ticket_id'])){
            (new TicketRepository($store))->releaseSold((string)$order['ticket_id'],(string)$order['event_id']);
        }
        $journal->append('payment.refunded',['order_id'=>$order['id'],'event_id'=>$order['event_id'],'payment_reference'=>$reference]);
        JsonResponse::send(['order'=>$order,'attendee'=>$attendee]);
    }

    // Phase 2 — Communications
    $campaigns=new CampaignRepository($store);
    if($method==='GET' && $path==='/campaigns'){
        $auth->requirePermission('communications.view');
        JsonResponse::send($forEvent($campaigns->all(),$eventQuery));
    }
    if($method==='POST' && $path==='/campaigns'){
        $auth->requirePermission('communications.manage');
        $input=$body();$event=$requireEvent((string)($input['event_id']??''));
        $input['timezone']=(string)($input['timezone']??$event['timezone']??'UTC');
        $record=$campaigns->create($input);
        $journal->append('campaign.created',['campaign_id'=>$record['id'],'event_id'=>$record['event_id']]);
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
        $input=$body();$requireEvent((string)($input['event_id']??''));
        $record=$workflows->create($input);
        $journal->append('automation.created',['workflow_id'=>$record['id'],'event_id'=>$record['event_id']]);
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
        $context=(array)($input['context']??[]);
        $requireEvent((string)($context['event_id']??''));
        JsonResponse::send((new WorkflowEngine($workflows,new NotificationOutbox($store)))->fire(
            (string)($input['trigger']??''),
            $context,
            (bool)($input['dry_run']??false)
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
        $input=$body();$requireEvent((string)($input['event_id']??''));
        JsonResponse::send($badges->create($input),201);
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
        $input=$body();$requireEvent((string)($input['event_id']??''));
        JsonResponse::send($sessions->create($input),201);
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
        $input=$body();$requireEvent((string)($input['event_id']??''));
        JsonResponse::send($exhibitors->create($input),201);
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
        $input=$body();$event=$requireEvent((string)($input['event_id']??''));
        $exhibitor=null;
        foreach($exhibitors->all() as $row) if(($row['id']??'')===($input['exhibitor_id']??'')){$exhibitor=$row;break;}
        $attendee=(new AttendeeRepository($store))->find((string)($input['attendee_id']??''));
        if(!$exhibitor||($exhibitor['event_id']??'')!==$event['id']) throw new InvalidArgumentException('Exhibitor does not belong to this event.');
        if(!$attendee||($attendee['event_id']??'')!==$event['id']) throw new InvalidArgumentException('Attendee does not belong to this event.');
        $record=(new LeadRepository($store))->create($input);
        $journal->append('lead.captured',['lead_id'=>$record['id'],'exhibitor_id'=>$record['exhibitor_id'],'event_id'=>$record['event_id']]);
        JsonResponse::send($record,201);
    }
    if($method==='GET' && $path==='/meetings'){
        $auth->requirePermission('exhibitors.view');
        JsonResponse::send($forEvent((new MeetingRepository($store))->all(),$eventQuery));
    }
    if($method==='POST' && $path==='/meetings'){
        $auth->requirePermission('exhibitors.manage');
        $input=$body();$event=$requireEvent((string)($input['event_id']??''));
        $exhibitor=null;
        foreach($exhibitors->all() as $row) if(($row['id']??'')===($input['exhibitor_id']??'')){$exhibitor=$row;break;}
        $attendee=(new AttendeeRepository($store))->find((string)($input['attendee_id']??''));
        if(!$exhibitor||($exhibitor['event_id']??'')!==$event['id']) throw new InvalidArgumentException('Exhibitor does not belong to this event.');
        if(!$attendee||($attendee['event_id']??'')!==$event['id']) throw new InvalidArgumentException('Attendee does not belong to this event.');
        $record=(new MeetingRepository($store))->create($input);
        $journal->append('meeting.booked',['meeting_id'=>$record['id'],'exhibitor_id'=>$record['exhibitor_id'],'event_id'=>$record['event_id']]);
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
        $input=$body();$event=$requireEvent((string)($input['event_id']??''));
        $attendee=(new AttendeeRepository($store))->find((string)($input['attendee_id']??''));
        $template=null;
        foreach($badges->all() as $row) if(($row['id']??'')===($input['template_id']??'')){$template=$row;break;}
        if(!$attendee||($attendee['event_id']??'')!==$event['id']) throw new InvalidArgumentException('Attendee does not belong to this event.');
        if(!$template||($template['event_id']??'')!==$event['id']) throw new InvalidArgumentException('Badge template does not belong to this event.');
        $record=(new PrintJobRepository($store))->create($input);
        $journal->append('badge.print_queued',['print_job_id'=>$record['id'],'attendee_id'=>$record['attendee_id'],'event_id'=>$record['event_id']]);
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
        $requireEvent($m[1]);
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
        $event=$requireEvent($m[1]);$input=$body();
        $attendee=(new AttendeeRepository($store))->find((string)($input['attendee_id']??''));
        if(!$attendee||($attendee['event_id']??'')!==$event['id']) throw new InvalidArgumentException('Attendee does not belong to this event.');
        $venue=(new VenueRepository($store))->get($event['id']);$hall=null;
        foreach((array)($venue['seating']??[]) as $row) if(($row['id']??'')===($input['hall_id']??'')){$hall=$row;break;}
        if(!$hall||($hall['type']??'')!=='reserved') throw new InvalidArgumentException('Reserved seating hall not found for this event.');
        $record=(new SeatAssignmentRepository($store))->assign($m[1],$input);
        $journal->append('seat.assigned',['event_id'=>$m[1],'attendee_id'=>$record['attendee_id'],'seat'=>$record['seat']]);
        JsonResponse::send($record,201);
    }

    if($method==='GET' && $path==='/onground/live'){
        $auth->requirePermission('onground.view');
        $eventId=trim((string)($_GET['event_id']??''));
        $requireEvent($eventId);
        $access=new AccessEventRepository($store);
        $attendeeRepo=new AttendeeRepository($store);
        $users=[];
        foreach($auth->allUsers() as $user) $users[(string)$user['id']]=$user;
        $decorate=static function(array $row) use ($attendeeRepo,$users): array {
            $attendee=$attendeeRepo->find((string)($row['attendee_id']??''));
            $operator=$users[(string)($row['operator_id']??'')]??null;
            return $row+[
                'attendee'=>$attendee?[
                    'id'=>$attendee['id'],'name'=>$attendee['name'],'email'=>$attendee['email']??'',
                    'category'=>$attendee['category']??'','company'=>$attendee['company']??'',
                ]:null,
                'operator'=>$operator?['id'=>$operator['id'],'name'=>$operator['name'],'email'=>$operator['email']??'']:null,
            ];
        };
        $recent=array_map($decorate,$access->recent($eventId,(int)($_GET['limit']??100)));
        $inside=array_map($decorate,$access->currentlyInside($eventId));
        JsonResponse::send([
            'event_id'=>$eventId,
            'currently_inside'=>count($inside),
            'occupancy'=>$access->currentOccupancy($eventId),
            'inside'=>$inside,
            'recent'=>$recent,
            'generated_at'=>date(DATE_ATOM),
        ]);
    }

    // Phase 2 — signed offline package for OnGround clients
    if($method==='GET' && preg_match('#^/onground/snapshot/([^/]+)$#',$path,$m)){
        $auth->requirePermission('onground.view');
        $requireEvent($m[1]);
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
