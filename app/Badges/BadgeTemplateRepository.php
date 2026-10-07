<?php
declare(strict_types=1);

namespace DigiSangam\Badges;

use DigiSangam\Core\Storage\JsonFileStore;

final class BadgeTemplateRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('badges/templates.json', []);
    }

    public function create(array $input): array
    {
        $record=[
            'id'=>'bdg_'.bin2hex(random_bytes(6)),
            'event_id'=>trim((string)($input['event_id'] ?? '')),
            'name'=>trim((string)($input['name']??'Badge Template')),
            'width_mm'=>max(40,(int)($input['width_mm']??100)),
            'height_mm'=>max(40,(int)($input['height_mm']??140)),
            'background'=>(string)($input['background']??'#ffffff'),
            'accent'=>(string)($input['accent']??'#4f46e5'),
            'show_qr'=>(bool)($input['show_qr']??true),
            'fields'=>(array)($input['fields']??['name','company','category']),
            'category'=>(string)($input['category']??'All'),
            'created_at'=>date(DATE_ATOM),
        ];
        if($record['event_id']==='') throw new \InvalidArgumentException('Event is required.');
        if($record['name']==='') throw new \InvalidArgumentException('Badge template name is required.');
        return $this->store->transaction('badges/templates.json',static function(array $rows) use ($record): array {
            array_unshift($rows,$record);
            return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function update(string $id,array $input): ?array
    {
        return $this->store->transaction('badges/templates.json',static function(array $rows) use ($id,$input): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id) continue;
                foreach(['name','background','accent','category'] as $field) if(array_key_exists($field,$input)) $row[$field]=trim((string)$input[$field]);
                if(array_key_exists('show_qr',$input)) $row['show_qr']=(bool)$input['show_qr'];
                foreach(['width_mm','height_mm'] as $field) if(array_key_exists($field,$input)) $row[$field]=max(40,(int)$input[$field]);
                if(isset($input['fields'])&&is_array($input['fields'])) $row['fields']=array_values($input['fields']);
                if(trim((string)($row['name']??''))==='') throw new \InvalidArgumentException('Badge template name is required.');
                $row['updated_at']=date(DATE_ATOM); $updated=$row; break;
            }
            unset($row);
            return ['data'=>$rows,'result'=>$updated];
        },[]);
    }

}
