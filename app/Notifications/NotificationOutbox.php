<?php
declare(strict_types=1);

namespace DigiSangam\Notifications;

use DigiSangam\Core\Storage\JsonFileStore;

final class NotificationOutbox
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function queue(string $channel, string $template, array $recipient, array $data): array
    {
        $record = [
            'id'=>'msg_'.bin2hex(random_bytes(6)),
            'channel'=>$channel,
            'template'=>$template,
            'recipient'=>$recipient,
            'data'=>$data,
            'status'=>'queued',
            'created_at'=>date(DATE_ATOM),
        ];
        $this->store->transaction('notifications/outbox.json', static function(array $rows) use ($record): array {
            array_unshift($rows, $record);
            return ['data'=>$rows,'result'=>$record];
        }, []);
        return $record;
    }
}
