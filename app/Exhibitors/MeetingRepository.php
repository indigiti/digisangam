<?php
declare(strict_types=1);

namespace DigiSangam\Exhibitors;

use DigiSangam\Core\Storage\JsonFileStore;

final class MeetingRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('exhibitors/meetings.json',[]);
    }

    public function create(array $input): array
    {
        $record=[
            'id'=>'mtg_'.bin2hex(random_bytes(6)),
            'event_id'=>trim((string)($input['event_id'] ?? '')),
            'exhibitor_id'=>(string)($input['exhibitor_id']??''),
            'attendee_id'=>(string)($input['attendee_id']??''),
            'start_at'=>(string)($input['start_at']??''),
            'duration_minutes'=>max(10,(int)($input['duration_minutes']??30)),
            'location'=>(string)($input['location']??'Meeting Lounge'),
            'status'=>(string)($input['status']??'scheduled'),
            'created_at'=>date(DATE_ATOM),
        ];
        if($record['exhibitor_id']===''||$record['attendee_id']==='') throw new \InvalidArgumentException('Exhibitor and attendee are required.');
        if(($record['event_id']??'')==='') throw new \InvalidArgumentException('Event is required.');
        return $this->store->transaction('exhibitors/meetings.json',static function(array $rows) use ($record): array {
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function update(string $id,array $input): ?array
    {
        return $this->store->transaction('exhibitors/meetings.json',static function(array $rows) use ($id,$input): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id) continue;
                foreach(['start_at','location','status'] as $field){
                    if(array_key_exists($field,$input)) $row[$field]=trim((string)$input[$field]);
                }
                if(array_key_exists('duration_minutes',$input)) $row['duration_minutes']=max(10,(int)$input['duration_minutes']);
                $row['updated_at']=date(DATE_ATOM);$updated=$row;break;
            }
            unset($row);
            return ['data'=>$rows,'result'=>$updated];
        },[]);
    }
}
