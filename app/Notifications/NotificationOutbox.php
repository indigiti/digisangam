<?php
declare(strict_types=1);

namespace DigiSangam\Notifications;

use DigiSangam\Core\Storage\JsonFileStore;

final class NotificationOutbox
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function queue(string $channel, string $template, array $recipient, array $data, string $notBefore = ''): array
    {
        $record = [
            'id'=>'msg_'.bin2hex(random_bytes(6)),
            'channel'=>$channel,
            'template'=>$template,
            'recipient'=>$recipient,
            'data'=>$data,
            'status'=>'queued',
            'attempts'=>0,
            'not_before'=>$notBefore,
            'created_at'=>date(DATE_ATOM),
        ];
        $this->store->transaction('notifications/outbox.json', static function(array $rows) use ($record): array {
            array_unshift($rows, $record);
            return ['data'=>$rows,'result'=>$record];
        }, []);
        return $record;
    }

    public function pending(int $limit=25): array
    {
        $now=time();
        $rows=$this->store->read('notifications/outbox.json',[]);
        $pending=array_values(array_filter($rows,static function(array $row) use ($now): bool {
            if(!in_array($row['status']??'queued',['queued','retry'],true)) return false;
            $next=(string)($row['next_attempt_at']??'');
            $notBefore=(string)($row['not_before']??'');
            if($notBefore!=='' && (strtotime($notBefore)?:0)>$now) return false;
            return $next==='' || (strtotime($next)?:0) <= $now;
        }));
        return array_slice($pending,0,max(1,$limit));
    }

    public function mark(string $id,string $status,array $meta=[]): ?array
    {
        return $this->store->transaction('notifications/outbox.json',static function(array $rows) use ($id,$status,$meta): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id) continue;
                $row['status']=$status;
                $row['attempts']=(int)($row['attempts']??0)+1;
                $row['updated_at']=date(DATE_ATOM);
                foreach($meta as $key=>$value) $row[$key]=$value;
                $updated=$row;
                break;
            }
            unset($row);
            return ['data'=>$rows,'result'=>$updated];
        },[]);
    }
}
