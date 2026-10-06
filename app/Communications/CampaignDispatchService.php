<?php
declare(strict_types=1);

namespace DigiSangam\Communications;

use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Notifications\NotificationOutbox;

final class CampaignDispatchService
{
    public function __construct(
        private readonly CampaignRepository $campaigns,
        private readonly AttendeeRepository $attendees,
        private readonly NotificationOutbox $outbox,
    ) {}

    public function dispatch(string $campaignId): array
    {
        $campaign=null;
        foreach($this->campaigns->all() as $row) if(($row['id']??'')===$campaignId){$campaign=$row;break;}
        if(!$campaign) throw new \RuntimeException('Campaign not found.');
        if(!in_array((string)$campaign['channel'],['email','whatsapp','sms'],true)) throw new \InvalidArgumentException('Unsupported campaign channel.');
        if(in_array((string)($campaign['status']??''),['queued','scheduled','sent'],true)) throw new \RuntimeException('Campaign has already been queued.');

        $notBefore='';
        $scheduled=false;
        $scheduleAt=trim((string)($campaign['schedule_at']??''));
        if($scheduleAt!==''){
            try{
                $local=new \DateTimeImmutable($scheduleAt,new \DateTimeZone((string)($campaign['timezone']??'UTC')));
            }catch(\Throwable){
                throw new \InvalidArgumentException('Campaign schedule is invalid.');
            }
            if($local->getTimestamp()>time()){
                $notBefore=$local->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM);
                $scheduled=true;
            }
        }

        $recipients=$this->segment((array)($campaign['segment']??[]),(string)$campaign['event_id']);
        $queued=0;$skipped=0;
        foreach($recipients as $attendee){
            $recipient=$campaign['channel']==='email'
                ? ['email'=>(string)($attendee['email']??'')]
                : ['phone'=>(string)($attendee['phone']??'')];
            if(trim((string)array_values($recipient)[0])===''){ $skipped++; continue; }

            $this->outbox->queue(
                (string)$campaign['channel'],
                (string)($campaign['template']??'custom_campaign'),
                $recipient,
                [
                    'event_id'=>$campaign['event_id'],
                    'attendee_id'=>$attendee['id']??'',
                    'campaign_id'=>$campaign['id'],
                    'subject'=>$campaign['subject']??'',
                    'content'=>$campaign['content']??'',
                    'confirmation_token'=>$attendee['confirmation_token']??'',
                ],
                $notBefore
            );
            $queued++;
        }

        $updated=$this->campaigns->update($campaignId,[
            'status'=>$scheduled?'scheduled':'queued',
            'queued_count'=>$queued,
            'sent_count'=>0,
            'simulated_count'=>0,
            'failed_count'=>0,
            'skipped_count'=>$skipped,
        ]);

        return ['campaign'=>$updated??$campaign,'matched'=>count($recipients),'queued'=>$queued,'skipped'=>$skipped,'not_before'=>$notBefore];
    }

    private function segment(array $segment,string $eventId): array
    {
        return array_values(array_filter($this->attendees->all(),static function(array $row) use ($segment,$eventId): bool {
            if(($row['event_id']??'')!==$eventId) return false;
            foreach(['status','category','company'] as $key){
                if(!empty($segment[$key]) && ($row[$key]??'')!==$segment[$key]) return false;
            }
            return true;
        }));
    }
}
