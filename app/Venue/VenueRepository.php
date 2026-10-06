<?php
declare(strict_types=1);

namespace DigiSangam\Venue;

use DigiSangam\Core\Storage\JsonFileStore;

final class VenueRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function get(string $eventId='evt_001'): array
    {
        return $this->store->read('venue/'.$eventId.'.json', self::defaults($eventId));
    }

    public function save(string $eventId,array $input): array
    {
        $current=$this->get($eventId);
        foreach(['name','address'] as $field) if(array_key_exists($field,$input)) $current[$field]=(string)$input[$field];
        if(isset($input['zones'])&&is_array($input['zones'])) $current['zones']=$input['zones'];
        if(isset($input['seating'])&&is_array($input['seating'])) $current['seating']=$input['seating'];
        $current['event_id']=$eventId; $current['updated_at']=date(DATE_ATOM);
        $this->store->write('venue/'.$eventId.'.json',$current);
        return $current;
    }

    private static function defaults(string $eventId): array
    {
        return [
            'event_id'=>$eventId,'name'=>'Convention Centre','address'=>'Mumbai, India',
            'zones'=>[
                ['id'=>'zone_general','name'=>'General Access','capacity'=>1500,'categories'=>['General','Media']],
                ['id'=>'zone_vip','name'=>'VIP Lounge','capacity'=>150,'categories'=>['VIP','Speaker','Sponsor']],
                ['id'=>'zone_stage','name'=>'Backstage','capacity'=>60,'categories'=>['Speaker','Sponsor']],
            ],
            'seating'=>[
                ['id'=>'hall_main','name'=>'Main Hall','type'=>'reserved','rows'=>20,'seats_per_row'=>30],
                ['id'=>'hall_a','name'=>'Hall A','type'=>'general','capacity'=>250],
            ],
        ];
    }
}
