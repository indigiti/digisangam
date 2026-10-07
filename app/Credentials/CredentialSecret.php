<?php
declare(strict_types=1);

namespace DigiSangam\Credentials;

use DigiSangam\Core\Storage\JsonFileStore;

final class CredentialSecret
{
    public static function resolve(JsonFileStore $store): string
    {
        $configured=trim((string)getenv('DIGISANGAM_CREDENTIAL_SECRET'));
        if($configured!=='') return $configured;

        return $store->transaction('secrets/credential.json',static function(array $record): array {
            $secret=trim((string)($record['secret']??''));
            if($secret===''){
                $secret=bin2hex(random_bytes(32));
                $record=['secret'=>$secret,'created_at'=>date(DATE_ATOM)];
            }
            return ['data'=>$record,'result'=>$secret];
        },[]);
    }
}
