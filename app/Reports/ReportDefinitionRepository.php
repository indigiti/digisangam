<?php
declare(strict_types=1);

namespace DigiSangam\Reports;

use DigiSangam\Core\Storage\JsonFileStore;

final class ReportDefinitionRepository
{
    private const DATASETS=['attendees','orders','checkins','leads','accreditation'];

    public function __construct(private readonly JsonFileStore $store) {}
    public function all(): array { return $this->store->read('reports/definitions.json',[]); }
    public function find(string $id): ?array { foreach($this->all() as $row)if(($row['id']??'')===$id)return $row;return null; }

    public function create(array $input): array
    {
        $eventId=trim((string)($input['event_id']??''));$name=trim((string)($input['name']??''));$dataset=(string)($input['dataset']??'attendees');
        if($eventId===''||$name==='')throw new \InvalidArgumentException('Event and report name are required.');
        if(!in_array($dataset,self::DATASETS,true))throw new \InvalidArgumentException('Unsupported report dataset.');
        $columns=array_values(array_filter(array_unique(array_map('strval',(array)($input['columns']??[])))));
        $filters=(array)($input['filters']??[]);
        $record=['id'=>'rpt_'.bin2hex(random_bytes(6)),'event_id'=>$eventId,'name'=>$name,'dataset'=>$dataset,'columns'=>$columns,'filters'=>$filters,'created_at'=>date(DATE_ATOM)];
        return $this->store->transaction('reports/definitions.json',static function(array $rows) use ($record): array {
            array_unshift($rows,$record);return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function update(string $id,array $input): ?array
    {
        return $this->store->transaction('reports/definitions.json',static function(array $rows) use ($id,$input): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id)continue;
                if(isset($input['name']))$row['name']=trim((string)$input['name']);
                if(isset($input['columns']))$row['columns']=array_values(array_filter(array_unique(array_map('strval',(array)$input['columns']))));
                if(isset($input['filters']))$row['filters']=(array)$input['filters'];
                if(trim((string)($row['name']??''))==='') throw new \InvalidArgumentException('Report name is required.');
                $row['updated_at']=date(DATE_ATOM);$updated=$row;break;
            }
            unset($row);return ['data'=>$rows,'result'=>$updated];
        },[]);
    }

    public function delete(string $id): bool
    {
        return $this->store->transaction('reports/definitions.json',static function(array $rows) use ($id): array {
            $next=array_values(array_filter($rows,static fn(array $x): bool => ($x['id']??'')!==$id));
            return ['data'=>$next,'result'=>count($rows)!==count($next)];
        },[]);
    }
}
