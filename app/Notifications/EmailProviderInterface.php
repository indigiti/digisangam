<?php
declare(strict_types=1);

namespace DigiSangam\Notifications;

interface EmailProviderInterface
{
    public function send(string $to,string $subject,string $html,string $text=''): array;
}
