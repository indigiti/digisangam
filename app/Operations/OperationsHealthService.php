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
            'generated_at'=>date(DATE_ATOM),
        ];
    }
}
