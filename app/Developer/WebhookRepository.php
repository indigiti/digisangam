<?php
declare(strict_types=1);

namespace DigiSangam\Developer;

use DigiSangam\Core\Storage\JsonFileStore;

final class WebhookRepository
{
    public function __construct(private readonly JsonFileStore $store) {}
    public function all(): array { return $this->store->read('developer/webhooks.json',[]); }
    public function create(array $input): array {
        $url=trim((string)($input['url']??''));$eventId=trim((string)($input['event_id']??''));
        if($eventId===''||!filter_var($url,FILTER_VALIDATE_URL))throw new \InvalidArgumentException('Event and valid webhook URL are required.');
        $record=['id'=>'whk_'.bin2hex(random_bytes(6)),'event_id'=>$eventId,'url'=>$url,'events'=>array_values((array)($input['events']??['*'])),'secret'=>bin2hex(random_bytes(24)),'active'=>true,'created_at'=>date(DATE_ATOM)];
        $rows=$this->all();array_unshift($rows,$record);$this->store->write('developer/webhooks.json',$rows);return $record;
    }
    public function update(string $id,array $input): ?array {
        $rows=$this->all();$updated=null;foreach($rows as &$r){if(($r['id']??'')!==$id)continue;if(array_key_exists('active',$input))$r['active']=(bool)$input['active'];if(isset($input['events']))$r['events']=array_values((array)$input['events']);$r['updated_at']=date(DATE_ATOM);$updated=$r;break;}unset($r);if($updated)$this->store->write('developer/webhooks.json',$rows);return $updated;
    }
}
