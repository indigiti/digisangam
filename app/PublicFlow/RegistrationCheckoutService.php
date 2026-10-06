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
        $event = $this->events->find($eventId);
        if (!$event || !in_array((string)($event['status'] ?? ''), ['Published','Live'], true)) {
            throw new \RuntimeException('Event is not available for registration.');
        }
        $schema = $this->registration->schema($eventId);
        return [
            'event'=>$event,
            'registration'=>[
                'title'=>$schema['title'] ?? 'Event Registration',
                'approval_mode'=>$schema['approval_mode'] ?? 'auto',
                'categories'=>$schema['categories'] ?? [],
                'fields'=>$schema['fields'] ?? [],
            ],
            'tickets'=>$this->tickets->publicForEvent($eventId),
        ];
    }

    public function register(string $eventId, array $input): array
    {
        if (!empty($input['website'])) throw new \InvalidArgumentException('Invalid submission.');
        $public = $this->publicEvent($eventId);
        $schema = $this->registration->schema($eventId);
        $answers = is_array($input['answers'] ?? null) ? $input['answers'] : [];
        $this->validateRequiredFields((array)($schema['fields'] ?? []), $answers);

        $email = strtolower(trim((string)($answers['fld_email'] ?? $input['email'] ?? '')));
        $name = trim((string)($answers['fld_name'] ?? $input['name'] ?? ''));
        $phone = trim((string)($answers['fld_phone'] ?? $input['phone'] ?? ''));
        $company = trim((string)($answers['fld_company'] ?? $input['company'] ?? ''));
        $category = trim((string)($answers['fld_category'] ?? $input['category'] ?? 'General'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('A valid email is required.');
        if ($name === '') throw new \InvalidArgumentException('Full name is required.');
        if ($this->attendees->findByEmailForEvent($email,$eventId)) throw new \InvalidArgumentException('This email is already registered for the event.');

        $approvalMode = (string)($schema['approval_mode'] ?? 'auto');
        if ($approvalMode === 'invite_only') $this->assertInvitation($email);

        $ticketId = trim((string)($input['ticket_id'] ?? ''));
        if ($ticketId === '') throw new \InvalidArgumentException('Please select a ticket.');
        $ticket = $this->tickets->find($ticketId);
        if (!$ticket || ($ticket['event_id'] ?? '') !== $eventId) throw new \InvalidArgumentException('Selected ticket is invalid.');

        $reserved = $this->tickets->reserveOne($ticketId, $eventId);
        $requiresPayment=(int)($reserved['price'] ?? 0) > 0;
        $status = ($approvalMode === 'manual' || $requiresPayment) ? 'Pending' : 'Confirmed';
        $confirmationToken = bin2hex(random_bytes(24));

        try {
            $attendee = $this->attendees->create([
                'name'=>$name,'email'=>$email,'phone'=>$phone,'company'=>$company,'category'=>$category,
                'status'=>$status,'event_id'=>$eventId,'ticket_id'=>$ticketId,'answers'=>$answers,
                'confirmation_token'=>$confirmationToken,
            ]);
        } catch (\Throwable $e) {
            throw $e;
        }

        $order = $this->orders->create([
            'event_id'=>$eventId,'attendee_id'=>$attendee['id'],'ticket_id'=>$ticketId,
            'amount'=>(int)($reserved['price'] ?? 0),'currency'=>'INR','status'=>$requiresPayment?'pending':'paid',
        ]);
        $payment = $this->payments->create($order, ['attendee'=>$attendee,'event'=>$public['event'],'ticket'=>$reserved]);
        $order = $this->orders->updatePayment((string)$order['id'],$payment) ?? $order;

        if (($payment['status'] ?? '') === 'paid' && $approvalMode !== 'manual') {
            $attendee = $this->attendees->update((string)$attendee['id'],['status'=>'Confirmed']) ?? $attendee;
        }

        $credential = ($attendee['status'] ?? '') === 'Confirmed' && ($order['status'] ?? '') === 'paid'
            ? $this->credentials->issue((string)$attendee['id'], $eventId)
            : null;

        $this->notifications->queue('email','registration_confirmation',['email'=>$email],[
            'event_id'=>$eventId,'attendee_id'=>$attendee['id'],'status'=>$attendee['status'],'confirmation_token'=>$confirmationToken
        ]);
        if ($phone !== '') {
            $this->notifications->queue('whatsapp','registration_confirmation',['phone'=>$phone],[
                'event_id'=>$eventId,'attendee_id'=>$attendee['id'],'status'=>$attendee['status']
            ]);
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
        $credential = ($attendee['status'] ?? '') === 'Confirmed'
            ? $this->credentials->issue((string)$attendee['id'], (string)$attendee['event_id'])
            : null;
        return ['attendee'=>$this->publicAttendee($attendee),'event'=>$event,'ticket'=>$ticket,'credential'=>$credential];
    }

    private function validateRequiredFields(array $fields, array $answers): void
    {
        foreach ($fields as $field) {
            if (empty($field['required'])) continue;
            $id=(string)($field['id'] ?? '');
            $value=$answers[$id] ?? null;
            if ($value === null || $value === '' || $value === []) {
                throw new \InvalidArgumentException(((string)($field['label'] ?? 'Required field')) . ' is required.');
            }
        }
    }

    private function assertInvitation(string $email): void
    {
        foreach ($this->invitations->all() as $invite) {
            if (strtolower((string)($invite['email'] ?? '')) === $email && ($invite['status'] ?? 'pending') !== 'revoked') return;
        }
        throw new \InvalidArgumentException('This event requires a valid invitation.');
    }

    private function publicAttendee(array $attendee): array
    {
        return [
            'id'=>$attendee['id'],'name'=>$attendee['name'],'email'=>$attendee['email'],
            'category'=>$attendee['category'],'status'=>$attendee['status'],'event_id'=>$attendee['event_id'],
        ];
    }
}
