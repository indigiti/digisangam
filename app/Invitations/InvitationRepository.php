<?php
declare(strict_types=1);

namespace DigiSangam\Invitations;

use DigiSangam\Core\Storage\JsonFileStore;

final class InvitationRepository
{
    private const PATH='invitations/index.json';

    public function __construct(private readonly JsonFileStore $store) {}
    public function all(): array { return $this->store->read(self::PATH,[]); }
    public function find(string $id): ?array { foreach($this->all() as $row)if(($row['id']??'')===$id)return $row;return null; }

    public function findByToken(string $token): ?array { foreach($this->all() as $row)if(($row['token']??'')===$token)return $row;return null; }

    public function create(array $input): array
    {
        $eventId=trim((string)($input['event_id']??''));
        $email=strtolower(trim((string)($input['email']??'')));
        if($eventId==='') throw new \InvalidArgumentException('Event is required.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Valid email is required.');

        return $this->store->transaction(self::PATH,static function(array $rows) use ($eventId,$email,$input): array {
            foreach($rows as $row){
                if(($row['event_id']??'')===$eventId&&strtolower((string)($row['email']??''))===$email&&($row['status']??'')!=='revoked'){
                    throw new \InvalidArgumentException('An active invitation already exists for this email.');
                }
            }
            $record=[
                'id'=>'inv_'.bin2hex(random_bytes(6)),'event_id'=>$eventId,'email'=>$email,
                'category'=>trim((string)($input['category']??'General')),'status'=>'pending',
                'token'=>bin2hex(random_bytes(20)),'created_at'=>date(DATE_ATOM),
            ];
            array_unshift($rows,$record);return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function revoke(string $id): ?array
    {
        return $this->store->transaction(self::PATH,static function(array $rows) use ($id): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id)continue;
                if(($row['status']??'')!=='revoked'){$row['status']='revoked';$row['revoked_at']=date(DATE_ATOM);}
                $updated=$row;break;
            }
            unset($row);return ['data'=>$rows,'result'=>$updated];
        },[]);
    }

    public function markSent(string $id,string $messageId): ?array
    {
        return $this->store->transaction(self::PATH,static function(array $rows) use ($id,$messageId): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id)continue;
                $row['notification_message_id']=$messageId;
                $row['queued_at']=date(DATE_ATOM);
                $updated=$row;break;
            }
            unset($row);return ['data'=>$rows,'result'=>$updated];
        },[]);
    }

    public function markAccepted(string $token): ?array
    {
        return $this->store->transaction(self::PATH,static function(array $rows) use ($token): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['token']??'')!==$token)continue;
                if(($row['status']??'pending')==='pending'){$row['status']='accepted';$row['accepted_at']=date(DATE_ATOM);}
                $updated=$row;break;
            }
            unset($row);return ['data'=>$rows,'result'=>$updated];
        },[]);
    }
}
