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

    if ($auth->setupRequired()) {
        JsonResponse::send(['error'=>'Initial administrator setup is required.','code'=>'SETUP_REQUIRED'], 428);
    }

    $auth->requireUser();
    if (!in_array($method, ['GET','HEAD'], true)) $auth->validateCsrf();

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
} catch (InvalidArgumentException $e) {
    JsonResponse::send(['error'=>$e->getMessage()],422);
} catch (RuntimeException $e) {
    error_log('[DigiSangam API] ' . $e->getMessage());
    JsonResponse::send(['error'=>$e->getMessage()],409);
} catch (Throwable $e) {
    error_log('[DigiSangam API] ' . $e->getMessage());
    JsonResponse::send(['error'=>'Internal server error'],500);
}
