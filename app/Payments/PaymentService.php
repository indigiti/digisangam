<?php
declare(strict_types=1);

namespace DigiSangam\Payments;

final class PaymentService
{
    public function create(array $order, array $context = []): array
    {
        if ((int)($order['amount'] ?? 0) === 0) {
            return (new FreePaymentGateway())->create($order,$context);
        }

        $provider=strtolower(trim((string)getenv('DIGISANGAM_PAYMENT_PROVIDER')));
        if($provider==='razorpay'){
            return (new RazorpayPaymentGateway(
                (string)getenv('RAZORPAY_KEY_ID'),
                (string)getenv('RAZORPAY_KEY_SECRET'),
            ))->create($order,$context);
        }

        return (new ManualPaymentGateway())->create($order,$context);
    }
}
