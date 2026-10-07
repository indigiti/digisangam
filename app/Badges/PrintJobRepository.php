<?php
declare(strict_types=1);

namespace DigiSangam\Badges;

use DigiSangam\Core\Storage\JsonFileStore;

final class PrintJobRepository
{
    private const PATH='badges/print-queue.json';
    public function __construct(private readonly JsonFileStore $store) {}
    public function all(): array{return $this->store->read(self::PATH,[]);}

    public function create(array $input): array
    {
        $record=[
            'id'=>'print_'.bin2hex(random_bytes(6)),
            'event_id'=>trim((string)($input['event_id']??'')),
            'attendee_id'=>(string)($input['attendee_id']??''),
            'template_id'=>trim((string)($input['template_id']??'')),
            'copies'=>max(1,min(10,(int)($input['copies']??1))),
            'status'=>'queued','created_at'=>date(DATE_ATOM),
        ];
        if($record['event_id']==='')throw new \InvalidArgumentException('Event is required.');
        if($record['attendee_id']==='')throw new \InvalidArgumentException('Attendee is required.');
        if($record['template_id']==='')throw new \InvalidArgumentException('Badge template is required.');
        return $this->store->transaction(self::PATH,static function(array $rows) use ($record): array {
            array_unshift($rows,$record);return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function update(string $id,array $input): ?array
    {
        return $this->store->transaction(self::PATH,static function(array $rows) use ($id,$input): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id)continue;
                foreach(['status','copies','provider','provider_job_id','last_error'] as $field)if(array_key_exists($field,$input))$row[$field]=$input[$field];
                $row['updated_at']=date(DATE_ATOM);$updated=$row;break;
            }
            unset($row);return ['data'=>$rows,'result'=>$updated];
        },[]);
    }
}
