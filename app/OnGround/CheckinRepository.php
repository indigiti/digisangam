<?php
declare(strict_types=1);

namespace DigiSangam\OnGround;

use DigiSangam\Core\Storage\JsonFileStore;

final class CheckinRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(string $eventId): array
    {
        return $this->store->read('checkins/'.$eventId.'.json',[]);
    }

    public function find(string $eventId,string $attendeeId): ?array
    {
        foreach($this->all($eventId) as $row){
            if(($row['attendee_id']??'')===$attendeeId) return $row;
        }
        return null;
    }

    public function checkin(string $eventId,string $attendeeId,string $operatorId='',string $zoneId=''): array
    {
        return $this->store->transaction('checkins/'.$eventId.'.json',static function(array $rows) use ($eventId,$attendeeId,$operatorId,$zoneId): array {
            foreach($rows as $row){
                if(($row['attendee_id']??'')===$attendeeId){
                    return ['data'=>$rows,'result'=>['already_checked_in'=>true,'checkin'=>$row]];
                }
            }
            $record=[
                'id'=>'chk_'.bin2hex(random_bytes(6)),
                'event_id'=>$eventId,
                'attendee_id'=>$attendeeId,
                'operator_id'=>$operatorId,
                'zone_id'=>$zoneId,
                'checked_in_at'=>date(DATE_ATOM),
            ];
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>['already_checked_in'=>false,'checkin'=>$record]];
        },[]);
    }
}
