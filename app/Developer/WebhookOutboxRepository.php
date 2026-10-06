<?php
declare(strict_types=1);

namespace DigiSangam\Developer;

use DigiSangam\Core\Storage\JsonFileStore;

final class WebhookOutboxRepository
{
    public function __construct(private readonly JsonFileStore $store) {}
    public function all(): array{return $this->store->read('developer/webhook-outbox.json',[]);}
    public function queue(array $endpoint,string $event,array $data): array {
        $row=['id'=>'whd_'.bin2hex(random_bytes(6)),'webhook_id'=>$endpoint['id'],'url'=>$endpoint['url'],'secret'=>$endpoint['secret'],'event'=>$event,'data'=>$data,'status'=>'queued','attempts'=>0,'created_at'=>date(DATE_ATOM)];
        $rows=$this->all();array_unshift($rows,$row);$this->store->write('developer/webhook-outbox.json',$rows);return $row;
    }
    public function pending(int $limit=25): array{return array_slice(array_values(array_filter($this->all(),fn($x)=>in_array(($x['status']??''),['queued','retry'],true))),0,max(1,$limit));}
    public function mark(string $id,string $status,array $meta=[]): void {
        $rows=$this->all();foreach($rows as &$r){if(($r['id']??'')!==$id)continue;$r['status']=$status;$r['attempts']=(int)($r['attempts']??0)+1;$r=array_merge($r,$meta,['updated_at'=>date(DATE_ATOM)]);break;}unset($r);$this->store->write('developer/webhook-outbox.json',$rows);
    }
}
