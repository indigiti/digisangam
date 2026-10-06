<?php
declare(strict_types=1);

namespace DigiSangam\PublicFlow;

use DigiSangam\Core\Storage\JsonFileStore;

final class PublicRequestGuard
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function enforce(string $scope, int $limit = 20, int $windowSeconds = 600): void
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = hash('sha256', $scope . '|' . $ip);
        $now = time();

        $result = $this->store->transaction(
            'ratelimits/' . $key . '.json',
            static function(array $state) use ($now,$limit,$windowSeconds): array {
                $started=(int)($state['started_at'] ?? 0);
                $count=(int)($state['count'] ?? 0);
                if ($started === 0 || ($now - $started) >= $windowSeconds) {
                    $started=$now; $count=0;
                }
                $count++;
                $next=['started_at'=>$started,'count'=>$count,'expires_at'=>$started+$windowSeconds];
                return ['data'=>$next,'result'=>['allowed'=>$count <= $limit,'retry_after'=>max(1,($started+$windowSeconds)-$now)]];
            },
            []
        );

        if (empty($result['allowed'])) {
            header('Retry-After: ' . (int)$result['retry_after']);
            throw new \RuntimeException('Too many registration attempts. Please try again later.');
        }
    }
}
