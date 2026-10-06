<?php
declare(strict_types=1);

namespace DigiSangam\Notifications;

final class PhpMailEmailProvider implements EmailProviderInterface
{
    public function __construct(private readonly string $fromAddress,private readonly string $fromName) {}

    public function send(string $to,string $subject,string $html,string $text=''): array
    {
        if(!filter_var($to,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Invalid email recipient.');
        $headers=[
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: '.$this->fromName.' <'.$this->fromAddress.'>',
        ];
        if(!mail($to,$subject,$html,implode("\r\n",$headers))) throw new \RuntimeException('PHP mail() failed.');
        return ['provider'=>'mail','id'=>'mail_'.bin2hex(random_bytes(5))];
    }
}
