<?php
declare(strict_types=1);
namespace DigiSangam\Notifications;
interface SmsProviderInterface { public function send(string $to,string $message): array; }
