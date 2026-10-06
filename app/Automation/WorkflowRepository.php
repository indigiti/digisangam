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
            'event_id'=>(string)($input['event_id']??'evt_001'),
            'name'=>trim((string)($input['name']??'New Workflow')),
            'trigger'=>(string)($input['trigger']??'person.registered'),
            'conditions'=>(array)($input['conditions']??[]),
            'actions'=>(array)($input['actions']??[]),
            'enabled'=>(bool)($input['enabled']??false),
            'runs'=>0,'last_run_at'=>null,
            'created_at'=>date(DATE_ATOM),
        ];
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

    private static function defaults(): array
    {
        return [
            [
                'id'=>'flow_welcome','event_id'=>'evt_001','name'=>'Welcome confirmed attendees',
                'trigger'=>'attendee.confirmed','conditions'=>[],
                'actions'=>[['type'=>'email','template'=>'registration_confirmation'],['type'=>'wait','minutes'=>5],['type'=>'whatsapp','template'=>'registration_confirmation']],
                'enabled'=>true,'runs'=>0,'last_run_at'=>null,
            ],
            [
                'id'=>'flow_checkin','event_id'=>'evt_001','name'=>'Post check-in follow-up',
                'trigger'=>'attendee.checked_in','conditions'=>[],
                'actions'=>[['type'=>'wait','minutes'=>15],['type'=>'email','template'=>'post_checkin']],
                'enabled'=>false,'runs'=>0,'last_run_at'=>null,
            ],
        ];
    }
}
