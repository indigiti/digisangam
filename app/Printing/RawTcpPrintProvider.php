<?php
declare(strict_types=1);
namespace DigiSangam\Printing;
final class RawTcpPrintProvider implements PrintProviderInterface {
    public function __construct(private readonly string $host,private readonly int $port=9100) {}
    public function send(array $job,string $document): array {
        if($this->host==='')throw new \RuntimeException('Printer host is not configured.');
        $errno=0;$err='';$fp=@fsockopen($this->host,$this->port,$errno,$err,5);
        if(!$fp)throw new \RuntimeException('Printer connection failed: '.$err);
        stream_set_timeout($fp,5);$written=fwrite($fp,$document);fclose($fp);
        if($written===false)throw new \RuntimeException('Printer write failed.');
        return ['provider'=>'raw_tcp','id'=>'tcp-'.bin2hex(random_bytes(5)),'simulated'=>false,'bytes'=>$written];
    }
}
