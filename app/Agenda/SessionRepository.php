<?php
declare(strict_types=1);

namespace DigiSangam\Agenda;

use DigiSangam\Core\Storage\JsonFileStore;

final class SessionRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('agenda/sessions.json', []);
    }

    public function create(array $input): array
    {
        $record=[
            'id'=>'ses_'.bin2hex(random_bytes(6)),
            'event_id'=>trim((string)($input['event_id'] ?? '')),
            'title'=>trim((string)($input['title']??'Untitled Session')),
            'track'=>(string)($input['track']??'Main'),
            'room'=>(string)($input['room']??'Main Hall'),
            'start_at'=>(string)($input['start_at']??''),
            'end_at'=>(string)($input['end_at']??''),
            'capacity'=>max(0,(int)($input['capacity']??0)),
            'speakers'=>(array)($input['speakers']??[]),
            'status'=>(string)($input['status']??'published'),
            'created_at'=>date(DATE_ATOM),
        ];
        if(($record['event_id']??'')==='') throw new \InvalidArgumentException('Event is required.');
        if(($record['event_id']??'')==='') throw new \InvalidArgumentException('Event is required.');
        $rows=$this->all(); array_unshift($rows,$record);
        $this->store->write('agenda/sessions.json',$rows);
        return $record;
    }

    public function update(string $id,array $input): ?array
    {
        $rows=$this->all(); $updated=null;
        foreach($rows as &$row){
            if(($row['id']??'')!==$id) continue;
            foreach(['title','track','room','start_at','end_at','status','capacity'] as $field) if(array_key_exists($field,$input)) $row[$field]=$input[$field];
            if(isset($input['speakers'])&&is_array($input['speakers'])) $row['speakers']=$input['speakers'];
            $row['updated_at']=date(DATE_ATOM); $updated=$row; break;
        }
        unset($row);
        if($updated!==null) $this->store->write('agenda/sessions.json',$rows);
        return $updated;
    }

    private static function defaults(): array
    {
        return [
            ['id'=>'ses_open','event_id'=>'evt_001','title'=>'Opening Keynote','track'=>'Main','room'=>'Grand Ballroom','start_at'=>'2026-10-12T10:00','end_at'=>'2026-10-12T11:00','capacity'=>800,'speakers'=>['Keynote Speaker'],'status'=>'published'],
            ['id'=>'ses_ai','event_id'=>'evt_001','title'=>'AI, Commerce & Experiences','track'=>'Innovation','room'=>'Hall A','start_at'=>'2026-10-12T11:30','end_at'=>'2026-10-12T12:15','capacity'=>250,'speakers'=>['Panel'],'status'=>'published'],
        ];
    }
}
