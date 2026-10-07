<?php
declare(strict_types=1);

namespace DigiSangam\PublicFlow;

use DigiSangam\Events\EventRepository;
use DigiSangam\Tickets\TicketRepository;

final class PublicDiscoveryService
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly TicketRepository $tickets,
    ) {}

    public function browse(): array
    {
        $rows=[];
        foreach($this->events->all() as $event){
            if(!in_array((string)($event['status']??''),['Published','Live'],true)) continue;
            if(($event['privacy']??'public')!=='public') continue;

            $eventId=(string)($event['id']??'');
            $tickets=$this->tickets->publicForEvent($eventId);
            $prices=array_map(static fn(array $t): int => (int)($t['price']??0),$tickets);
            $available=array_sum(array_map(static fn(array $t): int => max(0,(int)($t['quantity']??0)-(int)($t['sold']??0)),$tickets));

            $rows[]=[
                'id'=>$eventId,
                'name'=>(string)($event['name']??'Event'),
                'headline'=>(string)($event['public_page']['headline']??$event['name']??'Event'),
                'description'=>(string)($event['description']??''),
                'type'=>(string)($event['type']??'Event'),
                'category'=>(string)($event['category']??'Featured'),
                'format'=>(string)($event['format']??'in_person'),
                'start_date'=>(string)($event['start_date']??$event['date']??''),
                'end_date'=>(string)($event['end_date']??''),
                'location'=>(string)($event['location']??''),
                'venue_name'=>(string)($event['venue_name']??''),
                'currency'=>(string)($event['currency']??'INR'),
                'status'=>(string)($event['status']??'Published'),
                'branding'=>(array)($event['branding']??[]),
                'min_price'=>$prices===[]?0:min($prices),
                'ticket_count'=>count($tickets),
                'available'=>$available,
            ];
        }

        usort($rows,static function(array $a,array $b): int {
            $aDate=(string)($a['start_date']??'9999-12-31');
            $bDate=(string)($b['start_date']??'9999-12-31');
            return $aDate<=>$bDate;
        });

        $categories=array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string)$row['category'],$rows))));
        sort($categories);
        $locations=array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string)$row['location'],$rows))));
        sort($locations);

        return [
            'events'=>$rows,
            'categories'=>$categories,
            'locations'=>$locations,
            'total'=>count($rows),
        ];
    }
}
