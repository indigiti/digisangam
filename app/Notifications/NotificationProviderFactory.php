<?php
declare(strict_types=1);

namespace DigiSangam\Notifications;

final class NotificationProviderFactory
{
    public static function email(): EmailProviderInterface
    {
        return match(strtolower(trim((string)getenv('DIGISANGAM_EMAIL_PROVIDER')))){
            'mail'=>new PhpMailEmailProvider((string)getenv('MAIL_FROM_ADDRESS'),(string)getenv('MAIL_FROM_NAME')),
            'smtp'=>new SmtpEmailProvider(
                (string)getenv('SMTP_HOST'),
                max(1,(int)(getenv('SMTP_PORT')?:587)),
                strtolower((string)(getenv('SMTP_ENCRYPTION')?:'tls')),
                (string)getenv('SMTP_USERNAME'),
                (string)getenv('SMTP_PASSWORD'),
                (string)getenv('MAIL_FROM_ADDRESS'),
                (string)getenv('MAIL_FROM_NAME'),
            ),
            default=>new LogEmailProvider(),
        };
    }

    public static function whatsapp(): WhatsAppProviderInterface
    {
        return strtolower(trim((string)getenv('DIGISANGAM_WHATSAPP_PROVIDER')))==='meta'
            ? new MetaWhatsAppProvider(
                (string)getenv('WHATSAPP_PHONE_NUMBER_ID'),
                (string)getenv('WHATSAPP_ACCESS_TOKEN'),
                (string)(getenv('WHATSAPP_TEMPLATE_LANGUAGE')?:'en'),
            )
            : new LogWhatsAppProvider();
    }
}
