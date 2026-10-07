<?php
declare(strict_types=1);

namespace DigiSangam\Agenda;

use DigiSangam\Core\Storage\JsonFileStore;

final class SessionRepository
{
    private const PATH='agenda/sessions.json';
    private const STATUSES=['draft','published','cancelled'];

    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read(self::PATH,[]);
    }

    public function create(array $input): array
    {
        $record=[
            'id'=>'ses_'.bin2hex(random_bytes(6)),
            'event_id'=>trim((string)($input['event_id']??'')),
            'title'=>trim((string)($input['title']??'Untitled Session')),
            'track'=>trim((string)($input['track']??'Main')),
            'room'=>trim((string)($input['room']??'Main Hall')),
            'start_at'=>trim((string)($input['start_at']??'')),
            'end_at'=>trim((string)($input['end_at']??'')),
            'capacity'=>max(0,(int)($input['capacity']??0)),
            'speakers'=>array_values(array_filter(array_map(static fn($value): string=>trim((string)$value),(array)($input['speakers']??[])))),
            'status'=>(string)($input['status']??'published'),
            'created_at'=>date(DATE_ATOM),
        ];
        $this->validate($record);

        return $this->store->transaction(self::PATH,static function(array $rows) use ($record): array {
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function update(string $id,array $input): ?array
    {
        return $this->store->transaction(self::PATH,function(array $rows) use ($id,$input): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id) continue;
                foreach(['title','track','room','start_at','end_at','status'] as $field){
                    if(array_key_exists($field,$input)) $row[$field]=trim((string)$input[$field]);
                }
                if(array_key_exists('capacity',$input)) $row['capacity']=max(0,(int)$input['capacity']);
                if(isset($input['speakers'])&&is_array($input['speakers'])){
                    $row['speakers']=array_values(array_filter(array_map(static fn($value): string=>trim((string)$value),$input['speakers'])));
                }
                $this->validate($row);
                $row['updated_at']=date(DATE_ATOM);
                $updated=$row;
                break;
            }
            unset($row);
            return ['data'=>$rows,'result'=>$updated];
        },[]);
    }

    private function validate(array $record): void
    {
        if(trim((string)($record['event_id']??''))==='') throw new \InvalidArgumentException('Event is required.');
        if(trim((string)($record['title']??''))==='') throw new \InvalidArgumentException('Session title is required.');
        if(!in_array((string)($record['status']??''),self::STATUSES,true)) throw new \InvalidArgumentException('Invalid session status.');
        if(($record['start_at']??'')!==''&&($record['end_at']??'')!==''&&$record['end_at']<=$record['start_at']){
            throw new \InvalidArgumentException('Session end time must be after start time.');
        }
    }
}
