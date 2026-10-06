<?php
declare(strict_types=1);

namespace DigiSangam\Payments;

interface PaymentGatewayInterface
{
    public function create(array $order, array $context = []): array;
}
