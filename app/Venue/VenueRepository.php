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
        return $this->store->read('venue/'.$eventId.'.json',self::defaults($eventId));
    }

    public function save(string $eventId,array $input): array
    {
        $eventId=trim($eventId);
        if($eventId==='') throw new \InvalidArgumentException('Event is required.');
        $current=$this->get($eventId);
        foreach(['name','address'] as $field) if(array_key_exists($field,$input)) $current[$field]=trim((string)$input[$field]);
        if(isset($input['zones'])){
            if(!is_array($input['zones'])) throw new \InvalidArgumentException('Venue zones must be an array.');
            $current['zones']=$this->validateZones($input['zones']);
        }
        if(isset($input['seating'])){
            if(!is_array($input['seating'])) throw new \InvalidArgumentException('Venue seating must be an array.');
            $current['seating']=$this->validateSeating($input['seating']);
        }
        $current['event_id']=$eventId;
        $current['updated_at']=date(DATE_ATOM);
        $this->store->write('venue/'.$eventId.'.json',$current);
        return $current;
    }

    private function validateZones(array $zones): array
    {
        $out=[];$ids=[];
        foreach($zones as $index=>$zone){
            if(!is_array($zone)) throw new \InvalidArgumentException('Invalid zone at position '.($index+1).'.');
            $id=preg_replace('/[^a-zA-Z0-9_-]/','',trim((string)($zone['id']??'')))??'';
            $name=trim((string)($zone['name']??''));
            if($id===''||$name==='') throw new \InvalidArgumentException('Every zone needs an ID and name.');
            if(in_array($id,$ids,true)) throw new \InvalidArgumentException('Zone IDs must be unique.');
            $capacity=max(0,(int)($zone['capacity']??0));
            $categories=[];
            foreach((array)($zone['categories']??[]) as $category){
                $value=trim((string)$category);
                if($value!==''&&!in_array($value,$categories,true)) $categories[]=$value;
            }
            $out[]=['id'=>$id,'name'=>$name,'capacity'=>$capacity,'categories'=>$categories];
            $ids[]=$id;
        }
        return $out;
    }

    private function validateSeating(array $seating): array
    {
        $out=[];$ids=[];
        foreach($seating as $index=>$hall){
            if(!is_array($hall)) throw new \InvalidArgumentException('Invalid hall at position '.($index+1).'.');
            $id=preg_replace('/[^a-zA-Z0-9_-]/','',trim((string)($hall['id']??'')))??'';
            $name=trim((string)($hall['name']??''));
            $type=(string)($hall['type']??'general');
            if($id===''||$name==='') throw new \InvalidArgumentException('Every hall needs an ID and name.');
            if(in_array($id,$ids,true)) throw new \InvalidArgumentException('Hall IDs must be unique.');
            if(!in_array($type,['general','reserved'],true)) throw new \InvalidArgumentException('Invalid hall seating type.');
            $record=['id'=>$id,'name'=>$name,'type'=>$type];
            if($type==='reserved'){
                $rows=(int)($hall['rows']??0);
                $seats=(int)($hall['seats_per_row']??0);
                if($rows<1||$seats<1) throw new \InvalidArgumentException($name.' requires rows and seats per row.');
                $record['rows']=$rows;$record['seats_per_row']=$seats;$record['capacity']=$rows*$seats;
            }else{
                $record['capacity']=max(0,(int)($hall['capacity']??0));
            }
            $out[]=$record;$ids[]=$id;
        }
        return $out;
    }

    private static function defaults(string $eventId): array
    {
        return ['event_id'=>$eventId,'name'=>'','address'=>'','zones'=>[],'seating'=>[]];
    }
}
