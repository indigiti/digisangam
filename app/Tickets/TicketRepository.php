<?php
declare(strict_types=1);

namespace DigiSangam\Tickets;

use DigiSangam\Core\Storage\JsonFileStore;

final class TicketRepository
{
    private const PATH='tickets/index.json';

    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return array_map(static function(array $row): array {
            $row['sold']=(int)($row['sold']??0);
            $row['reserved']=(int)($row['reserved']??0);
            return $row;
        },$this->store->read(self::PATH,[]));
    }

    public function publicForEvent(string $eventId): array
    {
        $today=date('Y-m-d');
        return array_values(array_filter($this->all(),static function(array $row) use ($eventId,$today): bool {
            if(($row['event_id']??'')!==$eventId||($row['status']??'')!=='Active')return false;
            if(!empty($row['sale_start'])&&$row['sale_start']>$today)return false;
            if(!empty($row['sale_end'])&&$row['sale_end']<$today)return false;
            return ((int)$row['sold']+(int)$row['reserved'])<(int)($row['quantity']??0);
        }));
    }

    public function find(string $id): ?array
    {
        foreach($this->all() as $row)if(($row['id']??'')===$id)return $row;
        return null;
    }

    public function create(array $input): array
    {
        $record=[
            'id'=>'tic_'.bin2hex(random_bytes(5)),
            'name'=>trim((string)($input['name']??'Ticket')),
            'price'=>max(0,(int)($input['price']??0)),
            'quantity'=>max(1,(int)($input['quantity']??1)),
            'sold'=>0,'reserved'=>0,
            'status'=>(string)($input['status']??'Active'),
            'event_id'=>trim((string)($input['event_id']??'')),
            'sale_start'=>(string)($input['sale_start']??''),
            'sale_end'=>(string)($input['sale_end']??''),
            'created_at'=>date(DATE_ATOM),
        ];
        if($record['event_id']==='')throw new \InvalidArgumentException('Event is required.');
        if($record['name']==='')throw new \InvalidArgumentException('Ticket name is required.');
        if($record['sale_start']!==''&&$record['sale_end']!==''&&$record['sale_end']<$record['sale_start'])throw new \InvalidArgumentException('Sale end cannot be before sale start.');

        return $this->store->transaction(self::PATH,static function(array $rows) use ($record): array {
            array_unshift($rows,$record);return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function update(string $id,array $input): ?array
    {
        return $this->store->transaction(self::PATH,static function(array $rows) use ($id,$input): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id)continue;
                $row['sold']=(int)($row['sold']??0);$row['reserved']=(int)($row['reserved']??0);
                foreach(['name','status','sale_start','sale_end'] as $field)if(array_key_exists($field,$input))$row[$field]=(string)$input[$field];
                foreach(['price','quantity'] as $field)if(array_key_exists($field,$input))$row[$field]=max($field==='quantity'?1:0,(int)$input[$field]);
                if($row['sold']+$row['reserved']>(int)$row['quantity'])throw new \InvalidArgumentException('Quantity cannot be lower than sold plus reserved inventory.');
                $row['updated_at']=date(DATE_ATOM);$updated=$row;break;
            }
            unset($row);return ['data'=>$rows,'result'=>$updated];
        },[]);
    }

    public function reserveOne(string $ticketId,string $eventId): array
    {
        return $this->store->transaction(self::PATH,static function(array $rows) use ($ticketId,$eventId): array {
            $reserved=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$ticketId||($row['event_id']??'')!==$eventId)continue;
                if(($row['status']??'')!=='Active')throw new \RuntimeException('This ticket is not available.');
                $sold=(int)($row['sold']??0);$held=(int)($row['reserved']??0);$quantity=(int)($row['quantity']??0);
                if($sold+$held>=$quantity)throw new \RuntimeException('This ticket is sold out.');
                $row['reserved']=$held+1;$row['sold']=$sold;$row['updated_at']=date(DATE_ATOM);$reserved=$row;break;
            }
            unset($row);
            if($reserved===null)throw new \RuntimeException('Ticket not found for this event.');
            return ['data'=>$rows,'result'=>$reserved];
        },[]);
    }

    public function commitReservation(string $ticketId,string $eventId): array
    {
        return $this->store->transaction(self::PATH,static function(array $rows) use ($ticketId,$eventId): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$ticketId||($row['event_id']??'')!==$eventId)continue;
                $held=(int)($row['reserved']??0);
                if($held<1)throw new \RuntimeException('No ticket reservation is available to commit.');
                $row['reserved']=$held-1;$row['sold']=(int)($row['sold']??0)+1;$row['updated_at']=date(DATE_ATOM);$updated=$row;break;
            }
            unset($row);
            if($updated===null)throw new \RuntimeException('Ticket not found for this event.');
            return ['data'=>$rows,'result'=>$updated];
        },[]);
    }

    public function releaseReservation(string $ticketId,string $eventId): void
    {
        $this->store->transaction(self::PATH,static function(array $rows) use ($ticketId,$eventId): array {
            foreach($rows as &$row){
                if(($row['id']??'')!==$ticketId||($row['event_id']??'')!==$eventId)continue;
                $row['reserved']=max(0,(int)($row['reserved']??0)-1);$row['updated_at']=date(DATE_ATOM);break;
            }
            unset($row);return $rows;
        },[]);
    }

    public function releaseSold(string $ticketId,string $eventId): void
    {
        $this->store->transaction(self::PATH,static function(array $rows) use ($ticketId,$eventId): array {
            foreach($rows as &$row){
                if(($row['id']??'')!==$ticketId||($row['event_id']??'')!==$eventId)continue;
                $row['sold']=max(0,(int)($row['sold']??0)-1);$row['updated_at']=date(DATE_ATOM);break;
            }
            unset($row);return $rows;
        },[]);
    }

    // Backward-compatible rollback helper. Prefer explicit releaseReservation/releaseSold in new code.
    public function releaseOne(string $ticketId,string $eventId): void
    {
        $ticket=$this->find($ticketId);
        if(($ticket['reserved']??0)>0)$this->releaseReservation($ticketId,$eventId);
        else $this->releaseSold($ticketId,$eventId);
    }
}
