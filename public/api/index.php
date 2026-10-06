<?php
declare(strict_types=1);

use DigiSangam\Analytics\AnalyticsService;
use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Core\EventJournal\EventJournal;
use DigiSangam\Core\Http\JsonResponse;
use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Events\EventRepository;
use DigiSangam\Tickets\TicketRepository;

require dirname(__DIR__, 2) . '/app/bootstrap.php';

$root = dirname(__DIR__, 2);
$store = new JsonFileStore($root . '/storage');
$journal = new EventJournal($store);
$path = '/' . trim((string)($_GET['path'] ?? ''), '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if ($method === 'GET' && $path === '/dashboard') JsonResponse::send((new AnalyticsService())->dashboard());
    if ($method === 'GET' && $path === '/events') JsonResponse::send((new EventRepository($store))->all());

    if ($method === 'POST' && $path === '/events') {
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input) || trim((string)($input['name'] ?? '')) === '') JsonResponse::send(['error'=>'Event name is required.'], 422);
        $event = (new EventRepository($store))->create($input);
        $journal->append('event.created', ['event_id'=>$event['id'],'name'=>$event['name']]);
        JsonResponse::send($event, 201);
    }

    if ($method === 'GET' && $path === '/attendees') JsonResponse::send((new AttendeeRepository($store))->all());
    if ($method === 'GET' && $path === '/tickets') JsonResponse::send((new TicketRepository($store))->all());

    JsonResponse::send(['error'=>'Not found','path'=>$path], 404);
} catch (Throwable $e) {
    error_log('[DigiSangam API] ' . $e->getMessage());
    JsonResponse::send(['error'=>'Internal server error'], 500);
}
