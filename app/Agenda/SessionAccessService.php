<?php
declare(strict_types=1);

namespace DigiSangam\Agenda;

use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Commerce\OrderRepository;
use DigiSangam\Credentials\CredentialService;

final class SessionAccessService
{
    public function __construct(
        private readonly CredentialService $credentials,
        private readonly AttendeeRepository $attendees,
        private readonly OrderRepository $orders,
        private readonly SessionRepository $sessions,
        private readonly SessionAttendanceRepository $attendance,
    ) {}

    public function enter(string $sessionId,string $payload,string $operatorId=''): array
    {
        $session=null;
        foreach($this->sessions->all() as $row) if(($row['id']??'')===$sessionId){$session=$row;break;}
        if(!$session) throw new \RuntimeException('Session not found.');

        $prefix='digisangam://credential/';
        $token=str_starts_with($payload,$prefix)?substr($payload,strlen($prefix)):$payload;
        $credential=$this->credentials->verify($token);
        if(!$credential) return ['allowed'=>false,'reason'=>'INVALID_SIGNATURE'];

        $attendee=$this->attendees->find((string)($credential['attendee_id']??''));
        if(!$attendee) return ['allowed'=>false,'reason'=>'ATTENDEE_NOT_FOUND'];
        if(($attendee['event_id']??'')!==($session['event_id']??'')) return ['allowed'=>false,'reason'=>'EVENT_MISMATCH'];
        if(($attendee['status']??'')!=='Confirmed') return ['allowed'=>false,'reason'=>'NOT_CONFIRMED'];

        $order=$this->orders->findLatestByAttendee((string)$attendee['id']);
        if($order && (int)($order['amount']??0)>0 && ($order['status']??'')!=='paid'){
            return ['allowed'=>false,'reason'=>'PAYMENT_NOT_PAID'];
        }

        $current=$this->attendance->all($sessionId);
        $already=false;
        foreach($current as $row) if(($row['attendee_id']??'')===($attendee['id']??'')){$already=true;break;}
        if(!$already && (int)($session['capacity']??0)>0 && count($current)>=(int)$session['capacity']){
            return ['allowed'=>false,'reason'=>'SESSION_FULL','session'=>$session];
        }

        $result=$this->attendance->enter($sessionId,(string)$attendee['id'],$operatorId);
        return [
            'allowed'=>true,
            'reason'=>$result['duplicate']?'ALREADY_ENTERED':'SESSION_ENTRY_RECORDED',
            'session'=>$session,
            'attendee'=>['id'=>$attendee['id'],'name'=>$attendee['name'],'category'=>$attendee['category']??''],
            'attendance'=>$result['attendance'],
            'duplicate'=>$result['duplicate'],
        ];
    }
}
