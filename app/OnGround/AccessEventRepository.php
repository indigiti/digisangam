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

    public function enter(string $eventId,string $attendeeId,string $zoneId='',string $operatorId=''): array
    {
        $zoneId=trim($zoneId) ?: 'venue';
        return $this->store->transaction('access/'.$eventId.'.json',static function(array $rows) use ($eventId,$attendeeId,$zoneId,$operatorId): array {
            $latest=null;
            foreach($rows as $row){
                if(($row['attendee_id']??'')===$attendeeId){$latest=$row;break;}
            }
            $latestAction=(string)($latest['action']??($latest?'enter':''));
            if($latest && $latestAction==='enter' && ($latest['zone_id']??'')===$zoneId){
                return ['data'=>$rows,'result'=>['duplicate'=>true,'event'=>$latest]];
            }
            $record=[
                'id'=>'acc_'.bin2hex(random_bytes(6)),
                'event_id'=>$eventId,
                'attendee_id'=>$attendeeId,
                'zone_id'=>$zoneId,
                'operator_id'=>$operatorId,
                'action'=>'enter',
                'entered_at'=>date(DATE_ATOM),
                'occurred_at'=>date(DATE_ATOM),
            ];
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>['duplicate'=>false,'event'=>$record]];
        },[]);
    }

    public function exit(string $eventId,string $attendeeId,string $zoneId='',string $operatorId=''): array
    {
        return $this->store->transaction('access/'.$eventId.'.json',static function(array $rows) use ($eventId,$attendeeId,$zoneId,$operatorId): array {
            $latest=null;
            foreach($rows as $row){
                if(($row['attendee_id']??'')===$attendeeId){$latest=$row;break;}
            }
            if(!$latest || ($latest['action']??'enter')==='exit'){
                return ['data'=>$rows,'result'=>['duplicate'=>true,'event'=>$latest]];
            }
            $resolvedZone=trim($zoneId) ?: (string)($latest['zone_id']??'venue');
            $record=[
                'id'=>'acc_'.bin2hex(random_bytes(6)),
                'event_id'=>$eventId,
                'attendee_id'=>$attendeeId,
                'zone_id'=>$resolvedZone,
                'operator_id'=>$operatorId,
                'action'=>'exit',
                'exited_at'=>date(DATE_ATOM),
                'occurred_at'=>date(DATE_ATOM),
            ];
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>['duplicate'=>false,'event'=>$record]];
        },[]);
    }

    public function currentOccupancy(string $eventId): array
    {
        $latest=$this->latestByAttendee($eventId);
        $counts=[];
        foreach($latest as $row){
            if(($row['action']??'enter')==='exit') continue;
            $zone=(string)($row['zone_id']??'venue');
            $counts[$zone]=($counts[$zone]??0)+1;
        }
        return $counts;
    }

    public function currentlyInside(string $eventId): array
    {
        return array_values(array_filter($this->latestByAttendee($eventId),static fn(array $row): bool => ($row['action']??'enter')!=='exit'));
    }

    public function recent(string $eventId,int $limit=100): array
    {
        return array_slice($this->all($eventId),0,max(1,min(500,$limit)));
    }

    private function latestByAttendee(string $eventId): array
    {
        $latest=[];
        foreach($this->all($eventId) as $row){
            $attendee=(string)($row['attendee_id']??'');
            if($attendee==='' || isset($latest[$attendee])) continue;
            $latest[$attendee]=$row;
        }
        return $latest;
    }
}
