<?php
declare(strict_types=1);

namespace DigiSangam\PublicFlow;

use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Commerce\OrderRepository;
use DigiSangam\Credentials\CredentialService;
use DigiSangam\Events\EventRepository;
use DigiSangam\Invitations\InvitationRepository;
use DigiSangam\Notifications\NotificationOutbox;
use DigiSangam\Payments\PaymentService;
use DigiSangam\Registration\RegistrationRepository;
use DigiSangam\Tickets\TicketRepository;

final class RegistrationCheckoutService
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly RegistrationRepository $registration,
        private readonly InvitationRepository $invitations,
        private readonly TicketRepository $tickets,
        private readonly AttendeeRepository $attendees,
        private readonly OrderRepository $orders,
        private readonly NotificationOutbox $notifications,
        private readonly CredentialService $credentials,
        private readonly PaymentService $payments,
    ) {}

    public function publicEvent(string $eventId): array
    {
        $event=$this->events->find($eventId);
        if(!$event || !in_array((string)($event['status']??''),['Published','Live'],true)){
            throw new \RuntimeException('Event is not available for registration.');
        }
        return $this->eventPayload($eventId,$event);
    }

    public function previewEvent(string $eventId): array
    {
        $event=$this->events->find($eventId);
        if(!$event) throw new \RuntimeException('Event not found.');
        $payload=$this->eventPayload($eventId,$event);
        $payload['preview']=true;
        return $payload;
    }

    private function eventPayload(string $eventId,array $event): array
    {
        $schema=$this->registration->schema($eventId);
        return [
            'event'=>$event,
            'registration'=>[
                'title'=>$schema['title']??'Event Registration',
                'approval_mode'=>$schema['approval_mode']??'auto',
                'categories'=>$schema['categories']??[],
                'fields'=>$schema['fields']??[],
            ],
            'tickets'=>$this->tickets->publicForEvent($eventId),
        ];
    }

    public function register(string $eventId, array $input): array
    {
        if (!empty($input['website'])) throw new \InvalidArgumentException('Invalid submission.');
        $public = $this->publicEvent($eventId);
        $schema = $this->registration->schema($eventId);
        $submitted = is_array($input['answers'] ?? null) ? $input['answers'] : [];
        $answers = $this->validateAnswers(
            (array)($schema['fields'] ?? []),
            $submitted,
            (array)($schema['categories'] ?? [])
        );

        $email = strtolower(trim((string)($answers['fld_email'] ?? $input['email'] ?? '')));
        $name = trim((string)($answers['fld_name'] ?? $input['name'] ?? ''));
        $phone = trim((string)($answers['fld_phone'] ?? $input['phone'] ?? ''));
        $company = trim((string)($answers['fld_company'] ?? $input['company'] ?? ''));
        $category = trim((string)($answers['fld_category'] ?? $input['category'] ?? 'General'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('A valid email is required.');
        if ($name === '') throw new \InvalidArgumentException('Full name is required.');
        if ($this->attendees->findByEmailForEvent($email,$eventId)) throw new \InvalidArgumentException('This email is already registered for the event.');

        $approvalMode = (string)($schema['approval_mode'] ?? 'auto');
        if ($approvalMode === 'invite_only') $this->assertInvitation($email,$eventId,trim((string)($input['invitation_token']??'')));

        $ticketId = trim((string)($input['ticket_id'] ?? ''));
        if ($ticketId === '') throw new \InvalidArgumentException('Please select a ticket.');
        $ticket = $this->tickets->find($ticketId);
        if (!$ticket || ($ticket['event_id'] ?? '') !== $eventId) throw new \InvalidArgumentException('Selected ticket is invalid.');

        $reserved = $this->tickets->reserveOne($ticketId, $eventId);
        $requiresPayment=(int)($reserved['price'] ?? 0) > 0;
        $status = ($approvalMode === 'manual' || $requiresPayment) ? 'Pending' : 'Confirmed';
        $confirmationToken = bin2hex(random_bytes(24));
        $attendee = null;

        try {
            $attendee = $this->attendees->create([
                'name'=>$name,'email'=>$email,'phone'=>$phone,'company'=>$company,'category'=>$category,
                'status'=>$status,'event_id'=>$eventId,'ticket_id'=>$ticketId,'answers'=>$answers,
                'confirmation_token'=>$confirmationToken,
            ]);

            $order = $this->orders->create([
                'event_id'=>$eventId,'attendee_id'=>$attendee['id'],'ticket_id'=>$ticketId,
                'amount'=>(int)($reserved['price'] ?? 0),'currency'=>strtoupper((string)($public['event']['currency']??'INR')),'status'=>$requiresPayment?'pending':'paid',
            ]);
            $payment = $this->payments->create($order, ['attendee'=>$attendee,'event'=>$public['event'],'ticket'=>$reserved]);
            $order = $this->orders->updatePayment((string)$order['id'],$payment) ?? $order;

            if (($payment['status'] ?? '') === 'paid' && $approvalMode !== 'manual') {
                $attendee = $this->attendees->update((string)$attendee['id'],['status'=>'Confirmed']) ?? $attendee;
            }
            if($approvalMode==='invite_only'){
                $this->invitations->markAccepted(trim((string)($input['invitation_token']??'')));
            }
        } catch (\Throwable $e) {
            if ($attendee !== null) $this->attendees->delete((string)$attendee['id']);
            $this->tickets->releaseOne($ticketId,$eventId);
            throw $e;
        }

        $credential = ($attendee['status'] ?? '') === 'Confirmed' && ($order['status'] ?? '') === 'paid'
            ? $this->credentials->issue((string)$attendee['id'], $eventId)
            : null;

        try {
            $this->notifications->queue('email','registration_confirmation',['email'=>$email],[
                'event_id'=>$eventId,'attendee_id'=>$attendee['id'],'status'=>$attendee['status'],'confirmation_token'=>$confirmationToken
            ]);
            if ($phone !== '') {
                $this->notifications->queue('whatsapp','registration_confirmation',['phone'=>$phone],[
                    'event_id'=>$eventId,'attendee_id'=>$attendee['id'],'status'=>$attendee['status']
                ]);
            }
        } catch (\Throwable) {
            // Registration remains valid even if the notification outbox is temporarily unavailable.
        }

        return [
            'attendee'=>$this->publicAttendee($attendee),
            'ticket'=>$reserved,
            'order'=>$order,
            'payment'=>$payment,
            'credential'=>$credential,
            'confirmation_token'=>$confirmationToken,
        ];
    }

    public function confirmation(string $token): array
    {
        if (strlen($token) < 32) throw new \InvalidArgumentException('Invalid confirmation token.');
        $attendee = $this->attendees->findByConfirmationToken($token);
        if (!$attendee) throw new \RuntimeException('Confirmation not found.');
        $event = $this->events->find((string)$attendee['event_id']);
        $ticket = $this->tickets->find((string)($attendee['ticket_id'] ?? ''));
        $order = $this->orders->findLatestByAttendee((string)$attendee['id']);
        $credential = ($attendee['status'] ?? '') === 'Confirmed' && (($order['status'] ?? 'paid') === 'paid')
            ? $this->credentials->issue((string)$attendee['id'], (string)$attendee['event_id'])
            : null;
        return [
            'attendee'=>$this->publicAttendee($attendee),
            'event'=>$event,
            'ticket'=>$ticket,
            'order'=>$order,
            'credential'=>$credential,
        ];
    }

    private function validateAnswers(array $fields,array $submitted,array $categories): array
    {
        $answers=[];
        foreach($fields as $field){
            $id=(string)($field['id']??'');
            if($id==='') continue;
            $value=$submitted[$id]??null;
            $answers[$id]=$value;
        }

        foreach($fields as $field){
            if(!$this->isVisible($field,$answers)) continue;
            $id=(string)($field['id']??'');
            $label=(string)($field['label']??'Field');
            $type=(string)($field['type']??'text');
            $value=$answers[$id]??null;

            if(!empty($field['required']) && ($value===null||$value===''||$value===[])){
                throw new \InvalidArgumentException($label.' is required.');
            }
            if($value===null||$value===''||$value===[]) continue;

            if(is_string($value) && strlen($value)>20000) throw new \InvalidArgumentException($label.' is too long.');

            if($type==='email' && !filter_var((string)$value,FILTER_VALIDATE_EMAIL)){
                throw new \InvalidArgumentException($label.' must be a valid email.');
            }
            if($type==='phone'){
                $phone=preg_replace('/[\s().-]+/','',(string)$value)??'';
                if(!preg_match('/^\+?[0-9]{7,18}$/',$phone)) throw new \InvalidArgumentException($label.' must be a valid phone number.');
                $answers[$id]=$phone;
            }
            if($type==='date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$value)){
                throw new \InvalidArgumentException($label.' must be a valid date.');
            }

            if(in_array($type,['select','dropdown','radio'],true)){
                $options=$id==='fld_category'?$categories:(array)($field['options']??[]);
                if(!in_array((string)$value,array_map('strval',$options),true)) throw new \InvalidArgumentException($label.' contains an invalid option.');
            }
            if($type==='multiselect'){
                if(!is_array($value)) throw new \InvalidArgumentException($label.' must contain a list of options.');
                $allowed=array_map('strval',(array)($field['options']??[]));
                foreach($value as $selected){
                    if(!in_array((string)$selected,$allowed,true)) throw new \InvalidArgumentException($label.' contains an invalid option.');
                }
                $answers[$id]=array_values(array_unique(array_map('strval',$value)));
            }
        }
        return $answers;
    }

    private function isVisible(array $field,array $answers): bool
    {
        if (($field['visibility'] ?? 'always') !== 'conditional' || !is_array($field['condition'] ?? null)) return true;
        $condition=$field['condition'];
        $source=$answers[(string)($condition['field'] ?? '')] ?? null;
        $value=$condition['value'] ?? null;
        return match ((string)($condition['operator'] ?? 'equals')) {
            'not_equals' => $source !== $value,
            'contains' => is_array($source) ? in_array($value,$source,true) : str_contains((string)$source,(string)$value),
            default => $source === $value,
        };
    }

    private function assertInvitation(string $email,string $eventId,string $token): void
    {
        if($token==='') throw new \InvalidArgumentException('This event requires an invitation link.');
        $invite=$this->invitations->findByToken($token);
        if(
            !$invite ||
            ($invite['event_id']??'')!==$eventId ||
            strtolower((string)($invite['email']??''))!==$email ||
            in_array(($invite['status']??'pending'),['revoked','accepted'],true)
        ){
            throw new \InvalidArgumentException('This invitation is invalid, expired or already used.');
        }
    }

    private function publicAttendee(array $attendee): array
    {
        return [
            'id'=>$attendee['id'],'name'=>$attendee['name'],'email'=>$attendee['email'],
            'category'=>$attendee['category'],'status'=>$attendee['status'],'event_id'=>$attendee['event_id'],
        ];
    }
}
