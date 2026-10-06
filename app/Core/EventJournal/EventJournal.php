<?php
declare(strict_types=1);

namespace DigiSangam\Core\EventJournal;

use DigiSangam\Core\Storage\JsonFileStore;

final class EventJournal
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function append(string $event,array $data): void
    {
        $entry=['id'=>'log_'.bin2hex(random_bytes(8)),'event'=>$event,'occurred_at'=>date(DATE_ATOM),'data'=>$data];
        $path='journal/'.date('Y-m-d').'.json';
        $this->store->transaction($path,static function(array $entries) use ($entry): array {
            $entries[]=$entry;return ['data'=>$entries,'result'=>null];
        },[]);
        $this->queueWebhooks($event,$data);
    }

    private function queueWebhooks(string $event,array $data): void
    {
        $eventId=(string)($data['event_id']??'');
        if($eventId==='') return;
        $endpoints=$this->store->read('developer/webhooks.json',[]);
        foreach($endpoints as $endpoint){
            if(empty($endpoint['active'])||($endpoint['event_id']??'')!==$eventId)continue;
            $events=(array)($endpoint['events']??[]);
            if(!in_array('*',$events,true)&&!in_array($event,$events,true))continue;
            $record=[
                'id'=>'whd_'.bin2hex(random_bytes(6)),'webhook_id'=>$endpoint['id'],'url'=>$endpoint['url'],
                'secret'=>$endpoint['secret'],'event'=>$event,'data'=>$data,'status'=>'queued','attempts'=>0,'created_at'=>date(DATE_ATOM),
            ];
            $this->store->transaction('developer/webhook-outbox.json',static function(array $rows) use ($record): array {
                array_unshift($rows,$record);return ['data'=>$rows,'result'=>null];
            },[]);
        }
    }
}
