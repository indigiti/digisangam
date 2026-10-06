<?php
declare(strict_types=1);

namespace DigiSangam\Intelligence;

final class EventBlueprintService
{
    public function build(string $prompt): array
    {
        $prompt=trim($prompt);if($prompt==='')throw new \InvalidArgumentException('Describe the event you want to create.');
        $lower=strtolower($prompt);
        $type=str_contains($lower,'expo')?'Expo':(str_contains($lower,'award')?'Awards':(str_contains($lower,'workshop')?'Workshop':(str_contains($lower,'webinar')?'Webinar':'Conference')));
        $format=str_contains($lower,'virtual')?'virtual':(str_contains($lower,'hybrid')?'hybrid':'in_person');
        $categories=['General'];
        foreach(['VIP','Speaker','Media','Sponsor','Exhibitor'] as $category)if(str_contains($lower,strtolower($category)))$categories[]=$category;
        if($type==='Expo')foreach(['Exhibitor','Visitor'] as $c)if(!in_array($c,$categories,true))$categories[]=$c;
        $paid=preg_match('/(?:₹|rs\.?|inr|paid|ticket)/i',$prompt)===1;
        $name=$this->name($prompt,$type);
        return [
            'name'=>$name,'description'=>$prompt,'type'=>$type,'category'=>$type==='Expo'?'Trade & Business':'Business',
            'format'=>$format,'timezone'=>'Asia/Kolkata','currency'=>'INR','privacy'=>'public',
            'registration'=>[
                'title'=>$name.' Registration','approval_mode'=>str_contains($lower,'approval')?'manual':'auto','categories'=>$categories,
                'fields'=>[
                    ['id'=>'fld_name','label'=>'Full Name','type'=>'text','required'=>true,'visibility'=>'always'],
                    ['id'=>'fld_email','label'=>'Email Address','type'=>'email','required'=>true,'visibility'=>'always'],
                    ['id'=>'fld_phone','label'=>'Mobile Number','type'=>'phone','required'=>true,'visibility'=>'always'],
                    ['id'=>'fld_category','label'=>'Category','type'=>'select','required'=>true,'visibility'=>'always'],
                    ['id'=>'fld_company','label'=>'Company Name','type'=>'text','required'=>false,'visibility'=>'always'],
                ],
            ],
            'ticket'=>['enabled'=>true,'name'=>$paid?'General Admission':'Registration','price'=>0,'quantity'=>500],
            'branding'=>['brand_name'=>$name,'primary_color'=>'#4f46e5','secondary_color'=>'#06b6d4','background_color'=>'#0f172a'],
            'public_page'=>['headline'=>$name,'show_location'=>true,'show_organizer'=>true],
            'explanation'=>[
                'Event type inferred as '.$type.'.',
                'Format inferred as '.str_replace('_',' ',$format).'.',
                'Registration categories inferred from your description.',
                $paid?'A ticketed setup was detected; set the final price before publishing.':'A free registration ticket was prepared.',
            ],
        ];
    }
    private function name(string $prompt,string $type): string {
        $first=trim((string)preg_split('/[.!?\n]/',$prompt)[0]);if(strlen($first)>72)$first=substr($first,0,69).'…';
        return $first!==''?$first:('New '.$type);
    }
}
