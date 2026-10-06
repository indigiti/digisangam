<?php
declare(strict_types=1);

namespace DigiSangam\Notifications;

final class LogEmailProvider implements EmailProviderInterface
{
    public function send(string $to,string $subject,string $html,string $text=''): array
    {
        error_log('[DigiSangam email] to='.$to.' subject='.$subject);
        return ['provider'=>'log','id'=>'log_'.bin2hex(random_bytes(5))];
    }
}
