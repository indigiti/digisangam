<?php
declare(strict_types=1);

namespace DigiSangam\PublicFlow;

use DigiSangam\Events\EventRepository;
use DigiSangam\Tickets\TicketRepository;

/**
 * Public discovery index: only published, public events are eligible.
 * Filtering is server-side; facets always reflect the eligible event catalogue.
 * This service does not fabricate offers, ratings, proximity, or recommendations.
 */
final class PublicDiscoveryService
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly TicketRepository $tickets,
    ) {}

    public function browse(array $filters=[]): array
    {
        // Snapshot inventory once: avoid O(events × tickets) JSON reads on every browse.
        // Count active sale-window tickets even when sold out so the UI can label them correctly.
        $ticketSummary=[];
        $today=date('Y-m-d');
        foreach($this->tickets->all() as $ticket){
            if(($ticket['status']??'')!=='Active')continue;
            if(!empty($ticket['sale_start']) && $ticket['sale_start']>$today)continue;
            if(!empty($ticket['sale_end']) && $ticket['sale_end']<$today)continue;
            $id=(string)($ticket['event_id']??'');
            if($id==='')continue;
            if(!isset($ticketSummary[$id]))$ticketSummary[$id]=['prices'=>[],'count'=>0,'available'=>0];
            $ticketSummary[$id]['count']++;
            $ticketSummary[$id]['prices'][]=max(0,(int)($ticket['price']??0));
            $ticketSummary[$id]['available']+=max(0,(int)($ticket['quantity']??0)-(int)($ticket['sold']??0)-(int)($ticket['reserved']??0));
        }

        $all=[];
        foreach($this->events->all() as $event){
            if(!in_array((string)($event['status']??''),['Published','Live'],true)) continue;
            if(($event['privacy']??'public')!=='public') continue;

            $eventId=(string)($event['id']??'');
            $summary=$ticketSummary[$eventId]??['prices'=>[],'count'=>0,'available'=>0];
            $prices=$summary['prices'];
            $available=$summary['available'];
            $location=trim((string)($event['location']??''));
            $city=trim((string)($event['city']??''));
            if($city==='') $city=trim(explode(',',$location,2)[0]);
            if($city==='' && ($event['format']??'')==='virtual') $city='Online';

            $all[]=[
                'id'=>$eventId,
                'name'=>(string)($event['name']??'Event'),
                'headline'=>(string)($event['public_page']['headline']??$event['name']??'Event'),
                'description'=>(string)($event['description']??''),
                'type'=>(string)($event['type']??'Event'),
                'category'=>(string)($event['category']??'Featured'),
                'format'=>(string)($event['format']??'in_person'),
                'start_date'=>(string)($event['start_date']??$event['date']??''),
                'end_date'=>(string)($event['end_date']??''),
                'timezone'=>(string)($event['timezone']??'Asia/Kolkata'),
                'location'=>$location,
                'city'=>$city,
                'venue_name'=>(string)($event['venue_name']??''),
                'currency'=>(string)($event['currency']??'INR'),
                'status'=>(string)($event['status']??'Published'),
                'branding'=>(array)($event['branding']??[]),
                'min_price'=>$prices===[]?0:min($prices),
                'ticket_count'=>$summary['count'],
                'available'=>$available,
            ];
        }

        $categories=array_values(array_unique(array_filter(array_column($all,'category'))));
        sort($categories,SORT_NATURAL|SORT_FLAG_CASE);
        $locations=array_values(array_unique(array_filter(array_column($all,'location'))));
        sort($locations,SORT_NATURAL|SORT_FLAG_CASE);
        $cities=array_values(array_unique(array_filter(array_column($all,'city'))));
        sort($cities,SORT_NATURAL|SORT_FLAG_CASE);

        $q=strtolower(trim(substr((string)($filters['q']??''),0,120)));
        $category=strtolower(trim(substr((string)($filters['category']??''),0,80)));
        $currency=strtoupper(trim(substr((string)($filters['currency']??''),0,3)));
        $city=strtolower(trim(substr((string)($filters['city']??''),0,100)));
        $date=strtolower(trim((string)($filters['date']??'')));
        $minPrice=isset($filters['min_price']) && $filters['min_price']!=='' && is_numeric($filters['min_price'])?max(0,(float)$filters['min_price']):null;
        $maxPrice=isset($filters['max_price']) && $filters['max_price']!=='' && is_numeric($filters['max_price'])?max(0,(float)$filters['max_price']):null;
        $availableOnly=filter_var($filters['available_only']??false,FILTER_VALIDATE_BOOLEAN);

        $rows=array_values(array_filter($all,static function(array $event) use ($q,$category,$city,$date,$minPrice,$maxPrice,$availableOnly,$currency): bool {
            if($q!==''){
                $hay=strtolower(implode(' ',[
                    $event['name'],$event['headline'],$event['description'],$event['type'],
                    $event['category'],$event['location'],$event['city'],$event['venue_name']
                ]));
                if(!str_contains($hay,$q)) return false;
            }
            if($category!=='' && $category!=='all' && strtolower($event['category'])!==$category) return false;
            if($currency!=='' && strtoupper($event['currency'])!==$currency) return false;
            if($city!=='' && $city!=='all' && strtolower($event['city'])!==$city) return false;
            if($availableOnly && $event['available']<=0) return false;
            if($minPrice!==null && ($event['ticket_count']===0 || $event['min_price']<$minPrice)) return false;
            if($maxPrice!==null && ($event['ticket_count']===0 || $event['min_price']>$maxPrice)) return false;
            if(in_array($date,['today','tomorrow','weekend','upcoming'],true)){
                try { $tz=new \DateTimeZone($event['timezone']); }
                catch(\Throwable){ $tz=new \DateTimeZone('Asia/Kolkata'); }
                $today=new \DateTimeImmutable('today',$tz);
                $start=(string)$event['start_date'];
                $end=(string)($event['end_date']?:$start);
                if($start==='') return false;
                $target=$today->format('Y-m-d');
                if($date==='tomorrow') $target=$today->modify('+1 day')->format('Y-m-d');
                if(in_array($date,['today','tomorrow'],true) && ($start>$target || $end<$target)) return false;
                if($date==='upcoming' && $end<$target) return false;
                if($date==='weekend'){
                    $sat=$today->modify('saturday this week');
                    if((int)$today->format('N')===7)$sat=$today->modify('yesterday');
                    $sun=$sat->modify('+1 day');
                    if($end<$sat->format('Y-m-d') || $start>$sun->format('Y-m-d'))return false;
                }
            }
            return true;
        }));

        $sort=in_array($filters['sort']??'date',['date','price_asc','price_desc','name'],true)?(string)($filters['sort']??'date'):'date';
        usort($rows,static function(array $a,array $b)use($sort): int {
            if($sort==='name') return strcasecmp($a['name'],$b['name']);
            if($sort==='price_asc')return ($a['min_price']<=>$b['min_price']) ?: strcmp($a['start_date'],$b['start_date']);
            if($sort==='price_desc')return ($b['min_price']<=>$a['min_price']) ?: strcmp($a['start_date'],$b['start_date']);
            $ad=$a['start_date']?:'9999-12-31';
            $bd=$b['start_date']?:'9999-12-31';
            return strcmp($ad,$bd) ?: strcmp($a['name'],$b['name']);
        });
        $total=count($rows);

        $page=max(1,min(100000,(int)($filters['page']??1)));
        $perPage=isset($filters['per_page']) && $filters['per_page']!=='' ? max(1,min(48,(int)$filters['per_page'])) : null;
        if($perPage!==null) $rows=array_slice($rows,($page-1)*$perPage,$perPage);

        return [
            'events'=>$rows,
            'categories'=>$categories,
            'locations'=>$locations,
            'cities'=>$cities,
            'total'=>$total,
            'catalogue_total'=>count($all),
            'page'=>$page,
            'per_page'=>$perPage,
            'has_more'=>$perPage!==null && $page*$perPage<$total,
        ];
    }
}
