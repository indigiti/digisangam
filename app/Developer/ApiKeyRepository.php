<?php
declare(strict_types=1);

namespace DigiSangam\Developer;

use DigiSangam\Core\Storage\JsonFileStore;

final class ApiKeyRepository
{
    private const PATH='developer/api-keys.json';

    public function __construct(private readonly JsonFileStore $store) {}
    public function all(): array { return $this->store->read(self::PATH,[]); }

    public function create(string $name,array $scopes,string $eventId=''): array
    {
        $name=trim($name);
        $eventId=trim($eventId);
        if($name==='') throw new \InvalidArgumentException('API key name is required.');
        $allowed=['events.read','attendees.read','sessions.read','analytics.read'];
        $scopes=array_values(array_intersect($allowed,array_unique(array_map('strval',$scopes))));
        if($scopes===[]) throw new \InvalidArgumentException('At least one API scope is required.');

        $plain='dsk_'.bin2hex(random_bytes(24));
        $record=[
            'id'=>'key_'.bin2hex(random_bytes(6)),
            'name'=>$name,
            'event_id'=>$eventId,
            'scopes'=>$scopes,
            'hash'=>hash('sha256',$plain),
            'prefix'=>substr($plain,0,12),
            'active'=>true,
            'created_at'=>date(DATE_ATOM),
        ];
        $this->store->transaction(self::PATH,static function(array $rows) use ($record): array {
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>null];
        },[]);

        $public=$record;
        unset($public['hash']);
        $public['key']=$plain;
        return $public;
    }

    public function revoke(string $id): ?array
    {
        $updated=$this->store->transaction(self::PATH,static function(array $rows) use ($id): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id) continue;
                $row['active']=false;
                $row['revoked_at']=$row['revoked_at']??date(DATE_ATOM);
                $updated=$row;
                break;
            }
            unset($row);
            return ['data'=>$rows,'result'=>$updated];
        },[]);
        return is_array($updated)?$this->view($updated):null;
    }

    public function authenticate(string $plain,string $scope,string $eventId=''): ?array
    {
        $hash=hash('sha256',$plain);
        foreach($this->all() as $row){
            if(empty($row['active'])||!hash_equals((string)($row['hash']??''),$hash)) continue;
            if(!in_array($scope,(array)($row['scopes']??[]),true)) return null;
            if(($row['event_id']??'')!==''&&$eventId!==''&&($row['event_id']??'')!==$eventId) return null;
            return $this->view($row);
        }
        return null;
    }

    public function publicList(): array { return array_map(fn(array $row): array=>$this->view($row),$this->all()); }

    private function view(array $row): array
    {
        unset($row['hash']);
        return $row;
    }
}
