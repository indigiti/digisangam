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
        $rows=$this->all();array_unshift($rows,$record);$this->store->write('reports/definitions.json',$rows);return $record;
    }

    public function update(string $id,array $input): ?array
    {
        $rows=$this->all();$updated=null;
        foreach($rows as &$row){
            if(($row['id']??'')!==$id)continue;
            if(isset($input['name']))$row['name']=trim((string)$input['name']);
            if(isset($input['columns']))$row['columns']=array_values(array_filter(array_unique(array_map('strval',(array)$input['columns']))));
            if(isset($input['filters']))$row['filters']=(array)$input['filters'];
            $row['updated_at']=date(DATE_ATOM);$updated=$row;break;
        }
        unset($row);if($updated)$this->store->write('reports/definitions.json',$rows);return $updated;
    }

    public function delete(string $id): bool
    {
        $rows=$this->all();$next=array_values(array_filter($rows,fn($x)=>($x['id']??'')!==$id));
        if(count($rows)===count($next))return false;$this->store->write('reports/definitions.json',$next);return true;
    }
}
