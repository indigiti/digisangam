<?php
declare(strict_types=1);

namespace DigiSangam\OnGround;

use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Commerce\OrderRepository;
use DigiSangam\Credentials\CredentialBindingRepository;
use DigiSangam\Credentials\CredentialService;

final class ScannerService
{
    public function __construct(
        private readonly CredentialService $credentials,
        private readonly AttendeeRepository $attendees,
        private readonly OrderRepository $orders,
        private readonly CheckinRepository $checkins,
        private readonly ?AccessPolicyService $accessPolicy=null,
        private readonly ?AccessEventRepository $accessEvents=null,
        private readonly ?CredentialBindingRepository $bindings=null,
    ) {}

    public function verify(string $payload,string $zoneId=''): array
    {
        $resolved=$this->resolveIdentity($payload);
        if(empty($resolved['allowed'])) return $resolved;

        $attendee=$resolved['attendee_private'];
        if(($attendee['status']??'')!=='Confirmed') return [
            'allowed'=>false,'reason'=>'NOT_CONFIRMED',
            'attendee'=>$this->publicAttendee($attendee),'event_id'=>$attendee['event_id']??'',
            'credential_source'=>$resolved['credential_source'],
        ];

        $order=$this->orders->findLatestByAttendee((string)$attendee['id']);
        if($order && (int)($order['amount']??0)>0 && ($order['status']??'')!=='paid'){
            return [
                'allowed'=>false,'reason'=>'PAYMENT_NOT_PAID',
                'attendee'=>$this->publicAttendee($attendee),'event_id'=>$attendee['event_id']??'',
                'credential_source'=>$resolved['credential_source'],
            ];
        }

        $zone=null;
        if($this->accessPolicy && $zoneId!==''){
            $zone=$this->accessPolicy->evaluate((string)$attendee['event_id'],(string)($attendee['category']??''),$zoneId);
            if(empty($zone['allowed'])) return [
                'allowed'=>false,'reason'=>$zone['reason']??'ZONE_DENIED',
                'attendee'=>$this->publicAttendee($attendee),'event_id'=>$attendee['event_id'],'zone'=>$zone['zone']??null,
                'credential_source'=>$resolved['credential_source'],
            ];
        }

        $existing=$this->checkins->find((string)$attendee['event_id'],(string)$attendee['id']);
        return [
            'allowed'=>true,
            'reason'=>$existing?'ALREADY_CHECKED_IN':'VALID',
            'attendee'=>$this->publicAttendee($attendee),
            'event_id'=>$attendee['event_id'],
            'zone'=>$zone['zone']??null,
            'checkin'=>$existing,
            'credential_source'=>$resolved['credential_source'],
        ];
    }

    public function checkin(string $payload,string $operatorId='',string $zoneId=''): array
    {
        $verification=$this->verify($payload,$zoneId);
        if(empty($verification['allowed'])) return $verification;
        $attendee=$verification['attendee'];
        $result=$this->checkins->checkin((string)$verification['event_id'],(string)$attendee['id'],$operatorId,$zoneId);
        $access=$this->accessEvents
            ? $this->accessEvents->enter((string)$verification['event_id'],(string)$attendee['id'],$zoneId,$operatorId)
            : null;
        return $verification + $result + ['access_event'=>$access];
    }

    public function exit(string $payload,string $operatorId='',string $zoneId=''): array
    {
        $resolved=$this->resolveIdentity($payload);
        if(empty($resolved['allowed'])) return $resolved;
        $attendee=$resolved['attendee_private'];
        if(!$this->accessEvents) return ['allowed'=>false,'reason'=>'ACCESS_LEDGER_UNAVAILABLE'];

        $result=$this->accessEvents->exit((string)$attendee['event_id'],(string)$attendee['id'],$zoneId,$operatorId);
        return [
            'allowed'=>true,
            'reason'=>!empty($result['duplicate'])?'ALREADY_EXITED':'EXIT_RECORDED',
            'attendee'=>$this->publicAttendee($attendee),
            'event_id'=>$attendee['event_id'],
            'credential_source'=>$resolved['credential_source'],
            'already_exited'=>(bool)($result['duplicate']??false),
            'access_event'=>$result,
        ];
    }

    private function resolveIdentity(string $payload): array
    {
        [$token,$source]=$this->resolveCredentialToken($payload);
        if($token==='') return ['allowed'=>false,'reason'=>'INVALID_EXTERNAL_CREDENTIAL','credential_source'=>$source];

        $credential=$this->credentials->verify($token);
        if(!$credential) return ['allowed'=>false,'reason'=>'INVALID_SIGNATURE','credential_source'=>$source];

        $attendee=$this->attendees->find((string)($credential['attendee_id']??''));
        if(!$attendee) return ['allowed'=>false,'reason'=>'ATTENDEE_NOT_FOUND','credential_source'=>$source];
        if(($attendee['event_id']??'')!==($credential['event_id']??'')) return ['allowed'=>false,'reason'=>'EVENT_MISMATCH','credential_source'=>$source];

        return [
            'allowed'=>true,
            'attendee_private'=>$attendee,
            'event_id'=>$attendee['event_id'],
            'credential_source'=>$source,
        ];
    }

    private function resolveCredentialToken(string $payload): array
    {
        $payload=trim($payload);
        foreach(['nfc','rfid'] as $type){
            $prefix=$type.':';
            if(str_starts_with(strtolower($payload),$prefix)){
                if(!$this->bindings) return ['',strtoupper($type)];
                $binding=$this->bindings->resolve($type,substr($payload,strlen($prefix)));
                if(!$binding) return ['',strtoupper($type)];
                $issued=$this->credentials->issue((string)$binding['attendee_id'],(string)$binding['event_id']);
                return [(string)$issued['token'],strtoupper($type)];
            }
        }
        $prefix='digisangam://credential/';
        return [str_starts_with($payload,$prefix)?substr($payload,strlen($prefix)):$payload,'QR'];
    }

    private function publicAttendee(array $attendee): array
    {
        return [
            'id'=>$attendee['id'],'name'=>$attendee['name'],'category'=>$attendee['category']??'',
            'company'=>$attendee['company']??'','status'=>$attendee['status']??'',
        ];
    }
}
