<?php
declare(strict_types=1);

namespace DigiSangam\Agenda;

use DigiSangam\Core\Storage\JsonFileStore;

final class SessionAttendanceRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(string $sessionId): array
    {
        return $this->store->read('agenda/attendance/'.$sessionId.'.json',[]);
    }

    public function enter(string $sessionId,string $attendeeId,string $operatorId=''): array
    {
        return $this->store->transaction('agenda/attendance/'.$sessionId.'.json',static function(array $rows) use ($sessionId,$attendeeId,$operatorId): array {
            foreach($rows as $row){
                if(($row['attendee_id']??'')===$attendeeId) return ['data'=>$rows,'result'=>['duplicate'=>true,'attendance'=>$row]];
            }
            $record=[
                'id'=>'sat_'.bin2hex(random_bytes(6)),
                'session_id'=>$sessionId,'attendee_id'=>$attendeeId,'operator_id'=>$operatorId,
                'entered_at'=>date(DATE_ATOM),
            ];
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>['duplicate'=>false,'attendance'=>$record]];
        },[]);
    }
}
