<?php
declare(strict_types=1);

namespace DigiSangam\Notifications;

interface WhatsAppProviderInterface
{
    public function sendTemplate(string $to,string $template,array $parameters=[]): array;
}
