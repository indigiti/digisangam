<?php
declare(strict_types=1);

namespace DigiSangam\Notifications;

final class MetaWhatsAppProvider implements WhatsAppProviderInterface
{
    public function __construct(
        private readonly string $phoneNumberId,
        private readonly string $accessToken,
        private readonly string $language='en',
        private readonly string $graphVersion='',
    ) {}

    public function sendTemplate(string $to,string $template,array $parameters=[]): array
    {
        if($this->phoneNumberId===''||$this->accessToken==='') throw new \RuntimeException('WhatsApp Cloud API is not configured.');
        if(!function_exists('curl_init')) throw new \RuntimeException('PHP cURL extension is required for WhatsApp.');
        $to=preg_replace('/\D+/','',$to) ?? '';
        if($to==='') throw new \InvalidArgumentException('Invalid WhatsApp recipient.');

        $components=[];
        if($parameters!==[]){
            $components[]=[
                'type'=>'body',
                'parameters'=>array_map(static fn($value): array => ['type'=>'text','text'=>(string)$value],array_values($parameters)),
            ];
        }
        $payload=[
            'messaging_product'=>'whatsapp',
            'to'=>$to,
            'type'=>'template',
            'template'=>[
                'name'=>$template,
                'language'=>['code'=>$this->language],
                'components'=>$components,
            ],
        ];

        $base='https://graph.facebook.com/';
        if($this->graphVersion!=='') $base.=trim($this->graphVersion,'/').'/';
        $ch=curl_init($base.$this->phoneNumberId.'/messages');
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->accessToken,'Content-Type: application/json'],
            CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
        ]);
        $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $error=curl_error($ch); curl_close($ch);
        if($raw===false||$error!=='') throw new \RuntimeException('WhatsApp connection failed.');
        $decoded=json_decode((string)$raw,true);
        if($status<200||$status>=300||!is_array($decoded)) throw new \RuntimeException((string)($decoded['error']['message']??'WhatsApp API request failed.'));
        $id=(string)($decoded['messages'][0]['id']??'');
        if($id==='') throw new \RuntimeException((string)($decoded['error']['message']??'WhatsApp API did not return a message ID.'));
        return ['provider'=>'meta','id'=>$id];
    }
}
