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
            $capture=(new PaymentCaptureService(
                $this->orders,
                $this->attendees,
                $this->registration,
                $this->notifications,
                $this->journal,
            ))->capture($internalOrderId,'razorpay',$providerPaymentId);
            return ['ok'=>true]+$capture;
        }

        if ($event === 'payment.failed') {
            if (($order['status'] ?? '') === 'paid') return ['ok'=>true,'ignored'=>true,'reason'=>'ALREADY_PAID'];
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
