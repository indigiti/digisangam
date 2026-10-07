<?php
declare(strict_types=1);

namespace DigiSangam\Commerce;

use DigiSangam\Core\Storage\JsonFileStore;

final class OrderRepository
{
    private const PATH='orders/index.json';
    private const STATUSES=['pending','capturing','paid','failed','refunded','expired','cancelled'];

    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array { return $this->store->read(self::PATH,[]); }
    public function find(string $id): ?array { foreach($this->all() as $row)if(($row['id']??'')===$id)return $row;return null; }
    public function findByProviderOrderId(string $providerOrderId): ?array { if($providerOrderId==='')return null;foreach($this->all() as $row)if(($row['provider_order_id']??'')===$providerOrderId)return $row;return null; }
    public function findLatestByAttendee(string $attendeeId): ?array { foreach($this->all() as $row)if(($row['attendee_id']??'')===$attendeeId)return $row;return null; }

    public function create(array $input): array
    {
        $eventId=trim((string)($input['event_id']??''));
        $status=(string)($input['status']??'pending');
        if($eventId==='') throw new \InvalidArgumentException('Event is required.');
        if(!in_array($status,self::STATUSES,true)) throw new \InvalidArgumentException('Invalid order status.');
        $record=[
            'id'=>'ord_'.date('Ymd').'_'.bin2hex(random_bytes(4)),
            'event_id'=>$eventId,'attendee_id'=>(string)($input['attendee_id']??''),
            'ticket_id'=>(string)($input['ticket_id']??''),
            'amount'=>max(0,(int)($input['amount']??0)),
            'currency'=>strtoupper((string)($input['currency']??'INR')),
            'status'=>$status,'payment_reference'=>(string)($input['payment_reference']??''),
            'provider'=>(string)($input['provider']??''),
            'provider_order_id'=>(string)($input['provider_order_id']??''),
            'reservation_expires_at'=>(string)($input['reservation_expires_at']??''),
            'created_at'=>date(DATE_ATOM),
        ];
        return $this->store->transaction(self::PATH,static function(array $rows) use ($record): array {
            array_unshift($rows,$record);return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function updatePayment(string $id,array $payment): ?array
    {
        return $this->store->transaction(self::PATH,static function(array $rows) use ($id,$payment): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id)continue;
                $nextStatus=(string)($payment['status']??$row['status']);
                if(!in_array($nextStatus,self::STATUSES,true)) throw new \InvalidArgumentException('Invalid payment status.');
                $row['status']=$nextStatus;
                foreach(['payment_reference','provider','provider_order_id','reservation_expires_at'] as $field){
                    if(array_key_exists($field,$payment))$row[$field]=(string)$payment[$field];
                }
                $row['updated_at']=date(DATE_ATOM);$updated=$row;break;
            }
            unset($row);
            return ['data'=>$rows,'result'=>$updated];
        },[]);
    }

    public function transitionStatus(string $id,array $from,string $to,array $meta=[]): ?array
    {
        if(!in_array($to,self::STATUSES,true)) throw new \InvalidArgumentException('Invalid order status.');
        return $this->store->transaction(self::PATH,static function(array $rows) use ($id,$from,$to,$meta): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id)continue;
                if(!in_array((string)($row['status']??''),$from,true))break;
                $row['status']=$to;
                foreach($meta as $field=>$value)$row[$field]=$value;
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
}
