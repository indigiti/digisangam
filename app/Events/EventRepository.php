<?php
declare(strict_types=1);

namespace DigiSangam\Events;

use DigiSangam\Core\Storage\JsonFileStore;

final class EventRepository
{
    private const PATH='events/index.json';
    private const FORMATS=['in_person','hybrid','virtual'];
    private const PRIVACY=['public','private','invite_only'];
    private const STATUSES=['Draft','Published','Live','Completed','Archived'];

    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read(self::PATH,[]);
    }

    public function find(string $id): ?array
    {
        foreach($this->all() as $event) if(($event['id']??'')===$id) return $event;
        return null;
    }

    public function create(array $input): array
    {
        $name=trim((string)($input['name']??''));
        if($name==='') throw new \InvalidArgumentException('Event name is required.');

        $start=trim((string)($input['start_date']??''));
        $end=trim((string)($input['end_date']??''));
        if($start!==''&&$end!==''&&$end<$start) throw new \InvalidArgumentException('End date cannot be before start date.');

        $format=(string)($input['format']??'in_person');
        $privacy=(string)($input['privacy']??'public');
        $timezone=trim((string)($input['timezone']??'Asia/Kolkata'))?:'Asia/Kolkata';
        $currency=strtoupper(trim((string)($input['currency']??'INR')));
        if(!in_array($format,self::FORMATS,true)) throw new \InvalidArgumentException('Invalid event format.');
        if(!in_array($privacy,self::PRIVACY,true)) throw new \InvalidArgumentException('Invalid event privacy mode.');
        try{new \DateTimeZone($timezone);}catch(\Throwable){throw new \InvalidArgumentException('Invalid event timezone.');}
        if(!preg_match('/^[A-Z]{3}$/',$currency)) throw new \InvalidArgumentException('Event currency must use a three-letter code.');

        $organizerEmail=strtolower(trim((string)($input['organizer']['email']??'')));
        if($organizerEmail!==''&&!filter_var($organizerEmail,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Organizer email is invalid.');

        return $this->store->transaction(self::PATH,function(array $events) use ($input,$name,$start,$end,$format,$privacy,$timezone,$currency,$organizerEmail): array {
            $id='evt_'.bin2hex(random_bytes(5));
            $slug=$this->uniqueSlug((string)($input['slug']??$name),$events);
            $record=[
                'id'=>$id,
                'name'=>$name,
                'slug'=>$slug,
                'description'=>trim((string)($input['description']??'')),
                'type'=>(string)($input['type']??'Conference'),
                'category'=>(string)($input['category']??'Business'),
                'format'=>$format,
                'start_date'=>$start,
                'end_date'=>$end,
                'date'=>$start,
                'timezone'=>$timezone,
                'location'=>trim((string)($input['location']??'')),
                'venue_name'=>trim((string)($input['venue_name']??'')),
                'website'=>trim((string)($input['website']??'')),
                'currency'=>$currency,
                'privacy'=>$privacy,
                'status'=>'Draft',
                'branding'=>[
                    'brand_name'=>trim((string)($input['branding']['brand_name']??$name)),
                    'logo_url'=>trim((string)($input['branding']['logo_url']??'')),
                    'cover_url'=>trim((string)($input['branding']['cover_url']??'')),
                    'poster_url'=>trim((string)($input['branding']['poster_url']??'')),
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
                    'email'=>$organizerEmail,
                    'phone'=>trim((string)($input['organizer']['phone']??'')),
                ],
                'registrations'=>0,
                'progress'=>45,
                'created_at'=>date(DATE_ATOM),
            ];
            $record['progress']=$this->progress($record);
            array_unshift($events,$record);
            return ['data'=>$events,'result'=>$record];
        },[]);
    }

    public function update(string $id,array $input): ?array
    {
        return $this->store->transaction(self::PATH,function(array $events) use ($id,$input): array {
            $updated=null;
            foreach($events as &$event){
                if(($event['id']??'')!==$id) continue;

                foreach(['name','description','type','category','format','start_date','end_date','timezone','location','venue_name','website','currency','privacy','status'] as $field){
                    if(array_key_exists($field,$input)) $event[$field]=is_string($input[$field])?trim((string)$input[$field]):$input[$field];
                }
                $event['name']=trim((string)($event['name']??''));
                if($event['name']==='') throw new \InvalidArgumentException('Event name is required.');
                if(!in_array((string)($event['format']??''),self::FORMATS,true)) throw new \InvalidArgumentException('Invalid event format.');
                if(!in_array((string)($event['privacy']??''),self::PRIVACY,true)) throw new \InvalidArgumentException('Invalid event privacy mode.');
                if(!in_array((string)($event['status']??''),self::STATUSES,true)) throw new \InvalidArgumentException('Invalid event status.');
                $event['currency']=strtoupper(trim((string)($event['currency']??'INR')));
                if(!preg_match('/^[A-Z]{3}$/',(string)$event['currency'])) throw new \InvalidArgumentException('Event currency must use a three-letter code.');
                $event['timezone']=trim((string)($event['timezone']??'Asia/Kolkata'))?:'Asia/Kolkata';
                try{new \DateTimeZone((string)$event['timezone']);}catch(\Throwable){throw new \InvalidArgumentException('Invalid event timezone.');}

                if(isset($input['branding'])&&is_array($input['branding'])){
                    $event['branding']=array_merge((array)($event['branding']??[]),$input['branding']);
                    foreach(['primary_color','secondary_color','background_color'] as $field){
                        if(isset($event['branding'][$field])) $event['branding'][$field]=$this->color((string)$event['branding'][$field],$field==='primary_color'?'#4f46e5':($field==='secondary_color'?'#06b6d4':'#0f172a'));
                    }
                }
                if(isset($input['public_page'])&&is_array($input['public_page'])){
                    $event['public_page']=array_merge((array)($event['public_page']??[]),$input['public_page']);
                }
                if(isset($input['organizer'])&&is_array($input['organizer'])){
                    $event['organizer']=array_merge((array)($event['organizer']??[]),$input['organizer']);
                    if(array_key_exists('email',$event['organizer'])){
                        $email=strtolower(trim((string)$event['organizer']['email']));
                        if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Organizer email is invalid.');
                        $event['organizer']['email']=$email;
                    }
                }

                if(!empty($event['start_date'])&&!empty($event['end_date'])&&$event['end_date']<$event['start_date']) throw new \InvalidArgumentException('End date cannot be before start date.');
                $event['date']=$event['start_date']??'';
                $event['progress']=$this->progress($event);
                $event['updated_at']=date(DATE_ATOM);
                $updated=$event;
                break;
            }
            unset($event);
            return ['data'=>$events,'result'=>$updated];
        },[]);
    }

    private function uniqueSlug(string $value,array $events): string
    {
        $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$value)??'','-'));
        if($slug==='') $slug='event';
        $existing=array_map(static fn(array $event): string => (string)($event['slug']??''),$events);
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
        $locationReady=($event['format']??'in_person')==='virtual'||!empty($event['location']);
        $checks=[
            !empty($event['name']),!empty($event['start_date']),$locationReady,
            !empty($event['description']),!empty($event['branding']['primary_color']),
            !empty($event['organizer']['name']),in_array($event['status']??'Draft',['Published','Live','Completed','Archived'],true),
        ];
        return (int)round(count(array_filter($checks))/count($checks)*100);
    }
}
