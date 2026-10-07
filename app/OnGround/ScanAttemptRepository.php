<?php
declare(strict_types=1);

namespace DigiSangam\OnGround;

use DigiSangam\Core\Storage\JsonFileStore;

final class ScanAttemptRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function record(array $input): array
    {
        $eventId=trim((string)($input['event_id']??''));
        if($eventId==='') return [];
        $record=[
            'id'=>'scan_'.bin2hex(random_bytes(6)),
            'event_id'=>$eventId,
            'attendee_id'=>(string)($input['attendee_id']??''),
            'operator_id'=>(string)($input['operator_id']??''),
            'zone_id'=>(string)($input['zone_id']??''),
            'operation'=>(string)($input['operation']??'verify'),
            'allowed'=>(bool)($input['allowed']??false),
            'reason'=>(string)($input['reason']??'UNKNOWN'),
            'credential_source'=>(string)($input['credential_source']??''),
            'occurred_at'=>date(DATE_ATOM),
        ];
        $this->store->transaction('scan-attempts/'.$eventId.'.json',static function(array $rows) use ($record): array {
            array_unshift($rows,$record);
            if(count($rows)>5000)$rows=array_slice($rows,0,5000);
            return ['data'=>$rows,'result'=>null];
        },[]);
        return $record;
    }

    public function all(string $eventId): array { return $this->store->read('scan-attempts/'.$eventId.'.json',[]); }

    public function recentDenied(string $eventId,int $limit=100): array
    {
        return array_slice(array_values(array_filter($this->all($eventId),static fn(array $row): bool => empty($row['allowed']))),0,max(1,min(500,$limit)));
    }
}
