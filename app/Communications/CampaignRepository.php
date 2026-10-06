<?php
declare(strict_types=1);

namespace DigiSangam\Communications;

use DigiSangam\Core\Storage\JsonFileStore;

final class CampaignRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('communications/campaigns.json', []);
    }

    public function create(array $input): array
    {
        $record=[
            'id'=>'cmp_'.bin2hex(random_bytes(6)),
            'event_id'=>trim((string)($input['event_id'] ?? '')),
            'name'=>trim((string)($input['name']??'Untitled Campaign')),
            'channel'=>(string)($input['channel']??'email'),
            'template'=>(string)($input['template']??'custom_campaign'),
            'subject'=>trim((string)($input['subject']??'')),
            'content'=>(string)($input['content']??''),
            'segment'=>(array)($input['segment']??['status'=>'Confirmed']),
            'schedule_at'=>(string)($input['schedule_at']??''),
            'status'=>(string)($input['status']??'draft'),
            'sent_count'=>0,'failed_count'=>0,
            'created_at'=>date(DATE_ATOM),
        ];
        if(($record['event_id']??'')==='') throw new \InvalidArgumentException('Event is required.');
        if(($record['event_id']??'')==='') throw new \InvalidArgumentException('Event is required.');
        $rows=$this->all(); array_unshift($rows,$record);
        $this->store->write('communications/campaigns.json',$rows);
        return $record;
    }

    public function update(string $id,array $input): ?array
    {
        $rows=$this->all(); $updated=null;
        foreach($rows as &$row){
            if(($row['id']??'')!==$id) continue;
            foreach(['name','channel','template','subject','content','schedule_at','status'] as $field){
                if(array_key_exists($field,$input)) $row[$field]=is_string($input[$field])?trim($input[$field]):$input[$field];
            }
            if(isset($input['segment'])&&is_array($input['segment'])) $row['segment']=$input['segment'];
            foreach(['sent_count','failed_count'] as $field) if(array_key_exists($field,$input)) $row[$field]=max(0,(int)$input[$field]);
            $row['updated_at']=date(DATE_ATOM); $updated=$row; break;
        }
        unset($row);
        if($updated!==null) $this->store->write('communications/campaigns.json',$rows);
        return $updated;
    }
}
