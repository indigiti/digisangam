<?php
declare(strict_types=1);

namespace DigiSangam\Events;

use DigiSangam\Core\Storage\JsonFileStore;

final class EventRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('events/index.json', self::demo());
    }

    public function create(array $input): array
    {
        $events = $this->all();
        $record = [
            'id'=>'evt_'.bin2hex(random_bytes(5)),
            'name'=>trim((string)($input['name'] ?? 'Untitled Event')),
            'date'=>trim((string)($input['start_date'] ?? '')),
            'location'=>trim((string)($input['location'] ?? '')),
            'status'=>'Draft','registrations'=>'0','progress'=>20,'created_at'=>date(DATE_ATOM)
        ];
        array_unshift($events, $record);
        $this->store->write('events/index.json', $events);
        return $record;
    }

    private static function demo(): array
    {
        return [
            ['id'=>'evt_001','name'=>'Tech & Innovation Summit 2026','date'=>'12–14 Oct 2026','location'=>'Mumbai, India','status'=>'Live','registrations'=>'18,442','progress'=>88],
            ['id'=>'evt_002','name'=>'Healthcare Excellence Awards','date'=>'8 Nov 2026','location'=>'Delhi, India','status'=>'Published','registrations'=>'5,210','progress'=>64],
            ['id'=>'evt_003','name'=>'Business Leadership Forum','date'=>'22 Nov 2026','location'=>'Bengaluru, India','status'=>'Draft','registrations'=>'0','progress'=>20],
            ['id'=>'evt_004','name'=>'Education Tech Expo','date'=>'10 Dec 2026','location'=>'Hyderabad, India','status'=>'Published','registrations'=>'2,906','progress'=>52],
            ['id'=>'evt_005','name'=>'Sustainability Summit','date'=>'18 Jan 2027','location'=>'Pune, India','status'=>'Published','registrations'=>'1,842','progress'=>45],
        ];
    }
}
