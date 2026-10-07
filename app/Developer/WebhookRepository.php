<?php
declare(strict_types=1);

namespace DigiSangam\Developer;

use DigiSangam\Core\Storage\JsonFileStore;

final class WebhookRepository
{
    private const PATH='developer/webhooks.json';

    public function __construct(private readonly JsonFileStore $store) {}
    public function all(): array { return $this->store->read(self::PATH,[]); }
    public function publicList(): array { return array_map(fn(array $row): array=>$this->view($row),$this->all()); }

    public function create(array $input): array
    {
        $url=trim((string)($input['url']??''));
        $eventId=trim((string)($input['event_id']??''));
        if($eventId===''||!filter_var($url,FILTER_VALIDATE_URL)) throw new \InvalidArgumentException('Event and valid webhook URL are required.');

        $events=array_values(array_unique(array_filter(array_map('strval',(array)($input['events']??['*'])),static fn(string $value): bool => trim($value)!=='')));
        if($events===[]) $events=['*'];
        $record=[
            'id'=>'whk_'.bin2hex(random_bytes(6)),
            'event_id'=>$eventId,
            'url'=>$url,
            'events'=>$events,
            'secret'=>bin2hex(random_bytes(24)),
            'active'=>true,
            'created_at'=>date(DATE_ATOM),
        ];

        return $this->store->transaction(self::PATH,static function(array $rows) use ($record): array {
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function update(string $id,array $input): ?array
    {
        $updated=$this->store->transaction(self::PATH,static function(array $rows) use ($id,$input): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id) continue;
                if(array_key_exists('active',$input)) $row['active']=(bool)$input['active'];
                if(isset($input['events'])){
                    $events=array_values(array_unique(array_filter(array_map('strval',(array)$input['events']),static fn(string $value): bool => trim($value)!=='')));
                    $row['events']=$events===[]?['*']:$events;
                }
                if(isset($input['url'])){
                    $url=trim((string)$input['url']);
                    if(!filter_var($url,FILTER_VALIDATE_URL)) throw new \InvalidArgumentException('Valid webhook URL is required.');
                    $row['url']=$url;
                }
                $row['updated_at']=date(DATE_ATOM);
                $updated=$row;
                break;
            }
            unset($row);
            return ['data'=>$rows,'result'=>$updated];
        },[]);
        return is_array($updated)?$this->view($updated):null;
    }

    private function view(array $row): array
    {
        unset($row['secret']);
        $row['secret_configured']=true;
        return $row;
    }
}
