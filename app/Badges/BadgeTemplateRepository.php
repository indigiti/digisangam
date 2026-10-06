<?php
declare(strict_types=1);

namespace DigiSangam\Badges;

use DigiSangam\Core\Storage\JsonFileStore;

final class BadgeTemplateRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('badges/templates.json', self::defaults());
    }

    public function create(array $input): array
    {
        $record=[
            'id'=>'bdg_'.bin2hex(random_bytes(6)),
            'event_id'=>(string)($input['event_id']??'evt_001'),
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
        $rows=$this->all(); array_unshift($rows,$record);
        $this->store->write('badges/templates.json',$rows);
        return $record;
    }

    public function update(string $id,array $input): ?array
    {
        $rows=$this->all(); $updated=null;
        foreach($rows as &$row){
            if(($row['id']??'')!==$id) continue;
            foreach(['name','background','accent','category','show_qr','width_mm','height_mm'] as $field) if(array_key_exists($field,$input)) $row[$field]=$input[$field];
            if(isset($input['fields'])&&is_array($input['fields'])) $row['fields']=$input['fields'];
            $row['updated_at']=date(DATE_ATOM); $updated=$row; break;
        }
        unset($row);
        if($updated!==null) $this->store->write('badges/templates.json',$rows);
        return $updated;
    }

    private static function defaults(): array
    {
        return [[
            'id'=>'bdg_default','event_id'=>'evt_001','name'=>'Standard Event Badge',
            'width_mm'=>100,'height_mm'=>140,'background'=>'#ffffff','accent'=>'#4f46e5',
            'show_qr'=>true,'fields'=>['name','company','category'],'category'=>'All',
        ]];
    }
}
