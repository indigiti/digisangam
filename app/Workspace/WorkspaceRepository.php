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
        $current = $this->current();
        foreach (['name','brand','legal_name','gstin','billing_address','timezone','currency','country'] as $key) {
            if (array_key_exists($key, $input)) $current[$key] = trim((string)$input[$key]);
        }
        $current['updated_at'] = date(DATE_ATOM);
        $this->store->write('workspaces/ws_default.json', $current);
        return $current;
    }
}
