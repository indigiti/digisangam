<?php
declare(strict_types=1);

namespace DigiSangam\Venue;

use DigiSangam\Core\Storage\JsonFileStore;

final class SeatAssignmentRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(string $eventId): array
    {
        return $this->store->read('venue/seats/'.$eventId.'.json',[]);
    }

    public function assign(string $eventId,array $input): array
    {
        $attendeeId=(string)($input['attendee_id']??'');
        $hallId=(string)($input['hall_id']??'');
        $seat=(string)($input['seat']??'');
        if($attendeeId===''||$hallId===''||$seat==='') throw new \InvalidArgumentException('Attendee, hall and seat are required.');

        return $this->store->transaction('venue/seats/'.$eventId.'.json',static function(array $rows) use ($eventId,$attendeeId,$hallId,$seat): array {
            foreach($rows as $row){
                if(($row['attendee_id']??'')===$attendeeId) throw new \RuntimeException('Attendee already has an assigned seat.');
                if(($row['hall_id']??'')===$hallId && strcasecmp((string)($row['seat']??''),$seat)===0) throw new \RuntimeException('Seat is already assigned.');
            }
            $record=[
                'id'=>'seat_'.bin2hex(random_bytes(6)),'event_id'=>$eventId,'attendee_id'=>$attendeeId,
                'hall_id'=>$hallId,'seat'=>strtoupper($seat),'assigned_at'=>date(DATE_ATOM),
            ];
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>$record];
        },[]);
    }
}
