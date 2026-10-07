<?php
declare(strict_types=1);

namespace DigiSangam\Wallet;

use DigiSangam\Core\Storage\JsonFileStore;

final class WalletPassRepository
{
    private const PATH='wallet/passes.json';
    public function __construct(private readonly JsonFileStore $store) {}
    public function all(): array{return $this->store->read(self::PATH,[]);}
    public function find(string $id): ?array{foreach($this->all() as $row)if(($row['id']??'')===$id)return $row;return null;}

    public function create(array $record): array
    {
        return $this->store->transaction(self::PATH,static function(array $rows) use ($record): array {
            array_unshift($rows,$record);return ['data'=>$rows,'result'=>$record];
        },[]);
    }

    public function update(string $id,array $input): ?array
    {
        return $this->store->transaction(self::PATH,static function(array $rows) use ($id,$input): array {
            $updated=null;
            foreach($rows as &$row){
                if(($row['id']??'')!==$id)continue;
                $row=array_merge($row,$input,['updated_at'=>date(DATE_ATOM)]);$updated=$row;break;
            }
            unset($row);return ['data'=>$rows,'result'=>$updated];
        },[]);
    }
}
