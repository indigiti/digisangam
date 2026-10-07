<?php
declare(strict_types=1);

namespace DigiSangam\OnGround;

use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Commerce\OrderRepository;
use DigiSangam\Credentials\CredentialService;
use DigiSangam\Events\EventRepository;
use DigiSangam\Registration\RegistrationRepository;
use DigiSangam\Tickets\TicketRepository;

final class WalkInRegistrationService
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly RegistrationRepository $registration,
        private readonly TicketRepository $tickets,
        private readonly AttendeeRepository $attendees,
        private readonly OrderRepository $orders,
        private readonly CredentialService $credentials,
    ) {}

    public function register(string $eventId,array $input): array
    {
        $event=$this->events->find($eventId);
        if(!$event) throw new \InvalidArgumentException('Event not found.');
        $name=trim((string)($input['name']??''));
        $email=strtolower(trim((string)($input['email']??'')));
        $phone=trim((string)($input['phone']??''));
        if($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Name and a valid email are required.');

        $schema=$this->registration->schema($eventId);
        $category=trim((string)($input['category']??($schema['categories'][0]??'General')));
        if(!in_array($category,(array)($schema['categories']??[]),true)) throw new \InvalidArgumentException('Invalid attendee category.');

        $ticketId=trim((string)($input['ticket_id']??''));
        $ticket=$ticketId!==''?$this->tickets->reserveOne($ticketId,$eventId):null;
        $amount=(int)($ticket['price']??0);
        $settled=(bool)($input['payment_settled']??($amount===0));
        $status=$settled?'Confirmed':'Pending';

        try{
            $attendee=$this->attendees->create([
                'event_id'=>$eventId,'name'=>$name,'email'=>$email,'phone'=>$phone,
                'company'=>trim((string)($input['company']??'')),'category'=>$category,'status'=>$status,
                'ticket_id'=>$ticketId,'source'=>'walk_in',
            ]);
            $order=null;
            if($ticket){
                $order=$this->orders->create([
                    'event_id'=>$eventId,'attendee_id'=>$attendee['id'],'ticket_id'=>$ticketId,
                    'amount'=>$amount,'currency'=>$event['currency']??'INR',
                    'status'=>$settled?'paid':'pending','provider'=>'manual',
                    'payment_reference'=>trim((string)($input['payment_reference']??'')),
                ]);
            }
            if($ticket&&$settled)$ticket=$this->tickets->commitReservation($ticketId,$eventId);
            $credential=$status==='Confirmed'?$this->credentials->issue((string)$attendee['id'],$eventId):null;
            return ['attendee'=>$attendee,'ticket'=>$ticket,'order'=>$order,'credential'=>$credential];
        }catch(\Throwable $e){
            if($ticket) $this->tickets->releaseReservation($ticketId,$eventId);
            throw $e;
        }
    }
}
