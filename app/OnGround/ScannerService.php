<?php
declare(strict_types=1);

namespace DigiSangam\OnGround;

use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Commerce\OrderRepository;
use DigiSangam\Credentials\CredentialService;

final class ScannerService
{
    public function __construct(
        private readonly CredentialService $credentials,
        private readonly AttendeeRepository $attendees,
        private readonly OrderRepository $orders,
        private readonly CheckinRepository $checkins,
    ) {}

    public function verify(string $payload): array
    {
        $token=$this->tokenFromPayload($payload);
        $credential=$this->credentials->verify($token);
        if(!$credential) return ['allowed'=>false,'reason'=>'INVALID_SIGNATURE'];

        $attendee=$this->attendees->find((string)($credential['attendee_id']??''));
        if(!$attendee) return ['allowed'=>false,'reason'=>'ATTENDEE_NOT_FOUND'];
        if(($attendee['event_id']??'')!==($credential['event_id']??'')) return ['allowed'=>false,'reason'=>'EVENT_MISMATCH'];
        if(($attendee['status']??'')!=='Confirmed') return ['allowed'=>false,'reason'=>'NOT_CONFIRMED','attendee'=>$this->publicAttendee($attendee)];

        $order=$this->orders->findLatestByAttendee((string)$attendee['id']);
        if($order && (int)($order['amount']??0)>0 && ($order['status']??'')!=='paid'){
            return ['allowed'=>false,'reason'=>'PAYMENT_NOT_PAID','attendee'=>$this->publicAttendee($attendee)];
        }

        $existing=$this->checkins->find((string)$attendee['event_id'],(string)$attendee['id']);
        return [
            'allowed'=>true,
            'reason'=>$existing?'ALREADY_CHECKED_IN':'VALID',
            'attendee'=>$this->publicAttendee($attendee),
            'event_id'=>$attendee['event_id'],
            'checkin'=>$existing,
        ];
    }

    public function checkin(string $payload,string $operatorId=''): array
    {
        $verification=$this->verify($payload);
        if(empty($verification['allowed'])) return $verification;
        $attendee=$verification['attendee'];
        $result=$this->checkins->checkin((string)$verification['event_id'],(string)$attendee['id'],$operatorId);
        return $verification + $result;
    }

    private function tokenFromPayload(string $payload): string
    {
        $prefix='digisangam://credential/';
        return str_starts_with($payload,$prefix)?substr($payload,strlen($prefix)):$payload;
    }

    private function publicAttendee(array $attendee): array
    {
        return [
            'id'=>$attendee['id'],'name'=>$attendee['name'],'category'=>$attendee['category']??'',
            'company'=>$attendee['company']??'','status'=>$attendee['status']??'',
        ];
    }
}
