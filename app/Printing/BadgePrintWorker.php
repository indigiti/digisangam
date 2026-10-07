<?php
declare(strict_types=1);

namespace DigiSangam\Printing;

use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Badges\BadgeTemplateRepository;
use DigiSangam\Badges\PrintJobRepository;
use DigiSangam\Credentials\CredentialService;

final class BadgePrintWorker
{
    public function __construct(
        private readonly PrintJobRepository $jobs,
        private readonly AttendeeRepository $attendees,
        private readonly BadgeTemplateRepository $templates,
        private readonly PrintProviderInterface $provider,
        private readonly CredentialService $credentials,
    ) {}

    public function run(int $limit=20): array
    {
        $processed=0;$sent=0;$simulated=0;$failed=0;
        foreach($this->jobs->all() as $job){
            if($processed>=$limit) break;
            if(($job['status']??'')!=='queued') continue;
            $processed++;
            try{
                $attendee=$this->attendees->find((string)$job['attendee_id']);
                if(!$attendee) throw new \RuntimeException('Attendee not found.');

                $template=null;
                foreach($this->templates->all() as $candidate){
                    if(($candidate['id']??'')===($job['template_id']??'')){$template=$candidate;break;}
                }
                if(!$template) throw new \RuntimeException('Badge template not found.');
                if(($template['event_id']??'')!==($attendee['event_id']??'') || ($job['event_id']??'')!==($attendee['event_id']??'')){
                    throw new \RuntimeException('Badge job, attendee and template must belong to the same event.');
                }

                $zpl=$this->zpl($attendee,$template,$job);
                $result=$this->provider->send($job,$zpl);
                $status=!empty($result['simulated'])?'simulated':'printed';
                $this->jobs->update((string)$job['id'],[
                    'status'=>$status,
                    'provider'=>$result['provider']??'unknown',
                    'provider_job_id'=>$result['id']??'',
                ]);
                !empty($result['simulated'])?$simulated++:$sent++;
            }catch(\Throwable $e){
                $this->jobs->update((string)$job['id'],['status'=>'failed','last_error'=>$e->getMessage()]);
                $failed++;
            }
        }
        return ['processed'=>$processed,'printed'=>$sent,'simulated'=>$simulated,'failed'=>$failed];
    }

    private function zpl(array $attendee,array $template,array $job): string
    {
        $clean=static fn(mixed $value): string => preg_replace('/[\^~]/','',(string)$value)??'';
        $width=max(320,(int)($template['width_mm']??100)*8);
        $height=max(320,(int)($template['height_mm']??140)*8);
        $copies=max(1,min(10,(int)($job['copies']??1)));
        $fields=array_values(array_unique(array_map('strval',(array)($template['fields']??['name','company','category']))));
        $values=[
            'name'=>(string)($attendee['name']??''),
            'company'=>(string)($attendee['company']??''),
            'category'=>(string)($attendee['category']??''),
            'email'=>(string)($attendee['email']??''),
            'phone'=>(string)($attendee['phone']??''),
        ];

        $zpl="^XA^PW{$width}^LL{$height}";
        $y=70;
        foreach($fields as $field){
            if(!array_key_exists($field,$values)) continue;
            $font=$field==='name'?55:30;
            $zpl.="^FO60,{$y}^A0N,{$font},{$font}^FD".$clean($values[$field])."^FS";
            $y+=$field==='name'?80:52;
        }

        if(!empty($template['show_qr'])){
            $eventId=(string)($attendee['event_id']??'');
            if($eventId==='') throw new \RuntimeException('Attendee event is required for badge credential.');
            $credential=$this->credentials->issue((string)$attendee['id'],$eventId);
            $qrY=max($y+35,300);
            $zpl.="^FO60,{$qrY}^BQN,2,8^FDLA,".$clean((string)$credential['payload'])."^FS";
        }

        return $zpl."^PQ{$copies}^XZ";
    }
}
