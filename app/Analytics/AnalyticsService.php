<?php
declare(strict_types=1);

namespace DigiSangam\Analytics;

use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Commerce\OrderRepository;
use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Events\EventRepository;
use DigiSangam\OnGround\CheckinRepository;

final class AnalyticsService
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function dashboard(string $eventId=''): array
    {
        $events=(new EventRepository($this->store))->all();
        if($eventId==='') $eventId=(string)($events[0]['id']??'');
        $event=$eventId!==''?(new EventRepository($this->store))->find($eventId):null;

        if(!$event){
            return [
                'event'=>null,
                'stats'=>[
                    ['label'=>'Registrations','value'=>'0','delta'=>'','tone'=>'blue'],
                    ['label'=>'Confirmed','value'=>'0','delta'=>'','tone'=>'mint'],
                    ['label'=>'Checked-in','value'=>'0','delta'=>'','tone'=>'cyan'],
                    ['label'=>'Revenue','value'=>'₹0','delta'=>'','tone'=>'rose'],
                ],
                'trend'=>array_fill(0,14,0),
                'categories'=>[],
                'empty'=>true,
            ];
        }

        $attendees=array_values(array_filter((new AttendeeRepository($this->store))->all(),static fn(array $x): bool => ($x['event_id']??'')===$eventId));
        $orders=array_values(array_filter((new OrderRepository($this->store))->all(),static fn(array $x): bool => ($x['event_id']??'')===$eventId));
        $checkins=(new CheckinRepository($this->store))->all($eventId);

        $confirmed=count(array_filter($attendees,static fn(array $x): bool => ($x['status']??'')==='Confirmed'));
        $revenue=array_sum(array_map(static fn(array $x): int => ($x['status']??'')==='paid'?(int)($x['amount']??0):0,$orders));

        $byDay=[];
        foreach($attendees as $row){
            $ts=strtotime((string)($row['created_at']??''));
            if($ts===false) continue;
            $key=date('Y-m-d',$ts);
            $byDay[$key]=($byDay[$key]??0)+1;
        }
        $trend=[];
        for($i=13;$i>=0;$i--){
            $key=date('Y-m-d',strtotime('-'.$i.' days'));
            $trend[]=(int)($byDay[$key]??0);
        }

        $categoryCounts=[];
        foreach($attendees as $row){
            $name=trim((string)($row['category']??'Other'))?:'Other';
            $categoryCounts[$name]=($categoryCounts[$name]??0)+1;
        }
        $total=max(1,count($attendees));
        $palette=['#4f46e5','#10b981','#f59e0b','#8b5cf6','#f43f5e','#06b6d4','#94a3b8'];
        $categories=[];$i=0;
        foreach($categoryCounts as $label=>$count){
            $categories[]=['label'=>$label,'value'=>round($count/$total*100,1),'count'=>$count,'color'=>$palette[$i++%count($palette)]];
        }

        return [
            'event'=>[
                'id'=>$event['id'],
                'name'=>$event['name'],
                'date'=>$this->dateLabel($event),
                'location'=>$event['location']??'',
                'status'=>$event['status']??'Draft',
                'progress'=>$event['progress']??0,
            ],
            'stats'=>[
                ['label'=>'Registrations','value'=>number_format(count($attendees)),'delta'=>'','tone'=>'blue'],
                ['label'=>'Confirmed','value'=>number_format($confirmed),'delta'=>'','tone'=>'mint'],
                ['label'=>'Checked-in','value'=>number_format(count($checkins)),'delta'=>'','tone'=>'cyan'],
                ['label'=>'Revenue','value'=>'₹'.number_format($revenue),'delta'=>'','tone'=>'rose'],
            ],
            'trend'=>$trend,
            'categories'=>$categories,
            'metrics'=>[
                'registrations'=>count($attendees),
                'confirmed'=>$confirmed,
                'checked_in'=>count($checkins),
                'revenue'=>$revenue,
                'confirmation_rate'=>count($attendees)>0?round($confirmed/count($attendees)*100,1):0,
                'checkin_rate'=>$confirmed>0?round(count($checkins)/$confirmed*100,1):0,
            ],
            'empty'=>false,
        ];
    }

    private function dateLabel(array $event): string
    {
        $start=(string)($event['start_date']??'');
        $end=(string)($event['end_date']??'');
        if($start===''&&$end==='') return 'Date not set';
        if($end===''||$end===$start) return $start;
        return $start.' – '.$end;
    }
}
