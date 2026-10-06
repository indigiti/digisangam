<?php
declare(strict_types=1);

namespace DigiSangam\Printing;

use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Badges\BadgeTemplateRepository;
use DigiSangam\Badges\PrintJobRepository;

final class BadgePrintWorker
{
    public function __construct(private readonly PrintJobRepository $jobs,private readonly AttendeeRepository $attendees,private readonly BadgeTemplateRepository $templates,private readonly PrintProviderInterface $provider) {}

    public function run(int $limit=20): array
    {
        $processed=0;$sent=0;$simulated=0;$failed=0;
        foreach($this->jobs->all() as $job){
            if($processed>=$limit)break;if(($job['status']??'')!=='queued')continue;$processed++;
            try{
                $attendee=$this->attendees->find((string)$job['attendee_id']);if(!$attendee)throw new \RuntimeException('Attendee not found.');
                $template=null;foreach($this->templates->all() as $t)if(($t['id']??'')===($job['template_id']??'')){$template=$t;break;}
                if(!$template)throw new \RuntimeException('Badge template not found.');
                $zpl=$this->zpl($attendee,$template);
                $result=$this->provider->send($job,$zpl);
                $status=!empty($result['simulated'])?'simulated':'printed';$this->jobs->update((string)$job['id'],['status'=>$status,'provider'=>$result['provider']??'unknown','provider_job_id'=>$result['id']??'']);
                !empty($result['simulated'])?$simulated++:$sent++;
            }catch(\Throwable $e){$this->jobs->update((string)$job['id'],['status'=>'failed','last_error'=>$e->getMessage()]);$failed++;}
        }
        return ['processed'=>$processed,'printed'=>$sent,'simulated'=>$simulated,'failed'=>$failed];
    }

    private function zpl(array $a,array $t): string {
        $clean=static fn($v)=>preg_replace('/[\^~]/','',(string)$v)??'';
        return "^XA^PW800^LL1000^FO60,70^A0N,55,55^FD".$clean($a['name']??'')."^FS^FO60,150^A0N,32,32^FD".$clean($a['company']??'')."^FS^FO60,205^A0N,28,28^FD".$clean($a['category']??'')."^FS^FO60,300^BQN,2,8^FDLA,".$clean($a['id']??'')."^FS^XZ";
    }
}
