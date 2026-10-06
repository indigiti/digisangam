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
            'created_at'=>date(DATE_ATOM),
        ];
        array_unshift($rows, $record);
        $this->store->write('orders/index.json', $rows);
        return $record;
    }
}
