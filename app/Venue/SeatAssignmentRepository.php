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

    public function assign(string $eventId,array $input,?array $hall=null): array
    {
        $attendeeId=trim((string)($input['attendee_id']??''));
        $hallId=trim((string)($input['hall_id']??''));
        $seat=strtoupper(trim((string)($input['seat']??'')));
        if($attendeeId===''||$hallId===''||$seat==='') throw new \InvalidArgumentException('Attendee, hall and seat are required.');
        if(!preg_match('/^([A-Z]+)([1-9][0-9]*)$/',$seat,$match)) throw new \InvalidArgumentException('Seat must use a row and number such as A12.');

        if($hall!==null){
            if(($hall['id']??'')!==$hallId || ($hall['type']??'')!=='reserved') throw new \InvalidArgumentException('Reserved seating hall is invalid.');
            $rowNumber=self::rowNumber($match[1]);
            $seatNumber=(int)$match[2];
            if($rowNumber<1 || $rowNumber>(int)($hall['rows']??0) || $seatNumber<1 || $seatNumber>(int)($hall['seats_per_row']??0)){
                throw new \InvalidArgumentException('Seat is outside the configured hall layout.');
            }
        }

        return $this->store->transaction('venue/seats/'.$eventId.'.json',static function(array $rows) use ($eventId,$attendeeId,$hallId,$seat): array {
            foreach($rows as $row){
                if(($row['attendee_id']??'')===$attendeeId) throw new \RuntimeException('Attendee already has an assigned seat.');
                if(($row['hall_id']??'')===$hallId && strcasecmp((string)($row['seat']??''),$seat)===0) throw new \RuntimeException('Seat is already assigned.');
            }
            $record=[
                'id'=>'seat_'.bin2hex(random_bytes(6)),'event_id'=>$eventId,'attendee_id'=>$attendeeId,
                'hall_id'=>$hallId,'seat'=>$seat,'assigned_at'=>date(DATE_ATOM),
            ];
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function unassign(string $eventId,string $assignmentId): bool
    {
        return $this->store->transaction('venue/seats/'.$eventId.'.json',static function(array $rows) use ($assignmentId): array {
            $next=array_values(array_filter($rows,static fn(array $row): bool => ($row['id']??'')!==$assignmentId));
            return ['data'=>$next,'result'=>count($next)!==count($rows)];
        },[]);
    }

    private static function rowNumber(string $letters): int
    {
        $value=0;
        foreach(str_split($letters) as $letter) $value=$value*26+(ord($letter)-64);
        return $value;
    }
}
