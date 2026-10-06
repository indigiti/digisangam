<?php
declare(strict_types=1);

namespace DigiSangam\Events;

use DigiSangam\Core\Storage\JsonFileStore;

final class EventRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('events/index.json', []);
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $event) if (($event['id'] ?? '') === $id) return $event;
        return null;
    }

    public function create(array $input): array
    {
        $name=trim((string)($input['name']??''));
        if($name==='') throw new \InvalidArgumentException('Event name is required.');

        $start=(string)($input['start_date']??'');
        $end=(string)($input['end_date']??'');
        if($start!=='' && $end!=='' && $end<$start) throw new \InvalidArgumentException('End date cannot be before start date.');

        $events=$this->all();
        $id='evt_'.bin2hex(random_bytes(5));
        $slug=$this->uniqueSlug((string)($input['slug']??$name),$events);

        $record=[
            'id'=>$id,
            'name'=>$name,
            'slug'=>$slug,
            'description'=>trim((string)($input['description']??'')),
            'type'=>(string)($input['type']??'Conference'),
            'category'=>(string)($input['category']??'Business'),
            'format'=>(string)($input['format']??'in_person'),
            'start_date'=>$start,
            'end_date'=>$end,
            'date'=>$start,
            'timezone'=>(string)($input['timezone']??'Asia/Kolkata'),
            'location'=>trim((string)($input['location']??'')),
            'venue_name'=>trim((string)($input['venue_name']??'')),
            'website'=>trim((string)($input['website']??'')),
            'currency'=>strtoupper((string)($input['currency']??'INR')),
            'privacy'=>(string)($input['privacy']??'public'),
            'status'=>'Draft',
            'branding'=>[
                'brand_name'=>trim((string)($input['branding']['brand_name']??$name)),
                'logo_url'=>trim((string)($input['branding']['logo_url']??'')),
                'cover_url'=>trim((string)($input['branding']['cover_url']??'')),
                'primary_color'=>$this->color((string)($input['branding']['primary_color']??'#4f46e5'),'#4f46e5'),
                'secondary_color'=>$this->color((string)($input['branding']['secondary_color']??'#06b6d4'),'#06b6d4'),
                'background_color'=>$this->color((string)($input['branding']['background_color']??'#0f172a'),'#0f172a'),
            ],
            'public_page'=>[
                'headline'=>trim((string)($input['public_page']['headline']??'')),
                'show_location'=>(bool)($input['public_page']['show_location']??true),
                'show_organizer'=>(bool)($input['public_page']['show_organizer']??true),
            ],
            'organizer'=>[
                'name'=>trim((string)($input['organizer']['name']??'')),
                'email'=>strtolower(trim((string)($input['organizer']['email']??''))),
                'phone'=>trim((string)($input['organizer']['phone']??'')),
            ],
            'registrations'=>0,
            'progress'=>45,
            'created_at'=>date(DATE_ATOM),
        ];
        array_unshift($events,$record);
        $this->store->write('events/index.json',$events);
        return $record;
    }

    public function update(string $id,array $input): ?array
    {
        $events=$this->all(); $updated=null;
        foreach($events as &$event){
            if(($event['id']??'')!==$id) continue;

            foreach(['name','description','type','category','format','start_date','end_date','timezone','location','venue_name','website','currency','privacy','status'] as $field){
                if(array_key_exists($field,$input)) $event[$field]=is_string($input[$field])?trim((string)$input[$field]):$input[$field];
            }
            if(isset($input['branding'])&&is_array($input['branding'])){
                $event['branding']=array_merge((array)($event['branding']??[]),$input['branding']);
                foreach(['primary_color','secondary_color','background_color'] as $field){
                    if(isset($event['branding'][$field])) $event['branding'][$field]=$this->color((string)$event['branding'][$field],$field==='primary_color'?'#4f46e5':($field==='secondary_color'?'#06b6d4':'#0f172a'));
                }
            }
            if(isset($input['public_page'])&&is_array($input['public_page'])) $event['public_page']=array_merge((array)($event['public_page']??[]),$input['public_page']);
            if(isset($input['organizer'])&&is_array($input['organizer'])) $event['organizer']=array_merge((array)($event['organizer']??[]),$input['organizer']);

            if(!empty($event['start_date'])&&!empty($event['end_date'])&&$event['end_date']<$event['start_date']) throw new \InvalidArgumentException('End date cannot be before start date.');
            $event['date']=$event['start_date']??'';
            $event['progress']=$this->progress($event);
            $event['updated_at']=date(DATE_ATOM);
            $updated=$event;
            break;
        }
        unset($event);
        if($updated!==null) $this->store->write('events/index.json',$events);
        return $updated;
    }

    private function uniqueSlug(string $value,array $events): string
    {
        $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$value)??'','-'));
        if($slug==='') $slug='event';
        $existing=array_map(static fn(array $e): string => (string)($e['slug']??''),$events);
        $base=$slug;$n=2;
        while(in_array($slug,$existing,true)) $slug=$base.'-'.$n++;
        return $slug;
    }

    private function color(string $value,string $fallback): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/',$value)?strtolower($value):$fallback;
    }

    private function progress(array $event): int
    {
        $checks=[
            !empty($event['name']),!empty($event['start_date']),!empty($event['location']),
            !empty($event['description']),!empty($event['branding']['primary_color']),
            !empty($event['organizer']['name']),in_array($event['status']??'Draft',['Published','Live'],true),
        ];
        return (int)round(count(array_filter($checks))/count($checks)*100);
    }
}
