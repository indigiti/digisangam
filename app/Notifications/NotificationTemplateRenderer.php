<?php
declare(strict_types=1);

namespace DigiSangam\Notifications;

final class NotificationTemplateRenderer
{
    public function render(string $template,array $data): array
    {
        $confirmation=$this->confirmationUrl((string)($data['event_id']??''),(string)($data['confirmation_token']??''));
        return match($template){
            'payment_confirmed'=>[
                'subject'=>'Payment confirmed — your DigiSangam registration',
                'html'=>'<h2>Payment confirmed</h2><p>Your event registration payment has been received.</p>'.($confirmation!==''?'<p><a href="'.htmlspecialchars($confirmation,ENT_QUOTES).'">Open your ticket and QR</a></p>':''),
                'text'=>'Payment confirmed. '.($confirmation!==''?'Open your ticket: '.$confirmation:''),
                'whatsapp_template'=>'payment_confirmed',
                'whatsapp_parameters'=>[$confirmation],
            ],
            default=>[
                'subject'=>'Registration received',
                'html'=>'<h2>Registration received</h2><p>Your registration has been recorded by DigiSangam.</p>'.($confirmation!==''?'<p><a href="'.htmlspecialchars($confirmation,ENT_QUOTES).'">View registration status</a></p>':''),
                'text'=>'Registration received. '.($confirmation!==''?'View status: '.$confirmation:''),
                'whatsapp_template'=>'registration_confirmation',
                'whatsapp_parameters'=>[$confirmation],
            ],
        };
    }

    private function confirmationUrl(string $eventId,string $token): string
    {
        if($eventId===''||$token==='') return '';
        $base=rtrim((string)getenv('APP_URL'),'/');
        if($base==='') return '/e/'.rawurlencode($eventId).'/confirmation/'.rawurlencode($token);
        return $base.'/e/'.rawurlencode($eventId).'/confirmation/'.rawurlencode($token);
    }
}
