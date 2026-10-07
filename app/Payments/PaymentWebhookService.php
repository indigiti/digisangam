<?php
declare(strict_types=1);

namespace DigiSangam\Payments;

use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Commerce\OrderRepository;
use DigiSangam\Core\EventJournal\EventJournal;
use DigiSangam\Notifications\NotificationOutbox;
use DigiSangam\Registration\RegistrationRepository;
use DigiSangam\Tickets\TicketRepository;

final class PaymentWebhookService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly AttendeeRepository $attendees,
        private readonly RegistrationRepository $registration,
        private readonly NotificationOutbox $notifications,
        private readonly EventJournal $journal,
        private readonly TicketRepository $tickets,
    ) {}

    public function handleRazorpay(array $payload): array
    {
        $event = (string)($payload['event'] ?? '');
        $paymentEntity = (array)($payload['payload']['payment']['entity'] ?? []);
        $orderEntity = (array)($payload['payload']['order']['entity'] ?? []);
        $notes = (array)($paymentEntity['notes'] ?? $orderEntity['notes'] ?? []);
        $internalOrderId = (string)($notes['digisangam_order_id'] ?? $orderEntity['receipt'] ?? '');

        $providerOrderId=(string)($paymentEntity['order_id'] ?? $orderEntity['id'] ?? '');
        $order = $internalOrderId!=='' ? $this->orders->find($internalOrderId) : null;
        if(!$order && $providerOrderId!=='') $order=$this->orders->findByProviderOrderId($providerOrderId);
        if(!$order) throw new \RuntimeException('Referenced DigiSangam order was not found.');
        $internalOrderId=(string)$order['id'];

        if (in_array($event, ['payment.captured','order.paid'], true)) {
            if (($order['status'] ?? '') === 'paid') return ['ok'=>true,'duplicate'=>true,'order'=>$order];

            $providerPaymentId = (string)($paymentEntity['id'] ?? $order['payment_reference'] ?? '');
            $capture=(new PaymentCaptureService(
                $this->orders,
                $this->attendees,
                $this->registration,
                $this->notifications,
                $this->journal,
                $this->tickets,
            ))->capture($internalOrderId,'razorpay',$providerPaymentId);
            return ['ok'=>true]+$capture;
        }

        if ($event === 'payment.failed') {
            if (($order['status'] ?? '') === 'paid') return ['ok'=>true,'ignored'=>true,'reason'=>'ALREADY_PAID'];
            $transition=$this->orders->transitionStatus($internalOrderId,['pending'],'failed',[
                'provider'=>'razorpay',
                'payment_reference'=>(string)($paymentEntity['id'] ?? ''),
                'reservation_expires_at'=>'',
            ]);
            if($transition){
                $order=$transition;
                if(!empty($order['ticket_id'])) $this->tickets->releaseReservation((string)$order['ticket_id'],(string)$order['event_id']);
            }else{
                $order=$this->orders->find($internalOrderId)??$order;
            }
            $this->journal->append('payment.failed',['order_id'=>$order['id'],'provider'=>'razorpay']);
            return ['ok'=>true,'order'=>$order];
        }

        return ['ok'=>true,'ignored'=>true,'event'=>$event];
    }
}
