<?php
declare(strict_types=1);

namespace DigiSangam\Accreditation;

use DigiSangam\Core\Storage\JsonFileStore;

final class AccreditationRepository
{
    private const STATUSES=['submitted','reviewing','approved','rejected','activated','blocked','expired'];

    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array { return $this->store->read('accreditation/records.json',[]); }

    public function create(array $input): array
    {
        $eventId=trim((string)($input['event_id']??''));
        $attendeeId=trim((string)($input['attendee_id']??''));
        if($eventId===''||$attendeeId==='') throw new \InvalidArgumentException('Event and attendee are required.');
        foreach($this->all() as $row){
            if(($row['event_id']??'')===$eventId&&($row['attendee_id']??'')===$attendeeId&&($row['status']??'')!=='rejected') {
                throw new \InvalidArgumentException('Attendee already has an active accreditation record.');
            }
        }
        $record=[
            'id'=>'acr_'.bin2hex(random_bytes(6)),
            'event_id'=>$eventId,
            'attendee_id'=>$attendeeId,
            'type'=>trim((string)($input['type']??'General')),
            'quota_pool'=>trim((string)($input['quota_pool']??'')),
            'documents'=>array_values((array)($input['documents']??[])),
            'notes'=>trim((string)($input['notes']??'')),
            'status'=>'submitted',
            'valid_from'=>(string)($input['valid_from']??''),
            'valid_until'=>(string)($input['valid_until']??''),
            'created_at'=>date(DATE_ATOM),
        ];
        $rows=$this->all();array_unshift($rows,$record);$this->store->write('accreditation/records.json',$rows);
        return $record;
    }

    public function update(string $id,array $input,string $actor=''): ?array
    {
        $rows=$this->all();$updated=null;
        foreach($rows as &$row){
            if(($row['id']??'')!==$id) continue;
            foreach(['type','quota_pool','notes','valid_from','valid_until'] as $field){
                if(array_key_exists($field,$input)) $row[$field]=trim((string)$input[$field]);
            }
            if(isset($input['documents'])&&is_array($input['documents'])) $row['documents']=array_values($input['documents']);
            if(isset($input['status'])){
                $status=(string)$input['status'];
                if(!in_array($status,self::STATUSES,true)) throw new \InvalidArgumentException('Invalid accreditation status.');
                $row['status']=$status;
                $row['status_actor']=$actor;
                $row['status_at']=date(DATE_ATOM);
                if($status==='activated') $row['activated_at']=date(DATE_ATOM);
                if($status==='blocked') $row['blocked_at']=date(DATE_ATOM);
            }
            $row['updated_at']=date(DATE_ATOM);$updated=$row;break;
        }
        unset($row);
        if($updated!==null)$this->store->write('accreditation/records.json',$rows);
        return $updated;
    }
}
