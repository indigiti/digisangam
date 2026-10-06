<?php
declare(strict_types=1);

use DigiSangam\Analytics\AnalyticsService;
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
use DigiSangam\Notifications\NotificationOutbox;
use DigiSangam\Payments\PaymentService;
use DigiSangam\PublicFlow\PublicRequestGuard;
use DigiSangam\PublicFlow\RateLimitException;
use DigiSangam\PublicFlow\RegistrationCheckoutService;
use DigiSangam\Registration\RegistrationRepository;
use DigiSangam\Tickets\TicketRepository;
use DigiSangam\Workspace\WorkspaceRepository;

require dirname(__DIR__, 2) . '/app/bootstrap.php';

$root = dirname(__DIR__, 2);
$store = new JsonFileStore($root . '/storage');
$journal = new EventJournal($store);
$auth = new AuthService($store);
$path = '/' . trim((string)($_GET['path'] ?? ''), '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$body = static function (): array {
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    return is_array($decoded) ? $decoded : [];
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

            $paymentCapture()->capture($orderId,'razorpay',(string)$input['razorpay_payment_id']);
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
        JsonResponse::send($scanner()->verify((string)($input['payload'] ?? '')));
    }

    if ($method === 'POST' && $path === '/scanner/checkin') {
        $user=$auth->requirePermission('attendees.checkin');
        $input=$body();
        $result=$scanner()->checkin((string)($input['payload'] ?? ''),(string)$user['id']);
        if(!empty($result['allowed'])){
            $journal->append('attendee.checked_in',[
                'attendee_id'=>$result['attendee']['id'] ?? null,
                'event_id'=>$result['event_id'] ?? null,
                'operator_id'=>$user['id'],
                'already_checked_in'=>$result['already_checked_in'] ?? false,
            ]);
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
        JsonResponse::send((new AnalyticsService())->dashboard());
    }

    $events = new EventRepository($store);
    if ($method === 'GET' && $path === '/events') {
        $auth->requirePermission('events.view');
        JsonResponse::send($events->all());
    }
    if ($method === 'POST' && $path === '/events') {
        $auth->requirePermission('events.manage');
        $input = $body();
        if (trim((string)($input['name'] ?? '')) === '') JsonResponse::send(['error'=>'Event name is required.'], 422);
        $event = $events->create($input);
        $journal->append('event.created', ['event_id'=>$event['id'],'name'=>$event['name']]);
        JsonResponse::send($event, 201);
    }
    if (preg_match('#^/events/([^/]+)$#', $path, $m)) {
        $eventId = $m[1];
        if ($method === 'GET') {
            $auth->requirePermission('events.view');
            $event = $events->find($eventId);
            JsonResponse::send($event ?? ['error'=>'Event not found.'], $event ? 200 : 404);
        }
        if (in_array($method, ['PUT','PATCH'], true)) {
            $auth->requirePermission('events.manage');
            $event = $events->update($eventId,$body());
            if (!$event) JsonResponse::send(['error'=>'Event not found.'],404);
            $journal->append('event.updated',['event_id'=>$eventId]);
            JsonResponse::send($event);
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
        JsonResponse::send($invitations->all());
    }
    if ($method === 'POST' && $path === '/invitations') {
        $auth->requirePermission('registration.manage');
        $input=$body();
        if (!filter_var((string)($input['email'] ?? ''), FILTER_VALIDATE_EMAIL)) JsonResponse::send(['error'=>'Valid email required.'],422);
        $invite=$invitations->create($input);
        $journal->append('invitation.created',['invitation_id'=>$invite['id']]);
        JsonResponse::send($invite,201);
    }

    $attendees = new AttendeeRepository($store);
    if ($method === 'GET' && $path === '/attendees') {
        $auth->requirePermission('attendees.view');
        JsonResponse::send($attendees->all());
    }
    if ($method === 'POST' && $path === '/attendees') {
        $auth->requirePermission('attendees.manage');
        $record=$attendees->create($body());
        $journal->append('attendee.created',['attendee_id'=>$record['id']]);
        JsonResponse::send($record,201);
    }
    if ($method === 'GET' && $path === '/attendees/export') {
        $auth->requirePermission('attendees.view');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="digisangam-attendees.csv"');
        echo CsvService::encode($attendees->all());
        exit;
    }
    if ($method === 'POST' && $path === '/attendees/import') {
        $auth->requirePermission('attendees.manage');
        $input=$body();
        $result=$attendees->import(CsvService::decode((string)($input['csv'] ?? '')));
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
            $record=$attendees->update($id,$body());
            if(!$record) JsonResponse::send(['error'=>'Attendee not found.'],404);
            $journal->append('attendee.updated',['attendee_id'=>$id,'status'=>$record['status'] ?? null]);
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
        JsonResponse::send($tickets->all());
    }
    if ($method === 'POST' && $path === '/tickets') {
        $auth->requirePermission('tickets.manage');
        $ticket=$tickets->create($body());
        $journal->append('ticket.created',['ticket_id'=>$ticket['id']]);
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
        JsonResponse::send($orders->all());
    }
    if ($method === 'POST' && $path === '/orders') {
        $auth->requirePermission('commerce.manage');
        $order=$orders->create($body());
        $journal->append('order.created',['order_id'=>$order['id'],'amount'=>$order['amount']]);
        JsonResponse::send($order,201);
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
