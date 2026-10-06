<?php
declare(strict_types=1);

namespace DigiSangam\OnGround;

use DigiSangam\Core\Storage\JsonFileStore;

final class AccessEventRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(string $eventId): array
    {
        return $this->store->read('access/'.$eventId.'.json',[]);
    }

    public function enter(string $eventId,string $attendeeId,string $zoneId,string $operatorId=''): array
    {
        if($zoneId==='') throw new \InvalidArgumentException('Zone is required.');
        return $this->store->transaction('access/'.$eventId.'.json',static function(array $rows) use ($eventId,$attendeeId,$zoneId,$operatorId): array {
            $latest=null;
            foreach($rows as $row){
                if(($row['attendee_id']??'')===$attendeeId){$latest=$row;break;}
            }
            if($latest && ($latest['zone_id']??'')===$zoneId){
                return ['data'=>$rows,'result'=>['duplicate'=>true,'event'=>$latest]];
            }
            $record=[
                'id'=>'acc_'.bin2hex(random_bytes(6)),
                'event_id'=>$eventId,
                'attendee_id'=>$attendeeId,
                'zone_id'=>$zoneId,
                'operator_id'=>$operatorId,
                'entered_at'=>date(DATE_ATOM),
            ];
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>['duplicate'=>false,'event'=>$record]];
        },[]);
    }

    public function currentOccupancy(string $eventId): array
    {
        $latest=[];
        foreach($this->all($eventId) as $row){
            $attendee=(string)($row['attendee_id']??'');
            if($attendee==='' || isset($latest[$attendee])) continue;
            $latest[$attendee]=$row;
        }
        $counts=[];
        foreach($latest as $row){
            $zone=(string)($row['zone_id']??'');
            if($zone!=='') $counts[$zone]=($counts[$zone]??0)+1;
        }
        return $counts;
    }
}
