<?php
declare(strict_types=1);

namespace DigiSangam\Attendees;

use DigiSangam\Core\Storage\JsonFileStore;

final class AttendeeRepository
{
    private const PATH='attendees/index.json';

    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array { return $this->store->read(self::PATH,[]); }

    public function find(string $id): ?array
    {
        foreach($this->all() as $row) if(($row['id']??'')===$id) return $row;
        return null;
    }

    public function findByEmailForEvent(string $email,string $eventId): ?array
    {
        $email=strtolower(trim($email));
        foreach($this->all() as $row){
            if(($row['event_id']??'')===$eventId && strtolower((string)($row['email']??''))===$email) return $row;
        }
        return null;
    }

    public function findByConfirmationToken(string $token): ?array
    {
        foreach($this->all() as $row){
            if(!empty($row['confirmation_token'])&&hash_equals((string)$row['confirmation_token'],$token)) return $row;
        }
        return null;
    }

    public function create(array $input): array
    {
        $name=trim((string)($input['name']??''));
        $email=strtolower(trim((string)($input['email']??'')));
        $eventId=trim((string)($input['event_id']??''));
        if($eventId==='') throw new \InvalidArgumentException('Event is required.');
        if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Name and a valid email are required.');

        return $this->store->transaction(self::PATH,function(array $rows) use ($input,$name,$email,$eventId): array {
            foreach($rows as $existing){
                if(($existing['event_id']??'')===$eventId&&strtolower((string)($existing['email']??''))===$email){
                    throw new \InvalidArgumentException('This email is already registered for the event.');
                }
            }
            $record=[
                'id'=>'TKT'.strtoupper(bin2hex(random_bytes(5))),
                'name'=>$name,'initials'=>$this->initials($name),
                'category'=>trim((string)($input['category']??'General')),
                'company'=>trim((string)($input['company']??'')),
                'status'=>ApprovalService::normalize((string)($input['status']??'Pending')),
                'email'=>$email,'phone'=>trim((string)($input['phone']??'')),
                'event_id'=>$eventId,'ticket_id'=>(string)($input['ticket_id']??''),
                'source'=>trim((string)($input['source']??'admin'))?:'admin',
                'answers'=>(array)($input['answers']??[]),
                'confirmation_token'=>(string)($input['confirmation_token']??bin2hex(random_bytes(24))),
                'created_at'=>date(DATE_ATOM),
            ];
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function update(string $id,array $input): ?array
    {
        return $this->store->transaction(self::PATH,function(array $rows) use ($id,$input): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id) continue;
                if(array_key_exists('email',$input)){
                    $email=strtolower(trim((string)$input['email']));
                    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Valid email is required.');
                    foreach($rows as $other){
                        if(($other['id']??'')!==$id&&($other['event_id']??'')===($row['event_id']??'')&&strtolower((string)($other['email']??''))===$email){
                            throw new \InvalidArgumentException('This email is already registered for the event.');
                        }
                    }
                    $row['email']=$email;
                }
                foreach(['name','category','company','phone','ticket_id','source','confirmation_token'] as $field){
                    if(array_key_exists($field,$input)) $row[$field]=trim((string)$input[$field]);
                }
                if(isset($input['status'])) $row['status']=ApprovalService::normalize((string)$input['status']);
                if(isset($input['name'])) $row['initials']=$this->initials((string)$row['name']);
                if(isset($input['answers'])&&is_array($input['answers'])) $row['answers']=$input['answers'];
                $row['updated_at']=date(DATE_ATOM);$updated=$row;break;
            }
            unset($row);
            return ['data'=>$rows,'result'=>$updated];
        },[]);
    }

    public function delete(string $id): bool
    {
        return $this->store->transaction(self::PATH,static function(array $rows) use ($id): array {
            $next=array_values(array_filter($rows,static fn(array $row): bool => ($row['id']??'')!==$id));
            return ['data'=>$next,'result'=>count($next)!==count($rows)];
        },[]);
    }

    public function import(array $rows): array
    {
        $created=0;$skipped=0;$errors=[];
        foreach($rows as $index=>$row){
            try{$this->create($row);$created++;}
            catch(\Throwable $e){$skipped++;$errors[]=['row'=>$index+2,'error'=>$e->getMessage()];}
        }
        return ['created'=>$created,'skipped'=>$skipped,'errors'=>$errors];
    }

    private function initials(string $name): string
    {
        $parts=preg_split('/\s+/',trim($name))?:[];
        return strtoupper(substr((string)($parts[0]??''),0,1).substr((string)($parts[count($parts)-1]??''),0,1));
    }
}
