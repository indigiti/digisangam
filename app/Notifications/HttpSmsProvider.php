<?php
declare(strict_types=1);
namespace DigiSangam\Notifications;
final class HttpSmsProvider implements SmsProviderInterface {
    public function __construct(private readonly string $endpoint,private readonly string $token='',private readonly string $sender='') {}
    public function send(string $to,string $message): array {
        if($this->endpoint===''||$to===''||$message==='')throw new \InvalidArgumentException('SMS provider configuration, recipient and message are required.');
        if(!function_exists('curl_init'))throw new \RuntimeException('cURL is required for HTTP SMS.');
        $payload=['to'=>$to,'message'=>$message];if($this->sender!=='')$payload['sender']=$this->sender;
        $headers=['Content-Type: application/json'];if($this->token!=='')$headers[]='Authorization: Bearer '.$this->token;
        $ch=curl_init($this->endpoint);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR)]);
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
        if($raw===false||$error!==''||$status<200||$status>=300)throw new \RuntimeException('SMS provider request failed'.($error!==''?': '.$error:' (HTTP '.$status.')'));
        $decoded=json_decode((string)$raw,true);
        return ['provider'=>'http','id'=>(string)($decoded['id']??$decoded['message_id']??bin2hex(random_bytes(6))),'response'=>$decoded??$raw];
    }
}
