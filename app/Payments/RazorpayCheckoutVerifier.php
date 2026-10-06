<?php
declare(strict_types=1);

namespace DigiSangam\Payments;

final class RazorpayCheckoutVerifier
{
    public function __construct(private readonly string $keySecret) {}

    public function verify(string $providerOrderId,string $paymentId,string $signature): bool
    {
        if ($this->keySecret === '' || $providerOrderId === '' || $paymentId === '' || $signature === '') return false;
        $expected=hash_hmac('sha256',$providerOrderId.'|'.$paymentId,$this->keySecret);
        return hash_equals($expected,$signature);
    }
}
