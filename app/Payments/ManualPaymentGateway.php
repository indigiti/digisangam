<?php
declare(strict_types=1);

namespace DigiSangam\Payments;

final class ManualPaymentGateway implements PaymentGatewayInterface
{
    public function create(array $order, array $context = []): array
    {
        return [
            'provider'=>'manual',
            'status'=>'pending',
            'payment_reference'=>'MANUAL-' . strtoupper(substr((string)$order['id'], -8)),
            'amount'=>(int)($order['amount'] ?? 0),
            'currency'=>(string)($order['currency'] ?? 'INR'),
            'action'=>'pending_external_provider',
            'message'=>'Payment provider adapter is not configured yet.',
        ];
    }
}
