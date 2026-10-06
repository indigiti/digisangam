<?php
declare(strict_types=1);

namespace DigiSangam\Payments;

final class RazorpayPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly string $keyId,
        private readonly string $keySecret,
    ) {}

    public function create(array $order, array $context = []): array
    {
        if ($this->keyId === '' || $this->keySecret === '') {
            throw new \RuntimeException('Razorpay credentials are not configured.');
        }

        $payload = [
            'amount' => max(0, (int)($order['amount'] ?? 0)) * 100,
            'currency' => strtoupper((string)($order['currency'] ?? 'INR')),
            'receipt' => (string)$order['id'],
            'notes' => [
                'digisangam_order_id' => (string)$order['id'],
                'event_id' => (string)($order['event_id'] ?? ''),
                'attendee_id' => (string)($order['attendee_id'] ?? ''),
            ],
        ];

        $response = $this->request('https://api.razorpay.com/v1/orders', $payload);
        if (empty($response['id'])) throw new \RuntimeException('Razorpay did not return an order ID.');

        return [
            'provider' => 'razorpay',
            'status' => 'pending',
            'payment_reference' => (string)$response['id'],
            'provider_order_id' => (string)$response['id'],
            'key_id' => $this->keyId,
            'amount' => (int)($order['amount'] ?? 0),
            'amount_subunits' => (int)$payload['amount'],
            'currency' => (string)$payload['currency'],
            'action' => 'razorpay_checkout',
            'prefill' => [
                'name' => (string)($context['attendee']['name'] ?? ''),
                'email' => (string)($context['attendee']['email'] ?? ''),
                'contact' => (string)($context['attendee']['phone'] ?? ''),
            ],
            'description' => (string)($context['event']['name'] ?? 'Event registration'),
        ];
    }

    private function request(string $url, array $payload): array
    {
        if (!function_exists('curl_init')) throw new \RuntimeException('PHP cURL extension is required for Razorpay.');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->keyId . ':' . $this->keySecret,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $error !== '') throw new \RuntimeException('Razorpay connection failed.');
        $decoded = json_decode((string)$raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            $message = is_array($decoded) ? (string)($decoded['error']['description'] ?? 'Razorpay request failed.') : 'Razorpay request failed.';
            throw new \RuntimeException($message);
        }
        return $decoded;
    }
}
