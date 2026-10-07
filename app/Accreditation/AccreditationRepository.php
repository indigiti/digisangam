<?php
declare(strict_types=1);

namespace DigiSangam\Accreditation;

use DigiSangam\Core\Storage\JsonFileStore;

final class AccreditationRepository
{
    private const PATH='accreditation/records.json';
    private const STATUSES=['submitted','reviewing','approved','rejected','activated','blocked','expired'];
    private const TRANSITIONS=[
        'submitted'=>['reviewing','approved','rejected','blocked'],
        'reviewing'=>['approved','rejected','blocked'],
        'approved'=>['activated','blocked','expired'],
        'activated'=>['blocked','expired'],
        'blocked'=>[],
        'rejected'=>[],
        'expired'=>[],
    ];

    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read(self::PATH,[]);
    }

    public function create(array $input): array
    {
        $eventId=trim((string)($input['event_id']??''));
        $attendeeId=trim((string)($input['attendee_id']??''));
        if($eventId===''||$attendeeId==='') throw new \InvalidArgumentException('Event and attendee are required.');

        $validFrom=trim((string)($input['valid_from']??''));
        $validUntil=trim((string)($input['valid_until']??''));
        if($validFrom!==''&&$validUntil!==''&&$validUntil<$validFrom) throw new \InvalidArgumentException('Accreditation valid-until date cannot be before valid-from date.');

        $record=[
            'id'=>'acr_'.bin2hex(random_bytes(6)),
            'event_id'=>$eventId,
            'attendee_id'=>$attendeeId,
            'type'=>trim((string)($input['type']??'General')),
            'quota_pool'=>trim((string)($input['quota_pool']??'')),
            'documents'=>array_values(array_unique(array_map('strval',(array)($input['documents']??[])))),
            'notes'=>trim((string)($input['notes']??'')),
            'status'=>'submitted',
            'valid_from'=>$validFrom,
            'valid_until'=>$validUntil,
            'created_at'=>date(DATE_ATOM),
        ];

        return $this->store->transaction(self::PATH,static function(array $rows) use ($record,$eventId,$attendeeId): array {
            foreach($rows as $row){
                if(($row['event_id']??'')===$eventId&&($row['attendee_id']??'')===$attendeeId&&!in_array(($row['status']??''),['rejected','expired'],true)){
                    throw new \InvalidArgumentException('Attendee already has an active accreditation record.');
                }
            }
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function update(string $id,array $input,string $actor=''): ?array
    {
        return $this->store->transaction(self::PATH,static function(array $rows) use ($id,$input,$actor): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id) continue;

                foreach(['type','quota_pool','notes','valid_from','valid_until'] as $field){
                    if(array_key_exists($field,$input)) $row[$field]=trim((string)$input[$field]);
                }
                if(!empty($row['valid_from'])&&!empty($row['valid_until'])&&$row['valid_until']<$row['valid_from']){
                    throw new \InvalidArgumentException('Accreditation valid-until date cannot be before valid-from date.');
                }
                if(isset($input['documents'])&&is_array($input['documents'])){
                    $row['documents']=array_values(array_unique(array_map('strval',$input['documents'])));
                }
                if(isset($input['status'])){
                    $status=(string)$input['status'];
                    if(!in_array($status,self::STATUSES,true)) throw new \InvalidArgumentException('Invalid accreditation status.');
                    $current=(string)($row['status']??'submitted');
                    if($status!==$current&&!in_array($status,self::TRANSITIONS[$current]??[],true)){
                        throw new \InvalidArgumentException('Invalid accreditation status transition from '.$current.' to '.$status.'.');
                    }
                    if($status!==$current){
                        $row['status']=$status;
                        $row['status_actor']=$actor;
                        $row['status_at']=date(DATE_ATOM);
                        if($status==='activated') $row['activated_at']=date(DATE_ATOM);
                        if($status==='blocked') $row['blocked_at']=date(DATE_ATOM);
                    }
                }
                $row['updated_at']=date(DATE_ATOM);
                $updated=$row;
                break;
            }
            unset($row);
            return ['data'=>$rows,'result'=>$updated];
        },[]);
    }
}
