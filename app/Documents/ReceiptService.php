<?php
declare(strict_types=1);

namespace DigiSangam\Documents;

final class ReceiptService
{
    public function build(array $event,array $attendee,array $ticket,?array $order,array $workspace=[]): array
    {
        $amount=(int)($order['amount']??0);
        return [
            'receipt_number'=>'RCPT-'.strtoupper(preg_replace('/[^A-Za-z0-9]/','',(string)($order['id']??$attendee['id']))),
            'issued_at'=>(string)($order['updated_at']??$order['created_at']??date(DATE_ATOM)),
            'seller'=>[
                'name'=>(string)($workspace['legal_name']??$workspace['name']??'DigiSangam Event Organizer'),
                'gstin'=>(string)($workspace['gstin']??''),
                'address'=>(string)($workspace['billing_address']??''),
            ],
            'buyer'=>['name'=>$attendee['name']??'','email'=>$attendee['email']??''],
            'event'=>['name'=>$event['name']??'','date'=>$event['start_date']??$event['date']??'','location'=>$event['location']??''],
            'ticket'=>['name'=>$ticket['name']??'','amount'=>$amount],
            'payment'=>[
                'status'=>$order['status']??($amount===0?'paid':'pending'),
                'currency'=>$order['currency']??'INR',
                'provider'=>$order['provider']??'',
                'reference'=>$order['payment_reference']??'',
            ],
            'total'=>$amount,
        ];
    }
}
