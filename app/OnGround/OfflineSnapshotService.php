<?php
declare(strict_types=1);

namespace DigiSangam\OnGround;

use DigiSangam\Agenda\SessionRepository;
use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Badges\BadgeTemplateRepository;
use DigiSangam\Credentials\CredentialService;
use DigiSangam\Tickets\TicketRepository;
use DigiSangam\Venue\VenueRepository;

final class OfflineSnapshotService
{
    public function __construct(
        private readonly AttendeeRepository $attendees,
        private readonly TicketRepository $tickets,
        private readonly VenueRepository $venue,
        private readonly SessionRepository $sessions,
        private readonly BadgeTemplateRepository $badges,
        private readonly string $secret,
    ) {}

    public function build(string $eventId): array
    {
        $attendees=array_values(array_filter($this->attendees->all(),static fn(array $row): bool => ($row['event_id']??'')===$eventId && ($row['status']??'')==='Confirmed'));
        $tickets=array_values(array_filter($this->tickets->all(),static fn(array $row): bool => ($row['event_id']??'')===$eventId));
        $sessions=array_values(array_filter($this->sessions->all(),static fn(array $row): bool => ($row['event_id']??'')===$eventId));
        $badges=array_values(array_filter($this->badges->all(),static fn(array $row): bool => ($row['event_id']??'')===$eventId));
        $credentials=[];
        $credentialService=new CredentialService($this->secret);
        foreach($attendees as $attendee){
            $issued=$credentialService->issue((string)$attendee['id'],$eventId);
            $credentials[]=[
                'attendee_id'=>$attendee['id'],
                'name'=>$attendee['name']??'',
                'category'=>$attendee['category']??'',
                'company'=>$attendee['company']??'',
                'payload'=>$issued['payload'],
            ];
        }
        $snapshot=[
            'version'=>2,'event_id'=>$eventId,'generated_at'=>date(DATE_ATOM),
            'attendees'=>$attendees,'credentials'=>$credentials,'tickets'=>$tickets,
            'venue'=>$this->venue->get($eventId),'sessions'=>$sessions,'badges'=>$badges,
        ];
        $canonical=json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $snapshot['signature']=hash_hmac('sha256',$canonical,$this->secret);
        return $snapshot;
    }
}
