<?php
declare(strict_types=1);
namespace DigiSangam\Printing;
final class LogPrintProvider implements PrintProviderInterface {
    public function send(array $job,string $document): array { return ['provider'=>'log','id'=>'print-log-'.($job['id']??''),'simulated'=>true,'bytes'=>strlen($document)]; }
}
