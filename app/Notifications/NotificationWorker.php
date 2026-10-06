<?php
declare(strict_types=1);

namespace DigiSangam\Notifications;

use DigiSangam\Communications\CampaignRepository;

final class NotificationWorker
{
    public function __construct(
        private readonly NotificationOutbox $outbox,
        private readonly NotificationTemplateRenderer $templates,
        private readonly EmailProviderInterface $email,
        private readonly WhatsAppProviderInterface $whatsapp,
        private readonly ?CampaignRepository $campaigns=null,
    ) {}

    public function run(int $limit=25): array
    {
        $processed=0;$sent=0;$simulated=0;$failed=0;
        foreach($this->outbox->pending($limit) as $message){
            $processed++;
            try{
                $template=$this->templates->render((string)$message['template'],(array)($message['data']??[]));
                if(($message['channel']??'')==='email'){
                    $to=(string)($message['recipient']['email']??'');
                    $result=$this->email->send($to,(string)$template['subject'],(string)$template['html'],(string)$template['text']);
                }elseif(($message['channel']??'')==='whatsapp'){
                    $to=(string)($message['recipient']['phone']??'');
                    $result=$this->whatsapp->sendTemplate($to,(string)$template['whatsapp_template'],(array)$template['whatsapp_parameters']);
                }else{
                    throw new \RuntimeException('Unsupported notification channel.');
                }

                $deliveryStatus=(($result['provider']??'')==='log')?'simulated':'sent';
                $this->outbox->mark((string)$message['id'],$deliveryStatus,[
                    'provider'=>$result['provider']??'unknown',
                    'provider_message_id'=>$result['id']??'',
                    'sent_at'=>date(DATE_ATOM),
                ]);
                if($deliveryStatus==='simulated') $simulated++; else $sent++;
            }catch(\Throwable $e){
                $attempts=(int)($message['attempts']??0)+1;
                $this->outbox->mark((string)$message['id'],$attempts>=5?'failed':'retry',[
                    'last_error'=>$e->getMessage(),
                    'next_attempt_at'=>date(DATE_ATOM,time()+min(3600,60*(2**min($attempts,5)))),
                ]);
                $failed++;
            }
            $this->reconcileCampaign((string)($message['data']['campaign_id']??''));
        }
        return ['processed'=>$processed,'sent'=>$sent,'simulated'=>$simulated,'failed'=>$failed];
    }

    private function reconcileCampaign(string $campaignId): void
    {
        if($campaignId===''||$this->campaigns===null) return;
        $summary=$this->outbox->campaignSummary($campaignId);
        $update=[
            'queued_count'=>$summary['total'],
            'sent_count'=>$summary['sent'],
            'simulated_count'=>$summary['simulated'],
            'failed_count'=>$summary['failed'],
        ];
        if($summary['pending']===0){
            if($summary['failed']>0) $update['status']='failed';
            elseif($summary['simulated']>0) $update['status']='simulated';
            else $update['status']='sent';
        }
        $this->campaigns->update($campaignId,$update);
    }
}
