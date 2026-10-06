<?php
declare(strict_types=1);

namespace DigiSangam\Notifications;

final class LogWhatsAppProvider implements WhatsAppProviderInterface
{
    public function sendTemplate(string $to,string $template,array $parameters=[]): array
    {
        error_log('[DigiSangam WhatsApp] to='.$to.' template='.$template);
        return ['provider'=>'log','id'=>'log_'.bin2hex(random_bytes(5))];
    }
}
