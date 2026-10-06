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
        if(($record['event_id']??'')==='') throw new \InvalidArgumentException('Event is required.');
        if(($record['event_id']??'')==='') throw new \InvalidArgumentException('Event is required.');
        $rows=$this->all(); array_unshift($rows,$record);
        $this->store->write('exhibitors/index.json',$rows);
        return $record;
    }

    public function update(string $id,array $input): ?array
    {
        $rows=$this->all(); $updated=null;
        foreach($rows as &$row){
            if(($row['id']??'')!==$id) continue;
            foreach(['name','type','booth','contact_name','contact_email','staff_quota','lead_quota','status'] as $field) if(array_key_exists($field,$input)) $row[$field]=$input[$field];
            $row['updated_at']=date(DATE_ATOM); $updated=$row; break;
        }
        unset($row);
        if($updated!==null) $this->store->write('exhibitors/index.json',$rows);
        return $updated;
    }

    private static function defaults(): array
    {
        return [
            ['id'=>'exh_001','event_id'=>'evt_001','name'=>'Nova Systems','type'=>'Sponsor','booth'=>'A12','contact_name'=>'Riya Shah','contact_email'=>'riya@example.test','staff_quota'=>12,'lead_quota'=>500,'status'=>'active'],
            ['id'=>'exh_002','event_id'=>'evt_001','name'=>'CloudForge','type'=>'Exhibitor','booth'=>'B07','contact_name'=>'Karan Mehta','contact_email'=>'karan@example.test','staff_quota'=>6,'lead_quota'=>250,'status'=>'active'],
        ];
    }
}
