<?php
declare(strict_types=1);

namespace DigiSangam\Core\EventJournal;

use DigiSangam\Core\Storage\JsonFileStore;

final class EventJournal
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function append(string $event, array $data): void
    {
        $path = 'journal/' . date('Y-m-d') . '.json';
        $entries = $this->store->read($path, []);
        $entries[] = ['id'=>'log_'.bin2hex(random_bytes(8)),'event'=>$event,'occurred_at'=>date(DATE_ATOM),'data'=>$data];
        $this->store->write($path, $entries);
    }
}
