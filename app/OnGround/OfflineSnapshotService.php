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
            'version'=>3,'event_id'=>$eventId,'generated_at'=>date(DATE_ATOM),
            'expires_at'=>date(DATE_ATOM,time()+48*3600),
            'attendees'=>$attendees,'credentials'=>$credentials,'tickets'=>$tickets,
            'venue'=>$this->venue->get($eventId),'sessions'=>$sessions,'badges'=>$badges,
        ];
        // An offline browser cannot verify an HMAC without exposing the server secret.
        // Derive a separate Ed25519 signing key from the stable credential secret.
        // The browser verifies the exact signed bytes (signed_payload) before use.
        if(!function_exists('sodium_crypto_sign_seed_keypair')){
            throw new \\RuntimeException('Sodium is required for verifiable offline snapshots.');
        }
        $seed=hash('sha256','digisangam/offline-v3|'.$this->secret,true);
        $pair=sodium_crypto_sign_seed_keypair($seed);
        $snapshot['signing_public_key']=base64_encode(sodium_crypto_sign_publickey($pair));
        $canonical=json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        return $snapshot + [
            'signature_algorithm'=>'Ed25519',
            'signed_payload'=>base64_encode($canonical),
            'signature'=>base64_encode(sodium_crypto_sign_detached($canonical,sodium_crypto_sign_secretkey($pair))),
        ];
    }
}
