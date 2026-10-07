<?php
declare(strict_types=1);

namespace DigiSangam\Registration;

use DigiSangam\Core\Storage\JsonFileStore;

final class RegistrationRepository
{
    private const TYPES=['text','email','phone','select','dropdown','multiselect','radio','date','file','paragraph','textarea'];
    private const APPROVAL_MODES=['auto','manual','invite_only'];
    private const OPERATORS=['equals','not_equals','contains'];

    public function __construct(private readonly JsonFileStore $store) {}

    public function schema(string $eventId): array
    {
        $eventId=trim($eventId);
        if($eventId==='') throw new \InvalidArgumentException('Event is required.');
        return $this->store->read('registration/'.$eventId.'.json',[
            'event_id'=>$eventId,
            'title'=>'Event Registration',
            'approval_mode'=>'auto',
            'categories'=>['General'],
            'fields'=>[
                ['id'=>'fld_name','label'=>'Full Name','type'=>'text','required'=>true,'visibility'=>'always'],
                ['id'=>'fld_email','label'=>'Email Address','type'=>'email','required'=>true,'visibility'=>'always'],
                ['id'=>'fld_phone','label'=>'Mobile Number','type'=>'phone','required'=>false,'visibility'=>'always'],
                ['id'=>'fld_category','label'=>'Category','type'=>'select','required'=>true,'visibility'=>'always'],
                ['id'=>'fld_company','label'=>'Company Name','type'=>'text','required'=>false,'visibility'=>'always'],
            ],
            'updated_at'=>date(DATE_ATOM),
        ]);
    }

    public function save(string $eventId,array $input): array
    {
        $eventId=trim($eventId);
        if($eventId==='') throw new \InvalidArgumentException('Event is required.');

        $schema=$this->schema($eventId);
        foreach(['title','approval_mode','categories','fields'] as $key){
            if(array_key_exists($key,$input)) $schema[$key]=$input[$key];
        }

        $schema['title']=trim((string)($schema['title']??''));
        if($schema['title']==='') throw new \InvalidArgumentException('Registration title is required.');

        $schema['approval_mode']=(string)($schema['approval_mode']??'auto');
        if(!in_array($schema['approval_mode'],self::APPROVAL_MODES,true)) throw new \InvalidArgumentException('Invalid approval mode.');

        $categories=[];
        foreach((array)($schema['categories']??[]) as $category){
            $value=trim((string)$category);
            if($value!==''&&!in_array($value,$categories,true)) $categories[]=$value;
        }
        if($categories===[]) throw new \InvalidArgumentException('At least one attendee category is required.');
        $schema['categories']=$categories;

        $fields=$this->validateFields((array)($schema['fields']??[]));
        $ids=array_column($fields,'id');
        foreach(['fld_name','fld_email','fld_category'] as $requiredId){
            if(!in_array($requiredId,$ids,true)) throw new \InvalidArgumentException('Required system field '.$requiredId.' is missing.');
        }
        $schema['fields']=$fields;
        $schema['event_id']=$eventId;
        $schema['updated_at']=date(DATE_ATOM);
        $this->store->write('registration/'.$eventId.'.json',$schema);
        return $schema;
    }

    private function validateFields(array $fields): array
    {
        if($fields===[]) throw new \InvalidArgumentException('Registration form must contain fields.');
        if(count($fields)>100) throw new \InvalidArgumentException('Registration form cannot exceed 100 fields.');

        $out=[];$ids=[];
        foreach($fields as $index=>$field){
            if(!is_array($field)) throw new \InvalidArgumentException('Invalid registration field at position '.($index+1).'.');
            $id=preg_replace('/[^a-zA-Z0-9_-]/','',trim((string)($field['id']??'')))??'';
            $label=trim((string)($field['label']??''));
            $type=(string)($field['type']??'text');
            if($id===''||$label==='') throw new \InvalidArgumentException('Every registration field needs an ID and label.');
            if(in_array($id,$ids,true)) throw new \InvalidArgumentException('Registration field IDs must be unique.');
            if(!in_array($type,self::TYPES,true)) throw new \InvalidArgumentException('Unsupported registration field type: '.$type.'.');

            $required=(bool)($field['required']??false);
            if(in_array($id,['fld_name','fld_email','fld_category'],true)) $required=true;

            $visibility=(string)($field['visibility']??'always');
            if(!in_array($visibility,['always','conditional'],true)) $visibility='always';

            $clean=[
                'id'=>$id,
                'label'=>$label,
                'type'=>$type,
                'required'=>$required,
                'visibility'=>$visibility,
            ];
            if(isset($field['help'])) $clean['help']=trim((string)$field['help']);

            if(in_array($type,['select','dropdown','multiselect','radio'],true)&&$id!=='fld_category'){
                $options=[];
                foreach((array)($field['options']??[]) as $option){
                    $value=trim((string)$option);
                    if($value!==''&&!in_array($value,$options,true)) $options[]=$value;
                }
                if($options===[]) throw new \InvalidArgumentException($label.' needs at least one option.');
                $clean['options']=$options;
            }

            if($visibility==='conditional'){
                $condition=(array)($field['condition']??[]);
                $source=(string)($condition['field']??'');
                $operator=(string)($condition['operator']??'equals');
                if($source===''||$source===$id) throw new \InvalidArgumentException($label.' needs a valid conditional source field.');
                if(!in_array($operator,self::OPERATORS,true)) throw new \InvalidArgumentException($label.' has an invalid conditional operator.');
                $clean['condition']=['field'=>$source,'operator'=>$operator,'value'=>$condition['value']??''];
            }

            $ids[]=$id;
            $out[]=$clean;
        }

        foreach($out as $field){
            if(($field['visibility']??'always')==='conditional'&&!in_array((string)$field['condition']['field'],$ids,true)){
                throw new \InvalidArgumentException($field['label'].' references a missing conditional source field.');
            }
        }
        return $out;
    }
}
