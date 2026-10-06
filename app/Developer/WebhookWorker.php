<?php
declare(strict_types=1);

namespace DigiSangam\Developer;

final class WebhookWorker
{
    public function __construct(private readonly WebhookOutboxRepository $outbox) {}
    public function run(int $limit=25): array {
        $processed=0;$sent=0;$failed=0;
        foreach($this->outbox->pending($limit) as $row){$processed++;
            try{
                if(!function_exists('curl_init'))throw new \RuntimeException('cURL is required for webhooks.');
                $payload=json_encode(['id'=>$row['id'],'event'=>$row['event'],'occurred_at'=>$row['created_at'],'data'=>$row['data']],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
                $signature=hash_hmac('sha256',$payload,(string)$row['secret']);
                $ch=curl_init((string)$row['url']);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-DigiSangam-Signature: sha256='.$signature],CURLOPT_POSTFIELDS=>$payload]);
                curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
                if($error!==''||$status<200||$status>=300)throw new \RuntimeException($error!==''?$error:'Webhook HTTP '.$status);
                $this->outbox->mark((string)$row['id'],'sent',['sent_at'=>date(DATE_ATOM),'http_status'=>$status]);$sent++;
            }catch(\Throwable $e){$this->outbox->mark((string)$row['id'],((int)($row['attempts']??0)+1)>=5?'failed':'retry',['last_error'=>$e->getMessage()]);$failed++;}
        }
        return ['processed'=>$processed,'sent'=>$sent,'failed'=>$failed];
    }
}
