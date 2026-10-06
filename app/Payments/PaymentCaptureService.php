<?php
declare(strict_types=1);

namespace DigiSangam\Payments;

use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Commerce\OrderRepository;
use DigiSangam\Core\EventJournal\EventJournal;
use DigiSangam\Notifications\NotificationOutbox;
use DigiSangam\Registration\RegistrationRepository;

final class PaymentCaptureService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly AttendeeRepository $attendees,
        private readonly RegistrationRepository $registration,
        private readonly NotificationOutbox $notifications,
        private readonly EventJournal $journal,
    ) {}

    public function capture(string $orderId,string $provider,string $paymentReference): array
    {
        $order=$this->orders->find($orderId);
        if(!$order) throw new \RuntimeException('Order not found.');
        if(($order['status']??'')==='paid') return ['order'=>$order,'attendee'=>$this->attendees->find((string)$order['attendee_id']),'duplicate'=>true];

        $order=$this->orders->updatePayment($orderId,[
            'status'=>'paid','provider'=>$provider,'payment_reference'=>$paymentReference,
        ]) ?? $order;

        $attendee=$this->attendees->find((string)$order['attendee_id']);
        if($attendee){
            $schema=$this->registration->schema((string)$order['event_id']);
            if(($schema['approval_mode']??'auto')!=='manual'){
                $attendee=$this->attendees->update((string)$attendee['id'],['status'=>'Confirmed']) ?? $attendee;
            }
            try{
                $this->notifications->queue('email','payment_confirmed',['email'=>$attendee['email']??''],[
                    'event_id'=>$order['event_id'],'attendee_id'=>$attendee['id'],'order_id'=>$order['id'],
                    'confirmation_token'=>$attendee['confirmation_token']??'',
                ]);
                if(!empty($attendee['phone'])){
                    $this->notifications->queue('whatsapp','payment_confirmed',['phone'=>$attendee['phone']],[
                        'event_id'=>$order['event_id'],'attendee_id'=>$attendee['id'],'order_id'=>$order['id'],
                    ]);
                }
            }catch(\Throwable){}
        }

        $this->journal->append('payment.captured',[
            'order_id'=>$order['id'],'provider'=>$provider,'payment_reference'=>$paymentReference,
        ]);

        return ['order'=>$order,'attendee'=>$attendee];
    }
}
