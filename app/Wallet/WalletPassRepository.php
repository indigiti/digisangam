<?php
declare(strict_types=1);

namespace DigiSangam\Wallet;

use DigiSangam\Core\Storage\JsonFileStore;

final class WalletPassRepository
{
    public function __construct(private readonly JsonFileStore $store) {}
    public function all(): array { return $this->store->read('wallet/passes.json',[]); }
    public function find(string $id): ?array { foreach($this->all() as $row) if(($row['id']??'')===$id)return $row; return null; }
    public function create(array $record): array {
        $rows=$this->all();array_unshift($rows,$record);$this->store->write('wallet/passes.json',$rows);return $record;
    }
    public function update(string $id,array $input): ?array {
        $rows=$this->all();$updated=null;
        foreach($rows as &$row){if(($row['id']??'')!==$id)continue;$row=array_merge($row,$input,['updated_at'=>date(DATE_ATOM)]);$updated=$row;break;}unset($row);
        if($updated)$this->store->write('wallet/passes.json',$rows);return $updated;
    }
}
