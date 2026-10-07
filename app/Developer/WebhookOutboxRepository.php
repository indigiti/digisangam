<?php
declare(strict_types=1);

namespace DigiSangam\Developer;

use DigiSangam\Core\Storage\JsonFileStore;

final class WebhookOutboxRepository
{
    private const PATH='developer/webhook-outbox.json';
    public function __construct(private readonly JsonFileStore $store) {}
    public function all(): array{return $this->store->read(self::PATH,[]);}

    public function queue(array $endpoint,string $event,array $data): array
    {
        $row=['id'=>'whd_'.bin2hex(random_bytes(6)),'webhook_id'=>$endpoint['id'],'url'=>$endpoint['url'],'secret'=>$endpoint['secret'],'event'=>$event,'data'=>$data,'status'=>'queued','attempts'=>0,'created_at'=>date(DATE_ATOM)];
        return $this->store->transaction(self::PATH,static function(array $rows) use ($row): array {
            array_unshift($rows,$row);return ['data'=>$rows,'result'=>$row];
        },[]);
    }

    public function pending(int $limit=25): array
    {
        $now=time();
        return array_slice(array_values(array_filter($this->all(),static function(array $row) use ($now): bool {
            if(!in_array(($row['status']??''),['queued','retry'],true)) return false;
            $next=(string)($row['next_attempt_at']??'');
            return $next==='' || (strtotime($next)?:0)<=$now;
        })),0,max(1,$limit));
    }

    public function mark(string $id,string $status,array $meta=[]): void
    {
        $this->store->transaction(self::PATH,static function(array $rows) use ($id,$status,$meta): array {
            foreach($rows as &$row){
                if(($row['id']??'')!==$id)continue;
                $row['status']=$status;$row['attempts']=(int)($row['attempts']??0)+1;
                $row=array_merge($row,$meta,['updated_at'=>date(DATE_ATOM)]);break;
            }
            unset($row);return $rows;
        },[]);
    }
}
