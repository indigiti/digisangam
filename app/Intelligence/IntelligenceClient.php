<?php
declare(strict_types=1);

namespace DigiSangam\Intelligence;

final class IntelligenceClient
{
    private readonly LocalIntelligenceEngine $fallback;

    public function __construct(
        private readonly string $baseUrl='',
        private readonly string $token='',
    ) {
        $this->fallback=new LocalIntelligenceEngine();
    }

    public function analyze(array $graph): array
    {
        return $this->request('/v1/analyze',['graph'=>$graph]) ?? $this->fallback->analyze($graph);
    }

    public function copilot(string $question,array $graph): array
    {
        return $this->request('/v1/copilot',['question'=>$question,'graph'=>$graph]) ?? $this->fallback->copilot($question,$graph);
    }

    public function eventBuilder(string $prompt): array
    {
        return $this->request('/v1/event-builder',['prompt'=>$prompt]) ?? (new EventBlueprintService())->build($prompt);
    }

    public function concierge(string $question,string $attendeeId,array $graph): array
    {
        return $this->request('/v1/concierge',['question'=>$question,'attendee_id'=>$attendeeId,'graph'=>$graph]) ?? $this->fallback->concierge($question,$attendeeId,$graph);
    }

    private function request(string $path,array $payload): ?array
    {
        $base=rtrim(trim($this->baseUrl),'/');
        if($base==='' || !function_exists('curl_init')) return null;

        $headers=['Content-Type: application/json'];
        if($this->token!=='') $headers[]='Authorization: Bearer '.$this->token;

        $ch=curl_init($base.$path);
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>2,
            CURLOPT_TIMEOUT=>8,
            CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        ]);
        $raw=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $error=curl_error($ch);
        curl_close($ch);

        if($raw===false || $error!=='' || $status<200 || $status>=300) return null;
        $decoded=json_decode((string)$raw,true);
        return is_array($decoded)?$decoded:null;
    }
}
