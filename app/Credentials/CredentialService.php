<?php
declare(strict_types=1);

namespace DigiSangam\Credentials;

final class CredentialService
{
    public function __construct(private readonly string $secret) {}

    public function issue(string $attendeeId, string $eventId): array
    {
        // v2 is intentionally stable for an attendee/event pair so the same signed
        // credential can be matched by an authenticated offline OnGround snapshot.
        $nonce = substr(hash_hmac('sha256',$eventId.'|'.$attendeeId,$this->secret),0,16);
        $payload = base64_encode(json_encode([
            'v'=>2,'attendee_id'=>$attendeeId,'event_id'=>$eventId,'nonce'=>$nonce
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $payload, $this->secret);
        $token = rtrim(strtr($payload, '+/', '-_'), '=') . '.' . $signature;
        return [
            'type'=>'signed_event_credential',
            'version'=>2,
            'token'=>$token,
            'payload'=>'digisangam://credential/' . $token,
            'attendee_id'=>$attendeeId,
            'event_id'=>$eventId,
        ];
    }

    public function verify(string $token): ?array
    {
        [$encoded, $signature] = array_pad(explode('.', $token, 2), 2, '');
        if ($encoded === '' || $signature === '') return null;
        $payload = strtr($encoded, '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $payload = base64_decode($payload, true);
        if ($payload === false) return null;
        $canonical = base64_encode($payload);
        $expected = hash_hmac('sha256', $canonical, $this->secret);
        if (!hash_equals($expected, $signature)) return null;
        $data = json_decode($payload, true);
        if(!is_array($data)) return null;
        if(!in_array((int)($data['v']??1),[1,2],true)) return null;
        if(empty($data['attendee_id'])||empty($data['event_id'])) return null;
        return $data;
    }
}
