<?php
declare(strict_types=1);

namespace DigiSangam\Communications;

use DigiSangam\Core\Storage\JsonFileStore;

final class CampaignRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('communications/campaigns.json',[]);
    }

    public function create(array $input): array
    {
        $eventId=trim((string)($input['event_id']??''));
        $name=trim((string)($input['name']??''));
        $channel=(string)($input['channel']??'email');
        $timezone=trim((string)($input['timezone']??'UTC'))?:'UTC';
        if($eventId==='') throw new \InvalidArgumentException('Event is required.');
        if($name==='') throw new \InvalidArgumentException('Campaign name is required.');
        if(!in_array($channel,['email','whatsapp'],true)) throw new \InvalidArgumentException('Unsupported campaign channel.');
        try{new \DateTimeZone($timezone);}catch(\Throwable){throw new \InvalidArgumentException('Invalid campaign timezone.');}

        $record=[
            'id'=>'cmp_'.bin2hex(random_bytes(6)),
            'event_id'=>$eventId,
            'name'=>$name,
            'channel'=>$channel,
            'template'=>(string)($input['template']??'custom_campaign'),
            'subject'=>trim((string)($input['subject']??'')),
            'content'=>trim((string)($input['content']??'')),
            'segment'=>(array)($input['segment']??['status'=>'Confirmed']),
            'schedule_at'=>trim((string)($input['schedule_at']??'')),
            'timezone'=>$timezone,
            'status'=>'draft',
            'queued_count'=>0,
            'sent_count'=>0,
            'simulated_count'=>0,
            'failed_count'=>0,
            'skipped_count'=>0,
            'created_at'=>date(DATE_ATOM),
        ];
        $rows=$this->all();
        array_unshift($rows,$record);
        $this->store->write('communications/campaigns.json',$rows);
        return $record;
    }

    public function update(string $id,array $input): ?array
    {
        $rows=$this->all();$updated=null;
        foreach($rows as &$row){
            if(($row['id']??'')!==$id) continue;
            foreach(['name','channel','template','subject','content','schedule_at','timezone','status'] as $field){
                if(array_key_exists($field,$input)) $row[$field]=is_string($input[$field])?trim((string)$input[$field]):$input[$field];
            }
            if(!in_array((string)($row['channel']??''),['email','whatsapp'],true)) throw new \InvalidArgumentException('Unsupported campaign channel.');
            if(!in_array((string)($row['status']??''),['draft','queued','scheduled','sent','simulated','failed','cancelled'],true)) throw new \InvalidArgumentException('Invalid campaign status.');
            try{new \DateTimeZone((string)($row['timezone']??'UTC'));}catch(\Throwable){throw new \InvalidArgumentException('Invalid campaign timezone.');}
            if(isset($input['segment'])&&is_array($input['segment'])) $row['segment']=$input['segment'];
            foreach(['queued_count','sent_count','simulated_count','failed_count','skipped_count'] as $field) if(array_key_exists($field,$input)) $row[$field]=max(0,(int)$input[$field]);
            $row['updated_at']=date(DATE_ATOM);
            $updated=$row;
            break;
        }
        unset($row);
        if($updated!==null) $this->store->write('communications/campaigns.json',$rows);
        return $updated;
    }
}
