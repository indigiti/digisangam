<?php
declare(strict_types=1);

namespace DigiSangam\Payments;

final class FreePaymentGateway implements PaymentGatewayInterface
{
    public function create(array $order, array $context = []): array
    {
        return [
            'provider'=>'free',
            'status'=>'paid',
            'payment_reference'=>'FREE-' . strtoupper(substr((string)$order['id'], -8)),
            'amount'=>0,
            'currency'=>(string)($order['currency'] ?? 'INR'),
            'action'=>'none',
        ];
    }
}
