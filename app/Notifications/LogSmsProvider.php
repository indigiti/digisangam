<?php
declare(strict_types=1);
namespace DigiSangam\Notifications;
final class LogSmsProvider implements SmsProviderInterface {
    public function send(string $to,string $message): array {
        if(trim($to)==='') throw new \InvalidArgumentException('SMS recipient is required.');
        return ['provider'=>'log','id'=>'sms-log-'.bin2hex(random_bytes(5)),'to'=>$to,'simulated'=>true];
    }
}
