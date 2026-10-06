<?php
declare(strict_types=1);

namespace DigiSangam\Intelligence;

use DigiSangam\Core\Storage\JsonFileStore;

final class ActionProposalRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('intelligence/actions.json',[]);
    }

    public function create(array $input,string $createdBy=''): array
    {
        $type=(string)($input['type']??'operator_note');
        if(!in_array($type,['operator_note','campaign_draft','workflow_draft'],true)) {
            throw new \InvalidArgumentException('Unsupported intelligence action type.');
        }
        $record=[
            'id'=>'aip_'.bin2hex(random_bytes(6)),
            'event_id'=>(string)($input['event_id']??'evt_001'),
            'type'=>$type,
            'title'=>trim((string)($input['title']??'AI suggested action')),
            'rationale'=>trim((string)($input['rationale']??'')),
            'payload'=>(array)($input['payload']??[]),
            'status'=>'pending',
            'created_by'=>$createdBy,
            'created_at'=>date(DATE_ATOM),
        ];
        $rows=$this->all();array_unshift($rows,$record);
        $this->store->write('intelligence/actions.json',$rows);
        return $record;
    }

    public function decide(string $id,string $decision,string $userId=''): ?array
    {
        if(!in_array($decision,['approved','rejected'],true)) throw new \InvalidArgumentException('Invalid action decision.');
        $rows=$this->all();$updated=null;
        foreach($rows as &$row){
            if(($row['id']??'')!==$id) continue;
            if(($row['status']??'pending')!=='pending') throw new \RuntimeException('Action proposal has already been decided.');
            $row['status']=$decision;
            $row['decided_by']=$userId;
            $row['decided_at']=date(DATE_ATOM);
            $updated=$row;break;
        }
        unset($row);
        if($updated!==null)$this->store->write('intelligence/actions.json',$rows);
        return $updated;
    }

    public function attachResult(string $id,array $result): ?array
    {
        $rows=$this->all();$updated=null;
        foreach($rows as &$row){
            if(($row['id']??'')!==$id) continue;
            $row['result']=$result;
            $row['executed_at']=date(DATE_ATOM);
            $updated=$row;break;
        }
        unset($row);
        if($updated!==null)$this->store->write('intelligence/actions.json',$rows);
        return $updated;
    }
}
