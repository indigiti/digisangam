<?php
declare(strict_types=1);

namespace DigiSangam\Tickets;

use DigiSangam\Core\Storage\JsonFileStore;

final class TicketRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('tickets/index.json', []);
    }

    public function publicForEvent(string $eventId): array
    {
        $today = date('Y-m-d');
        return array_values(array_filter($this->all(), static function(array $row) use ($eventId,$today): bool {
            if (($row['event_id'] ?? '') !== $eventId || ($row['status'] ?? '') !== 'Active') return false;
            if (!empty($row['sale_start']) && $row['sale_start'] > $today) return false;
            if (!empty($row['sale_end']) && $row['sale_end'] < $today) return false;
            return (int)($row['sold'] ?? 0) < (int)($row['quantity'] ?? 0);
        }));
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
            'event_id'=>trim((string)($input['event_id'] ?? '')),
            'sale_start'=>(string)($input['sale_start'] ?? ''),
            'sale_end'=>(string)($input['sale_end'] ?? ''),
            'created_at'=>date(DATE_ATOM),
        ];
        if($record['event_id']==='') throw new \InvalidArgumentException('Event is required.');
        if($record['name']==='') throw new \InvalidArgumentException('Ticket name is required.');
        if($record['sale_start']!=='' && $record['sale_end']!=='' && $record['sale_end']<$record['sale_start']) throw new \InvalidArgumentException('Sale end cannot be before sale start.');
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

    public function reserveOne(string $ticketId, string $eventId): array
    {
        return $this->store->transaction('tickets/index.json', function(array $rows) use ($ticketId,$eventId): array {
            $reserved = null;
            foreach ($rows as &$row) {
                if (($row['id'] ?? '') !== $ticketId || ($row['event_id'] ?? '') !== $eventId) continue;
                if (($row['status'] ?? '') !== 'Active') throw new \RuntimeException('This ticket is not available.');
                $sold=(int)($row['sold'] ?? 0); $quantity=(int)($row['quantity'] ?? 0);
                if ($sold >= $quantity) throw new \RuntimeException('This ticket is sold out.');
                $row['sold']=$sold+1;
                $row['updated_at']=date(DATE_ATOM);
                $reserved=$row;
                break;
            }
            unset($row);
            if ($reserved === null) throw new \RuntimeException('Ticket not found for this event.');
            return ['data'=>$rows,'result'=>$reserved];
        }, []);
    }

    public function releaseOne(string $ticketId,string $eventId): void
    {
        $this->store->transaction('tickets/index.json', function(array $rows) use ($ticketId,$eventId): array {
            foreach($rows as &$row){
                if(($row['id']??'')!==$ticketId || ($row['event_id']??'')!==$eventId) continue;
                $row['sold']=max(0,(int)($row['sold']??0)-1);
                $row['updated_at']=date(DATE_ATOM);
                break;
            }
            unset($row);
            return $rows;
        }, []);
    }

}
