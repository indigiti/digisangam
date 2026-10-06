<?php
declare(strict_types=1);

namespace DigiSangam\Wallet;

final class WalletPassService
{
    public function __construct(private readonly WalletPassRepository $passes,private readonly string $secret) {}

    public function issue(array $event,array $attendee,string $credentialPayload,string $platform): array
    {
        if(!in_array($platform,['apple','google'],true)) throw new \InvalidArgumentException('Unsupported wallet platform.');
        foreach($this->passes->all() as $row){
            if(($row['event_id']??'')===($event['id']??'')&&($row['attendee_id']??'')===($attendee['id']??'')&&($row['platform']??'')===$platform)return $row;
        }
        $id='wlt_'.bin2hex(random_bytes(6));
        $token=rtrim(strtr(base64_encode(hash_hmac('sha256',$id.'|'.($attendee['id']??''),$this->secret,true)),'+/','-_'),'=');
        $configured=$platform==='apple'
            ? trim((string)getenv('APPLE_WALLET_PASS_BASE_URL'))!==''
            : trim((string)getenv('GOOGLE_WALLET_SAVE_BASE_URL'))!=='';
        $base=$platform==='apple'?(string)getenv('APPLE_WALLET_PASS_BASE_URL'):(string)getenv('GOOGLE_WALLET_SAVE_BASE_URL');
        $record=[
            'id'=>$id,'event_id'=>$event['id']??'','attendee_id'=>$attendee['id']??'','platform'=>$platform,
            'status'=>$configured?'ready':'provider_configuration_required',
            'provider_url'=>$configured?rtrim($base,'/').'/'.rawurlencode($token):'',
            'token'=>$token,'credential_payload'=>$credentialPayload,
            'label'=>$event['name']??'DigiSangam Event','holder'=>$attendee['name']??'',
            'created_at'=>date(DATE_ATOM),
        ];
        return $this->passes->create($record);
    }
}
