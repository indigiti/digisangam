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
            'event_id'=>(string)($input['event_id']??'evt_001'),
            'exhibitor_id'=>(string)($input['exhibitor_id']??''),
            'attendee_id'=>(string)($input['attendee_id']??''),
            'start_at'=>(string)($input['start_at']??''),
            'duration_minutes'=>max(10,(int)($input['duration_minutes']??30)),
            'location'=>(string)($input['location']??'Meeting Lounge'),
            'status'=>(string)($input['status']??'scheduled'),
            'created_at'=>date(DATE_ATOM),
        ];
        if($record['exhibitor_id']===''||$record['attendee_id']==='') throw new \InvalidArgumentException('Exhibitor and attendee are required.');
        $rows=$this->all(); array_unshift($rows,$record);
        $this->store->write('exhibitors/meetings.json',$rows);
        return $record;
    }

    public function update(string $id,array $input): ?array
    {
        $rows=$this->all();$updated=null;
        foreach($rows as &$row){
            if(($row['id']??'')!==$id) continue;
            foreach(['start_at','duration_minutes','location','status'] as $field) if(array_key_exists($field,$input)) $row[$field]=$input[$field];
            $row['updated_at']=date(DATE_ATOM);$updated=$row;break;
        }
        unset($row);
        if($updated!==null)$this->store->write('exhibitors/meetings.json',$rows);
        return $updated;
    }
}
