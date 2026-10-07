<?php
declare(strict_types=1);

namespace DigiSangam\Invitations;

use DigiSangam\Core\Storage\JsonFileStore;

final class InvitationRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('invitations/index.json', []);
    }

    public function findByToken(string $token): ?array
    {
        foreach($this->all() as $row){
            if(($row['token']??'')===$token) return $row;
        }
        return null;
    }

    public function create(array $input): array
    {
        $rows=$this->all();
        $eventId=trim((string)($input['event_id']??''));
        $email=strtolower(trim((string)($input['email']??'')));
        if($eventId==='') throw new \InvalidArgumentException('Event is required.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Valid email is required.');
        foreach($rows as $row){
            if(($row['event_id']??'')===$eventId && strtolower((string)($row['email']??''))===$email && ($row['status']??'')!=='revoked'){
                throw new \InvalidArgumentException('An active invitation already exists for this email.');
            }
        }
        $record=[
            'id'=>'inv_'.bin2hex(random_bytes(6)),
            'event_id'=>$eventId,
            'email'=>$email,
            'category'=>trim((string)($input['category']??'General')),
            'status'=>'pending',
            'token'=>bin2hex(random_bytes(20)),
            'created_at'=>date(DATE_ATOM),
        ];
        array_unshift($rows,$record);
        $this->store->write('invitations/index.json',$rows);
        return $record;
    }

    public function revoke(string $id): ?array
    {
        $rows=$this->all();$updated=null;
        foreach($rows as &$row){
            if(($row['id']??'')!==$id) continue;
            if(($row['status']??'')==='revoked'){ $updated=$row; break; }
            $row['status']='revoked';
            $row['revoked_at']=date(DATE_ATOM);
            $updated=$row;
            break;
        }
        unset($row);
        if($updated!==null) $this->store->write('invitations/index.json',$rows);
        return $updated;
    }

    public function markAccepted(string $token): ?array
    {
        $rows=$this->all();$updated=null;
        foreach($rows as &$row){
            if(($row['token']??'')!==$token) continue;
            $row['status']='accepted';
            $row['accepted_at']=date(DATE_ATOM);
            $updated=$row;
            break;
        }
        unset($row);
        if($updated!==null) $this->store->write('invitations/index.json',$rows);
        return $updated;
    }
}
