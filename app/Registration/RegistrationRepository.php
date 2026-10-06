<?php
declare(strict_types=1);

namespace DigiSangam\Registration;

use DigiSangam\Core\Storage\JsonFileStore;

final class RegistrationRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function schema(string $eventId = 'evt_001'): array
    {
        return $this->store->read('registration/' . $eventId . '.json', [
            'event_id'=>$eventId,
            'title'=>'Event Registration',
            'approval_mode'=>'auto',
            'categories'=>['General','VIP','Speaker','Sponsor','Media'],
            'fields'=>[
                ['id'=>'fld_name','label'=>'Full Name','type'=>'text','required'=>true,'visibility'=>'always'],
                ['id'=>'fld_email','label'=>'Email Address','type'=>'email','required'=>true,'visibility'=>'always'],
                ['id'=>'fld_phone','label'=>'Mobile Number','type'=>'phone','required'=>true,'visibility'=>'always'],
                ['id'=>'fld_category','label'=>'Category','type'=>'select','required'=>true,'visibility'=>'always'],
                ['id'=>'fld_company','label'=>'Company Name','type'=>'text','required'=>false,'visibility'=>'always'],
            ],
            'updated_at'=>date(DATE_ATOM),
        ]);
    }

    public function save(string $eventId, array $input): array
    {
        $schema = $this->schema($eventId);
        foreach (['title','approval_mode','categories','fields'] as $key) {
            if (array_key_exists($key, $input)) $schema[$key] = $input[$key];
        }
        $schema['event_id'] = $eventId;
        $schema['updated_at'] = date(DATE_ATOM);
        $this->store->write('registration/' . $eventId . '.json', $schema);
        return $schema;
    }
}
