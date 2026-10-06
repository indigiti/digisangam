<?php
declare(strict_types=1);

namespace DigiSangam\Venue;

use DigiSangam\Core\Storage\JsonFileStore;

final class VenueRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function get(string $eventId): array
    {
        if(trim($eventId)==='') throw new \InvalidArgumentException('Event is required.');
        return $this->store->read('venue/'.$eventId.'.json', self::defaults($eventId));
    }

    public function save(string $eventId,array $input): array
    {
        if(trim($eventId)==='') throw new \InvalidArgumentException('Event is required.');
        $current=$this->get($eventId);
        foreach(['name','address'] as $field) if(array_key_exists($field,$input)) $current[$field]=trim((string)$input[$field]);
        if(isset($input['zones'])&&is_array($input['zones'])) $current['zones']=array_values($input['zones']);
        if(isset($input['seating'])&&is_array($input['seating'])) $current['seating']=array_values($input['seating']);
        $current['event_id']=$eventId;
        $current['updated_at']=date(DATE_ATOM);
        $this->store->write('venue/'.$eventId.'.json',$current);
        return $current;
    }

    private static function defaults(string $eventId): array
    {
        return [
            'event_id'=>$eventId,
            'name'=>'',
            'address'=>'',
            'zones'=>[],
            'seating'=>[],
        ];
    }
}
