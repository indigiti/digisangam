<?php
declare(strict_types=1);

namespace DigiSangam\Credentials;

use DigiSangam\Core\Storage\JsonFileStore;

final class CredentialBindingRepository
{
    private const PATH='credentials/bindings.json';

    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read(self::PATH,[]);
    }

    public function bind(string $eventId,string $attendeeId,string $type,string $uid): array
    {
        $eventId=trim($eventId);
        $attendeeId=trim($attendeeId);
        $type=strtolower(trim($type));
        $uid=strtoupper(trim($uid));
        if($eventId===''||$attendeeId===''||!in_array($type,['nfc','rfid'],true)||$uid===''){
            throw new \InvalidArgumentException('Event, attendee and valid NFC/RFID UID are required.');
        }

        return $this->store->transaction(self::PATH,static function(array $rows) use ($eventId,$attendeeId,$type,$uid): array {
            foreach($rows as $row){
                if(($row['event_id']??'')===$eventId&&($row['type']??'')===$type&&($row['uid']??'')===$uid&&empty($row['revoked_at'])){
                    throw new \InvalidArgumentException('Credential UID is already bound.');
                }
            }
            $record=[
                'id'=>'bnd_'.bin2hex(random_bytes(6)),
                'event_id'=>$eventId,
                'attendee_id'=>$attendeeId,
                'type'=>$type,
                'uid'=>$uid,
                'created_at'=>date(DATE_ATOM),
            ];
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function resolve(string $type,string $uid): ?array
    {
        $type=strtolower(trim($type));
        $uid=strtoupper(trim($uid));
        foreach($this->all() as $row){
            if(($row['type']??'')===$type&&($row['uid']??'')===$uid&&empty($row['revoked_at'])) return $row;
        }
        return null;
    }

    public function revoke(string $id): ?array
    {
        return $this->store->transaction(self::PATH,static function(array $rows) use ($id): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id) continue;
                if(empty($row['revoked_at'])) $row['revoked_at']=date(DATE_ATOM);
                $updated=$row;
                break;
            }
            unset($row);
            return ['data'=>$rows,'result'=>$updated];
        },[]);
    }
}
