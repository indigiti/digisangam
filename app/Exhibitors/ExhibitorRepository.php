<?php
declare(strict_types=1);

namespace DigiSangam\Exhibitors;

use DigiSangam\Core\Storage\JsonFileStore;

final class ExhibitorRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('exhibitors/index.json', []);
    }

    public function create(array $input): array
    {
        $record=[
            'id'=>'exh_'.bin2hex(random_bytes(6)),
            'event_id'=>trim((string)($input['event_id'] ?? '')),
            'name'=>trim((string)($input['name']??'New Exhibitor')),
            'type'=>(string)($input['type']??'Exhibitor'),
            'booth'=>(string)($input['booth']??''),
            'contact_name'=>(string)($input['contact_name']??''),
            'contact_email'=>(string)($input['contact_email']??''),
            'staff_quota'=>max(0,(int)($input['staff_quota']??0)),
            'lead_quota'=>max(0,(int)($input['lead_quota']??0)),
            'status'=>(string)($input['status']??'active'),
            'created_at'=>date(DATE_ATOM),
        ];
        if($record['event_id']==='') throw new \InvalidArgumentException('Event is required.');
        if($record['name']==='') throw new \InvalidArgumentException('Exhibitor name is required.');
        if($record['contact_email']!=='' && !filter_var($record['contact_email'],FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Contact email is invalid.');
        return $this->store->transaction('exhibitors/index.json',static function(array $rows) use ($record): array {
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function update(string $id,array $input): ?array
    {
        return $this->store->transaction('exhibitors/index.json',static function(array $rows) use ($id,$input): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id) continue;
                foreach(['name','type','booth','contact_name','contact_email','status'] as $field){
                    if(array_key_exists($field,$input)) $row[$field]=trim((string)$input[$field]);
                }
                foreach(['staff_quota','lead_quota'] as $field){
                    if(array_key_exists($field,$input)) $row[$field]=max(0,(int)$input[$field]);
                }
                if(trim((string)($row['name']??''))==='') throw new \InvalidArgumentException('Exhibitor name is required.');
                $email=trim((string)($row['contact_email']??''));
                if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Contact email is invalid.');
                $row['updated_at']=date(DATE_ATOM); $updated=$row; break;
            }
            unset($row);
            return ['data'=>$rows,'result'=>$updated];
        },[]);
    }

}
