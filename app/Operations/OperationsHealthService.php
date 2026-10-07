<?php
declare(strict_types=1);

namespace DigiSangam\Operations;

use DigiSangam\Core\Storage\JsonFileStore;

final class OperationsHealthService
{
    public function __construct(private readonly JsonFileStore $store,private readonly WorkerHeartbeatRepository $heartbeats) {}

    public function status(): array
    {
        $beats=$this->heartbeats->all();
        $workers=[];
        foreach([
            'notifications'=>300,
            'webhooks'=>300,
            'badge-print'=>300,
            'order-expiry'=>300,
            'media-cleanup'=>3600,
        ] as $name=>$defaultInterval){
            $beat=$beats[$name]??null;
            $expected=(int)($beat['expected_interval_seconds']??$defaultInterval);
            $last=$beat?strtotime((string)($beat['last_run_at']??'')):false;
            $age=$last===false?null:max(0,time()-$last);
            $state=$last===false?'never_run':($age>$expected*3?'stale':'healthy');
            $workers[]=[
                'name'=>$name,'state'=>$state,'last_run_at'=>$beat['last_run_at']??null,
                'age_seconds'=>$age,'expected_interval_seconds'=>$expected,'result'=>$beat['result']??null,
            ];
        }

        $notifications=$this->store->read('notifications/outbox.json',[]);
        $webhooks=$this->store->read('developer/webhook-outbox.json',[]);
        $prints=$this->store->read('badges/print-queue.json',[]);
        $orders=$this->store->read('orders/index.json',[]);

        $count=static fn(array $rows,array $statuses): int => count(array_filter($rows,static fn(array $row): bool => in_array((string)($row['status']??''),$statuses,true)));
        $expiredReservations=count(array_filter($orders,static function(array $row): bool {
            if(($row['status']??'')!=='pending'||empty($row['reservation_expires_at']))return false;
            $time=strtotime((string)$row['reservation_expires_at']);
            return $time!==false&&$time<=time();
        }));

        return [
            'workers'=>$workers,
            'queues'=>[
                'notifications'=>['pending'=>$count($notifications,['queued','retry']),'failed'=>$count($notifications,['failed'])],
                'webhooks'=>['pending'=>$count($webhooks,['queued','retry']),'failed'=>$count($webhooks,['failed'])],
                'badge_prints'=>['pending'=>$count($prints,['queued']),'failed'=>$count($prints,['failed'])],
                'expired_payment_reservations'=>['pending_cleanup'=>$expiredReservations],
            ],
            'providers'=>$this->providerReadiness(),
            'generated_at'=>date(DATE_ATOM),
        ];
    }

    private function providerReadiness(): array
    {
        $payment=strtolower(trim((string)getenv('DIGISANGAM_PAYMENT_PROVIDER'))) ?: 'manual';
        $email=strtolower(trim((string)getenv('DIGISANGAM_EMAIL_PROVIDER'))) ?: 'log';
        $whatsapp=strtolower(trim((string)getenv('DIGISANGAM_WHATSAPP_PROVIDER'))) ?: 'log';
        $sms=strtolower(trim((string)getenv('DIGISANGAM_SMS_PROVIDER'))) ?: 'log';
        $print=strtolower(trim((string)getenv('DIGISANGAM_PRINT_PROVIDER'))) ?: 'log';

        $row=static function(string $name,string $mode,bool $ready,bool $live,string $detail): array {
            return [
                'name'=>$name,
                'mode'=>$mode,
                'ready'=>$ready,
                'live'=>$live,
                'state'=>!$ready?'needs_config':($live?'live':'fallback'),
                'detail'=>$detail,
            ];
        };

        $paymentReady=$payment==='razorpay'
            ? trim((string)getenv('RAZORPAY_KEY_ID'))!=='' && trim((string)getenv('RAZORPAY_KEY_SECRET'))!=='' && trim((string)getenv('RAZORPAY_WEBHOOK_SECRET'))!==''
            : $payment==='manual';
        $emailReady=match($email){
            'smtp'=>trim((string)getenv('SMTP_HOST'))!=='' && trim((string)getenv('MAIL_FROM_ADDRESS'))!=='',
            'mail'=>trim((string)getenv('MAIL_FROM_ADDRESS'))!=='',
            default=>$email==='log',
        };
        $waReady=$whatsapp==='meta'
            ? trim((string)getenv('WHATSAPP_PHONE_NUMBER_ID'))!=='' && trim((string)getenv('WHATSAPP_ACCESS_TOKEN'))!==''
            : $whatsapp==='log';
        $smsReady=$sms==='http'
            ? trim((string)getenv('SMS_HTTP_ENDPOINT'))!==''
            : $sms==='log';
        $printReady=match($print){
            'raw_tcp'=>trim((string)getenv('PRINTER_HOST'))!=='',
            'cups'=>trim((string)getenv('PRINTER_QUEUE'))!=='',
            default=>$print==='log',
        };

        $aiUrl=trim((string)getenv('DIGISANGAM_INTELLIGENCE_URL'));
        $aiToken=trim((string)getenv('DIGISANGAM_INTELLIGENCE_TOKEN'));
        $apple=trim((string)getenv('APPLE_WALLET_PASS_BASE_URL'));
        $google=trim((string)getenv('GOOGLE_WALLET_SAVE_BASE_URL'));

        return [
            $row('Payments',$payment,$paymentReady,$payment==='razorpay'&&$paymentReady,$payment==='manual'?'Manual settlement only.':'Razorpay checkout + webhook verification.'),
            $row('Email',$email,$emailReady,in_array($email,['smtp','mail'],true)&&$emailReady,$email==='log'?'Messages are written to the log provider.':'External email delivery.'),
            $row('WhatsApp',$whatsapp,$waReady,$whatsapp==='meta'&&$waReady,$whatsapp==='log'?'Messages are written to the log provider.':'Meta WhatsApp Cloud delivery.'),
            $row('SMS',$sms,$smsReady,$sms==='http'&&$smsReady,$sms==='log'?'Messages are written to the log provider.':'External HTTP SMS delivery.'),
            $row('Badge printing',$print,$printReady,in_array($print,['raw_tcp','cups'],true)&&$printReady,$print==='log'?'Print jobs are simulated/logged.':'Physical printer delivery.'),
            $row('Apple Wallet',$apple!==''?'provider_url':'disabled',true,$apple!=='',$apple!==''?'Provider handoff configured.':'Pass provider URL not configured.'),
            $row('Google Wallet',$google!==''?'provider_url':'disabled',true,$google!=='',$google!==''?'Provider handoff configured.':'Save-link provider URL not configured.'),
            $row('AI service',$aiUrl!==''?'remote':'local_fallback',true,$aiUrl!==''&&$aiToken!=='',$aiUrl!==''?($aiToken!==''?'Remote intelligence service configured.':'Remote URL set but internal token is missing.'):'Local PHP intelligence fallback is active.'),
        ];
    }
}
