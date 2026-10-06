<?php
declare(strict_types=1);

namespace DigiSangam\Printing;

final class CupsPrintProvider implements PrintProviderInterface
{
    public function __construct(private readonly string $queue) {}

    public function send(array $job,string $document): array
    {
        if($this->queue==='') throw new \RuntimeException('CUPS printer queue is not configured.');
        if(!function_exists('exec')) throw new \RuntimeException('PHP exec is unavailable for CUPS printing.');
        $tmp=tempnam(sys_get_temp_dir(),'ds-print-');
        if($tmp===false) throw new \RuntimeException('Unable to create printer spool file.');
        file_put_contents($tmp,$document);
        try{
            $output=[];$code=1;
            $cmd='lp -d '.escapeshellarg($this->queue).' -o raw '.escapeshellarg($tmp).' 2>&1';
            exec($cmd,$output,$code);
            if($code!==0) throw new \RuntimeException('CUPS print failed: '.implode(' ',$output));
            $joined=implode(' ',$output);
            preg_match('/request id is ([^ ]+)/i',$joined,$m);
            return ['provider'=>'cups','id'=>$m[1]??('cups-'.bin2hex(random_bytes(5))),'simulated'=>false,'output'=>$joined];
        } finally { @unlink($tmp); }
    }
}
