<?php
declare(strict_types=1);

namespace DigiSangam\Tickets;

use DigiSangam\Core\Storage\JsonFileStore;

final class TicketRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('tickets/index.json', self::demo());
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $row) if (($row['id'] ?? '') === $id) return $row;
        return null;
    }

    public function create(array $input): array
    {
        $rows = $this->all();
        $record = [
            'id'=>'tic_'.bin2hex(random_bytes(5)),
            'name'=>trim((string)($input['name'] ?? 'Ticket')),
            'price'=>max(0,(int)($input['price'] ?? 0)),
            'quantity'=>max(1,(int)($input['quantity'] ?? 1)),
            'sold'=>0,
            'status'=>(string)($input['status'] ?? 'Active'),
            'event_id'=>(string)($input['event_id'] ?? 'evt_001'),
            'sale_start'=>(string)($input['sale_start'] ?? ''),
            'sale_end'=>(string)($input['sale_end'] ?? ''),
            'created_at'=>date(DATE_ATOM),
        ];
        array_unshift($rows,$record);
        $this->store->write('tickets/index.json',$rows);
        return $record;
    }

    public function update(string $id,array $input): ?array
    {
        $rows=$this->all(); $updated=null;
        foreach($rows as &$row){
            if(($row['id']??'')!==$id) continue;
            foreach(['name','status','sale_start','sale_end'] as $f) if(array_key_exists($f,$input)) $row[$f]=(string)$input[$f];
            foreach(['price','quantity'] as $f) if(array_key_exists($f,$input)) $row[$f]=max($f==='quantity'?1:0,(int)$input[$f]);
            if((int)$row['sold']>(int)$row['quantity']) throw new \InvalidArgumentException('Quantity cannot be lower than sold count.');
            $row['updated_at']=date(DATE_ATOM); $updated=$row; break;
        }
        unset($row);
        if($updated!==null) $this->store->write('tickets/index.json',$rows);
        return $updated;
    }

    private static function demo(): array
    {
        return [
            ['id'=>'tic_1','name'=>'Early Bird','price'=>2499,'quantity'=>500,'sold'=>432,'status'=>'Active','event_id'=>'evt_001'],
            ['id'=>'tic_2','name'=>'General','price'=>3999,'quantity'=>1000,'sold'=>642,'status'=>'Active','event_id'=>'evt_001'],
            ['id'=>'tic_3','name'=>'Student','price'=>1499,'quantity'=>300,'sold'=>210,'status'=>'Active','event_id'=>'evt_001'],
            ['id'=>'tic_4','name'=>'VIP','price'=>9999,'quantity'=>100,'sold'=>85,'status'=>'Active','event_id'=>'evt_001'],
            ['id'=>'tic_5','name'=>'Speaker','price'=>0,'quantity'=>200,'sold'=>120,'status'=>'Active','event_id'=>'evt_001'],
            ['id'=>'tic_6','name'=>'Sponsor Delegate','price'=>0,'quantity'=>500,'sold'=>320,'status'=>'Active','event_id'=>'evt_001'],
            ['id'=>'tic_7','name'=>'Media','price'=>0,'quantity'=>100,'sold'=>64,'status'=>'Active','event_id'=>'evt_001'],
        ];
    }
}
