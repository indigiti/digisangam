<?php
declare(strict_types=1);

namespace DigiSangam\Payments;

final class RazorpayWebhookVerifier
{
    public function __construct(private readonly string $secret) {}

    public function verify(string $rawBody, string $signature): array
    {
        if ($this->secret === '') throw new \RuntimeException('Razorpay webhook secret is not configured.');
        if ($signature === '') throw new \InvalidArgumentException('Missing Razorpay webhook signature.');

        $expected = hash_hmac('sha256', $rawBody, $this->secret);
        if (!hash_equals($expected, $signature)) throw new \InvalidArgumentException('Invalid Razorpay webhook signature.');

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) throw new \InvalidArgumentException('Invalid Razorpay webhook payload.');
        return $payload;
    }
}
