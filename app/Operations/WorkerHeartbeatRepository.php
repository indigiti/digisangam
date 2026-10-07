<?php
declare(strict_types=1);

namespace DigiSangam\Operations;

use DigiSangam\Core\Storage\JsonFileStore;

final class WorkerHeartbeatRepository
{
    private const PATH='operations/worker-heartbeats.json';

    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array { return $this->store->read(self::PATH,[]); }

    public function beat(string $worker,array $result=[],int $expectedIntervalSeconds=300): array
    {
        $worker=trim($worker);
        if($worker==='') throw new \InvalidArgumentException('Worker name is required.');
        $record=[
            'worker'=>$worker,'last_run_at'=>date(DATE_ATOM),
            'expected_interval_seconds'=>max(60,$expectedIntervalSeconds),
            'result'=>$result,
        ];
        return $this->store->transaction(self::PATH,static function(array $rows) use ($worker,$record): array {
            $rows[$worker]=$record;return ['data'=>$rows,'result'=>$record];
        },[]);
    }
}
