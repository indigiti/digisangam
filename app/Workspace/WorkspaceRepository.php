<?php
declare(strict_types=1);

namespace DigiSangam\Workspace;

use DigiSangam\Core\Storage\JsonFileStore;

final class WorkspaceRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function current(): array
    {
        return $this->store->read('workspaces/ws_default.json', [
            'id'=>'ws_default',
            'name'=>'DigiSangam Workspace',
            'brand'=>'DigiSangam',
            'legal_name'=>'',
            'gstin'=>'',
            'billing_address'=>'',
            'timezone'=>'Asia/Kolkata',
            'currency'=>'INR',
            'country'=>'IN',
            'created_at'=>date(DATE_ATOM)
        ]);
    }

    public function update(array $input): array
    {
        return $this->store->transaction('workspaces/ws_default.json',static function(array $current) use ($input): array {
            $current=array_merge([
                'id'=>'ws_default','name'=>'DigiSangam Workspace','brand'=>'DigiSangam','legal_name'=>'','gstin'=>'',
                'billing_address'=>'','timezone'=>'Asia/Kolkata','currency'=>'INR','country'=>'IN','created_at'=>date(DATE_ATOM),
            ],$current);
            foreach (['name','brand','legal_name','gstin','billing_address','timezone','currency','country'] as $key) {
                if (array_key_exists($key, $input)) $current[$key] = trim((string)$input[$key]);
            }
            if($current['name']==='') throw new \InvalidArgumentException('Workspace name is required.');
            try{new \DateTimeZone($current['timezone']?:'Asia/Kolkata');}catch(\Throwable){throw new \InvalidArgumentException('Invalid workspace timezone.');}
            $current['currency']=strtoupper($current['currency']);
            if(!preg_match('/^[A-Z]{3}$/',$current['currency'])) throw new \InvalidArgumentException('Workspace currency must use a three-letter code.');
            $current['country']=strtoupper($current['country']);
            if(!preg_match('/^[A-Z]{2}$/',$current['country'])) throw new \InvalidArgumentException('Workspace country must use a two-letter code.');
            $current['updated_at'] = date(DATE_ATOM);
            return ['data'=>$current,'result'=>$current];
        },[]);
    }
}
