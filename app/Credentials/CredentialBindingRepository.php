<?php
declare(strict_types=1);

namespace DigiSangam\Credentials;

use DigiSangam\Core\Storage\JsonFileStore;

final class CredentialBindingRepository
{
    public function __construct(private readonly JsonFileStore $store) {}
    public function all(): array { return $this->store->read('credentials/bindings.json',[]); }
    public function bind(string $eventId,string $attendeeId,string $type,string $uid): array {
        $type=strtolower(trim($type));$uid=strtoupper(trim($uid));
        if(!in_array($type,['nfc','rfid'],true)||$uid==='')throw new \InvalidArgumentException('Valid NFC/RFID type and UID are required.');
        $rows=$this->all();foreach($rows as $r)if(($r['event_id']??'')===$eventId&&($r['type']??'')===$type&&($r['uid']??'')===$uid&&empty($r['revoked_at']))throw new \InvalidArgumentException('Credential UID is already bound.');
        $record=['id'=>'bnd_'.bin2hex(random_bytes(6)),'event_id'=>$eventId,'attendee_id'=>$attendeeId,'type'=>$type,'uid'=>$uid,'created_at'=>date(DATE_ATOM)];
        array_unshift($rows,$record);$this->store->write('credentials/bindings.json',$rows);return $record;
    }
    public function resolve(string $type,string $uid): ?array { $type=strtolower($type);$uid=strtoupper(trim($uid));foreach($this->all() as $r)if(($r['type']??'')===$type&&($r['uid']??'')===$uid&&empty($r['revoked_at']))return $r;return null; }
    public function revoke(string $id): ?array { $rows=$this->all();$u=null;foreach($rows as &$r){if(($r['id']??'')!==$id)continue;$r['revoked_at']=date(DATE_ATOM);$u=$r;break;}unset($r);if($u)$this->store->write('credentials/bindings.json',$rows);return $u; }
}
