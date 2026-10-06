<?php
declare(strict_types=1);

namespace DigiSangam\Payments;

use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Commerce\OrderRepository;
use DigiSangam\Core\EventJournal\EventJournal;
use DigiSangam\Notifications\NotificationOutbox;
use DigiSangam\Registration\RegistrationRepository;

final class PaymentWebhookService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly AttendeeRepository $attendees,
        private readonly RegistrationRepository $registration,
        private readonly NotificationOutbox $notifications,
        private readonly EventJournal $journal,
    ) {}

    public function handleRazorpay(array $payload): array
    {
        $event = (string)($payload['event'] ?? '');
        $paymentEntity = (array)($payload['payload']['payment']['entity'] ?? []);
        $orderEntity = (array)($payload['payload']['order']['entity'] ?? []);
        $notes = (array)($paymentEntity['notes'] ?? $orderEntity['notes'] ?? []);
        $internalOrderId = (string)($notes['digisangam_order_id'] ?? $orderEntity['receipt'] ?? '');

        if ($internalOrderId === '') throw new \InvalidArgumentException('Webhook does not reference a DigiSangam order.');
        $order = $this->orders->find($internalOrderId);
        if (!$order) throw new \RuntimeException('Referenced order was not found.');

        if (in_array($event, ['payment.captured','order.paid'], true)) {
            if (($order['status'] ?? '') === 'paid') return ['ok'=>true,'duplicate'=>true,'order'=>$order];

            $providerPaymentId = (string)($paymentEntity['id'] ?? $orderEntity['id'] ?? $order['payment_reference'] ?? '');
            $order = $this->orders->updatePayment($internalOrderId, [
                'status'=>'paid',
                'provider'=>'razorpay',
                'payment_reference'=>$providerPaymentId,
            ]) ?? $order;

            $attendee = $this->attendees->find((string)$order['attendee_id']);
            if ($attendee) {
                $schema = $this->registration->schema((string)$order['event_id']);
                if (($schema['approval_mode'] ?? 'auto') !== 'manual') {
                    $attendee = $this->attendees->update((string)$attendee['id'], ['status'=>'Confirmed']) ?? $attendee;
                }
                $this->notifications->queue('email','payment_confirmed',['email'=>$attendee['email'] ?? ''],[
                    'event_id'=>$order['event_id'],'attendee_id'=>$attendee['id'],'order_id'=>$order['id'],
                    'confirmation_token'=>$attendee['confirmation_token'] ?? '',
                ]);
                if (!empty($attendee['phone'])) {
                    $this->notifications->queue('whatsapp','payment_confirmed',['phone'=>$attendee['phone']],[
                        'event_id'=>$order['event_id'],'attendee_id'=>$attendee['id'],'order_id'=>$order['id'],
                    ]);
                }
            }

            $this->journal->append('payment.captured',[
                'order_id'=>$order['id'],'provider'=>'razorpay','payment_reference'=>$providerPaymentId,
            ]);
            return ['ok'=>true,'order'=>$order,'attendee'=>$attendee ?? null];
        }

        if ($event === 'payment.failed') {
            $order = $this->orders->updatePayment($internalOrderId,[
                'status'=>'failed',
                'provider'=>'razorpay',
                'payment_reference'=>(string)($paymentEntity['id'] ?? ''),
            ]) ?? $order;
            $this->journal->append('payment.failed',['order_id'=>$order['id'],'provider'=>'razorpay']);
            return ['ok'=>true,'order'=>$order];
        }

        return ['ok'=>true,'ignored'=>true,'event'=>$event];
    }
}
