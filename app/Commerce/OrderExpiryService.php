<?php
declare(strict_types=1);

namespace DigiSangam\Commerce;

use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Core\EventJournal\EventJournal;
use DigiSangam\Tickets\TicketRepository;

final class OrderExpiryService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly TicketRepository $tickets,
        private readonly AttendeeRepository $attendees,
        private readonly EventJournal $journal,
    ) {}

    public function run(int $limit=100): array
    {
        $checked=0;$expired=0;$released=0;
        $now=time();

        foreach($this->orders->all() as $order){
            if($checked>=$limit)break;
            if(($order['status']??'')!=='pending')continue;
            $expires=(string)($order['reservation_expires_at']??'');
            if($expires===''||strtotime($expires)===false||strtotime($expires)>$now)continue;
            $checked++;

            $transition=$this->orders->transitionStatus((string)$order['id'],['pending'],'expired',[
                'reservation_expires_at'=>'',
                'expired_at'=>date(DATE_ATOM),
            ]);
            if(!$transition)continue;

            if(!empty($order['ticket_id'])){
                $this->tickets->releaseReservation((string)$order['ticket_id'],(string)$order['event_id']);
                $released++;
            }
            if(!empty($order['attendee_id'])){
                $attendee=$this->attendees->find((string)$order['attendee_id']);
                if($attendee&&($attendee['status']??'')!=='Confirmed'){
                    $this->attendees->update((string)$attendee['id'],['status'=>'Pending']);
                }
            }
            $this->journal->append('order.expired',[
                'order_id'=>$order['id'],'event_id'=>$order['event_id'],'attendee_id'=>$order['attendee_id']??'',
            ]);
            $expired++;
        }

        return ['checked'=>$checked,'expired'=>$expired,'reservations_released'=>$released];
    }
}
