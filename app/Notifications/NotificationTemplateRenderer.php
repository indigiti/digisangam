<?php
declare(strict_types=1);

namespace DigiSangam\Notifications;

final class NotificationTemplateRenderer
{
    public function render(string $template,array $data): array
    {
        $confirmation=$this->confirmationUrl((string)($data['event_id']??''),(string)($data['confirmation_token']??''));

        if($template==='custom_campaign'){
            $subject=trim((string)($data['subject']??'Event update')) ?: 'Event update';
            $content=trim((string)($data['content']??''));
            $safe=nl2br(htmlspecialchars($content,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));
            return [
                'subject'=>$subject,
                'html'=>'<div style="font-family:Arial,sans-serif;line-height:1.6">'.$safe.'</div>'.($confirmation!==''?'<p><a href="'.htmlspecialchars($confirmation,ENT_QUOTES).'">View your registration</a></p>':''),
                'text'=>$content.($confirmation!==''?"\n\nView your registration: ".$confirmation:''),
                'whatsapp_template'=>'custom_campaign',
                'whatsapp_parameters'=>[$content,$confirmation],
            ];
        }

        return match($template){
            'payment_confirmed'=>[
                'subject'=>'Payment confirmed — your DigiSangam registration',
                'html'=>'<h2>Payment confirmed</h2><p>Your event registration payment has been received.</p>'.($confirmation!==''?'<p><a href="'.htmlspecialchars($confirmation,ENT_QUOTES).'">Open your ticket and QR</a></p>':''),
                'text'=>'Payment confirmed. '.($confirmation!==''?'Open your ticket: '.$confirmation:''),
                'whatsapp_template'=>'payment_confirmed',
                'whatsapp_parameters'=>[$confirmation],
            ],
            'post_checkin'=>[
                'subject'=>'Welcome to the event',
                'html'=>'<h2>You are checked in</h2><p>Welcome. We hope you have a great event experience.</p>',
                'text'=>'You are checked in. Welcome to the event.',
                'whatsapp_template'=>'post_checkin',
                'whatsapp_parameters'=>[],
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
