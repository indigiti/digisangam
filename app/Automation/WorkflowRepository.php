<?php
declare(strict_types=1);

namespace DigiSangam\Automation;

use DigiSangam\Core\Storage\JsonFileStore;

final class WorkflowRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('automation/workflows.json', []);
    }

    public function create(array $input): array
    {
        $record=[
            'id'=>'flow_'.bin2hex(random_bytes(6)),
            'event_id'=>trim((string)($input['event_id'] ?? '')),
            'name'=>trim((string)($input['name']??'New Workflow')),
            'trigger'=>(string)($input['trigger']??'person.registered'),
            'conditions'=>(array)($input['conditions']??[]),
            'actions'=>(array)($input['actions']??[]),
            'enabled'=>(bool)($input['enabled']??false),
            'runs'=>0,'last_run_at'=>null,
            'created_at'=>date(DATE_ATOM),
        ];
        if($record['event_id']==='') throw new \InvalidArgumentException('Event is required.');
        if($record['name']==='') throw new \InvalidArgumentException('Workflow name is required.');
        if($record['trigger']==='') throw new \InvalidArgumentException('Workflow trigger is required.');
        $rows=$this->all(); array_unshift($rows,$record);
        $this->store->write('automation/workflows.json',$rows);
        return $record;
    }

    public function update(string $id,array $input): ?array
    {
        $rows=$this->all(); $updated=null;
        foreach($rows as &$row){
            if(($row['id']??'')!==$id) continue;
            foreach(['name','trigger','enabled'] as $field) if(array_key_exists($field,$input)) $row[$field]=$input[$field];
            foreach(['conditions','actions'] as $field) if(isset($input[$field])&&is_array($input[$field])) $row[$field]=$input[$field];
            $row['updated_at']=date(DATE_ATOM); $updated=$row; break;
        }
        unset($row);
        if($updated!==null) $this->store->write('automation/workflows.json',$rows);
        return $updated;
    }

}
