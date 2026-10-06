<?php
declare(strict_types=1);

namespace DigiSangam\Intelligence;

use DigiSangam\Agenda\SessionAttendanceRepository;
use DigiSangam\Agenda\SessionRepository;
use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Commerce\OrderRepository;
use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Events\EventRepository;
use DigiSangam\Exhibitors\ExhibitorRepository;
use DigiSangam\Exhibitors\LeadRepository;
use DigiSangam\Exhibitors\MeetingRepository;
use DigiSangam\OnGround\CheckinRepository;
use DigiSangam\Tickets\TicketRepository;
use DigiSangam\Venue\SeatAssignmentRepository;
use DigiSangam\Venue\VenueRepository;

final class EventGraphBuilder
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function build(string $eventId): array
    {
        $attendeeRepo=new AttendeeRepository($this->store);
        $orderRepo=new OrderRepository($this->store);
        $ticketRepo=new TicketRepository($this->store);
        $sessionRepo=new SessionRepository($this->store);
        $exhibitorRepo=new ExhibitorRepository($this->store);
        $leadRepo=new LeadRepository($this->store);
        $meetingRepo=new MeetingRepository($this->store);
        $checkinRepo=new CheckinRepository($this->store);
        $venueRepo=new VenueRepository($this->store);
        $seatRepo=new SeatAssignmentRepository($this->store);

        $event=(new EventRepository($this->store))->find($eventId);
        $attendees=$this->filter($attendeeRepo->all(),$eventId);
        $orders=$this->filter($orderRepo->all(),$eventId);
        $tickets=$this->filter($ticketRepo->all(),$eventId);
        $sessions=$this->filter($sessionRepo->all(),$eventId);
        $exhibitors=$this->filter($exhibitorRepo->all(),$eventId);
        $leads=$this->filter($leadRepo->all(),$eventId);
        $meetings=$this->filter($meetingRepo->all(),$eventId);
        $checkins=$checkinRepo->all($eventId);
        $venue=$venueRepo->get($eventId);
        $zones=(array)($venue['zones']??[]);
        $seats=$seatRepo->all($eventId);

        $sessionAttendance=[];
        foreach($sessions as $session){
            $sessionAttendance[(string)$session['id']]=(new SessionAttendanceRepository($this->store))->all((string)$session['id']);
        }

        $edges=[];
        foreach($attendees as $attendee){
            if(!empty($attendee['ticket_id'])) $edges[]=['type'=>'holds_ticket','from'=>$attendee['id'],'to'=>$attendee['ticket_id']];
        }
        foreach($orders as $order){
            if(!empty($order['attendee_id'])) $edges[]=['type'=>'order','from'=>$order['attendee_id'],'to'=>$order['id'],'status'=>$order['status']??''];
        }
        foreach($checkins as $row){
            $edges[]=['type'=>'checked_in','from'=>$row['attendee_id']??'','to'=>$eventId,'at'=>$row['checked_in_at']??'','zone_id'=>$row['zone_id']??''];
        }
        foreach($sessionAttendance as $sessionId=>$rows){
            foreach($rows as $row) $edges[]=['type'=>'session_attendance','from'=>$row['attendee_id']??'','to'=>$sessionId,'at'=>$row['entered_at']??''];
        }
        foreach($leads as $lead){
            $edges[]=['type'=>'lead','from'=>$lead['exhibitor_id']??'','to'=>$lead['attendee_id']??'','lead_id'=>$lead['id']??''];
        }
        foreach($meetings as $meeting){
            $edges[]=['type'=>'meeting','from'=>$meeting['exhibitor_id']??'','to'=>$meeting['attendee_id']??'','meeting_id'=>$meeting['id']??'','start_at'=>$meeting['start_at']??''];
        }
        foreach($seats as $seat){
            $edges[]=['type'=>'seat','from'=>$seat['attendee_id']??'','to'=>$seat['hall_id']??'','seat'=>$seat['seat']??''];
        }

        $confirmed=count(array_filter($attendees,static fn(array $x): bool => ($x['status']??'')==='Confirmed'));
        $pending=count(array_filter($attendees,static fn(array $x): bool => ($x['status']??'')==='Pending'));
        $paidOrders=array_values(array_filter($orders,static fn(array $x): bool => ($x['status']??'')==='paid'));
        $failedOrders=array_values(array_filter($orders,static fn(array $x): bool => ($x['status']??'')==='failed'));
        $paidRevenue=array_sum(array_map(static fn(array $x): int => (int)($x['amount']??0),$paidOrders));
        $paymentAttempts=count(array_filter($orders,static fn(array $x): bool => (int)($x['amount']??0)>0));
        $eventCapacity=array_sum(array_map(static fn(array $x): int => (int)($x['quantity']??0),$tickets));
        if($eventCapacity<=0) $eventCapacity=array_sum(array_map(static fn(array $x): int => (int)($x['capacity']??0),$zones));

        $zoneOccupancy=[];
        foreach($checkins as $row){
            $zone=(string)($row['zone_id']??'');
            if($zone!=='') $zoneOccupancy[$zone]=($zoneOccupancy[$zone]??0)+1;
        }

        return [
            'event_id'=>$eventId,
            'generated_at'=>date(DATE_ATOM),
            'event'=>$event,
            'nodes'=>[
                'attendees'=>$this->publicAttendees($attendees),
                'orders'=>$orders,
                'tickets'=>$tickets,
                'sessions'=>$sessions,
                'exhibitors'=>$exhibitors,
                'leads'=>$leads,
                'meetings'=>$meetings,
                'zones'=>$zones,
                'seats'=>$seats,
            ],
            'edges'=>$edges,
            'metrics'=>[
                'registrations'=>count($attendees),
                'confirmed'=>$confirmed,
                'pending_approvals'=>$pending,
                'checked_in'=>count($checkins),
                'checkin_rate'=>$confirmed>0?count($checkins)/$confirmed:0,
                'paid_revenue'=>$paidRevenue,
                'paid_orders'=>count($paidOrders),
                'failed_orders'=>count($failedOrders),
                'payment_failure_rate'=>$paymentAttempts>0?count($failedOrders)/$paymentAttempts:0,
                'event_capacity'=>$eventCapacity,
                'registration_trend'=>$this->registrationTrend($attendees),
                'zone_occupancy'=>$zoneOccupancy,
                'session_attendance'=>array_map('count',$sessionAttendance),
                'leads'=>count($leads),
                'meetings'=>count($meetings),
            ],
        ];
    }

    private function filter(array $rows,string $eventId): array
    {
        return array_values(array_filter($rows,static fn(array $row): bool => ($row['event_id']??'')===$eventId));
    }

    private function publicAttendees(array $rows): array
    {
        return array_map(static fn(array $row): array => [
            'id'=>$row['id']??'',
            'name'=>$row['name']??'',
            'category'=>$row['category']??'General',
            'company'=>$row['company']??'',
            'status'=>$row['status']??'',
            'ticket_id'=>$row['ticket_id']??'',
            'created_at'=>$row['created_at']??'',
            'answers'=>$row['answers']??[],
        ],$rows);
    }

    private function registrationTrend(array $attendees): array
    {
        $days=[];
        foreach($attendees as $row){
            $created=(string)($row['created_at']??'');
            if($created==='') continue;
            $ts=strtotime($created);
            if($ts===false) continue;
            $key=date('Y-m-d',$ts);
            $days[$key]=($days[$key]??0)+1;
        }
        if($days===[]) return [count($attendees)];
        ksort($days);
        return array_values(array_slice($days,-14,14,true));
    }
}
