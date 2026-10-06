<?php
declare(strict_types=1);

namespace DigiSangam\Payments;

final class PaymentService
{
    public function create(array $order, array $context = []): array
    {
        $gateway = ((int)($order['amount'] ?? 0)) === 0
            ? new FreePaymentGateway()
            : new ManualPaymentGateway();

        return $gateway->create($order, $context);
    }
}
