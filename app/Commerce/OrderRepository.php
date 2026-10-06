<?php
declare(strict_types=1);

namespace DigiSangam\Commerce;

use DigiSangam\Core\Storage\JsonFileStore;

final class OrderRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('orders/index.json', []);
    }

    public function find(string $id): ?array
    {
        foreach($this->all() as $row) if(($row['id']??'')===$id) return $row;
        return null;
    }

    public function findByProviderOrderId(string $providerOrderId): ?array
    {
        if($providerOrderId==='') return null;
        foreach($this->all() as $row) if(($row['provider_order_id']??'')===$providerOrderId) return $row;
        return null;
    }

    public function findLatestByAttendee(string $attendeeId): ?array
    {
        foreach($this->all() as $row) if(($row['attendee_id']??'')===$attendeeId) return $row;
        return null;
    }

    public function create(array $input): array
    {
        $rows = $this->all();
        $amount = max(0, (int)($input['amount'] ?? 0));
        $record = [
            'id'=>'ord_'.date('Ymd').'_'.bin2hex(random_bytes(4)),
            'event_id'=>(string)($input['event_id'] ?? 'evt_001'),
            'attendee_id'=>(string)($input['attendee_id'] ?? ''),
            'ticket_id'=>(string)($input['ticket_id'] ?? ''),
            'amount'=>$amount,
            'currency'=>strtoupper((string)($input['currency'] ?? 'INR')),
            'status'=>(string)($input['status'] ?? 'pending'),
            'payment_reference'=>(string)($input['payment_reference'] ?? ''),
            'provider'=>(string)($input['provider'] ?? ''),
            'provider_order_id'=>(string)($input['provider_order_id'] ?? ''),
            'created_at'=>date(DATE_ATOM),
        ];
        array_unshift($rows, $record);
        $this->store->write('orders/index.json', $rows);
        return $record;
    }

    public function updatePayment(string $id, array $payment): ?array
    {
        $rows=$this->all(); $updated=null;
        foreach($rows as &$row){
            if(($row['id']??'')!==$id) continue;
            $row['status']=(string)($payment['status'] ?? $row['status']);
            $row['payment_reference']=(string)($payment['payment_reference'] ?? $row['payment_reference']);
            $row['provider']=(string)($payment['provider'] ?? $row['provider']);
            if(isset($payment['provider_order_id'])) $row['provider_order_id']=(string)$payment['provider_order_id'];
            $row['updated_at']=date(DATE_ATOM);
            $updated=$row;
            break;
        }
        unset($row);
        if($updated!==null) $this->store->write('orders/index.json',$rows);
        return $updated;
    }
}
