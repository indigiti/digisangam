<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$release=$root.'/release';

$required=[
    $release.'/public/index.html'=>'PUBLIC_INDEX_MISSING',
    $release.'/public/.htaccess'=>'PUBLIC_HTACCESS_MISSING',
    $release.'/public/api/index.php'=>'PUBLIC_API_MISSING',
    $release.'/public/manifest.webmanifest'=>'PUBLIC_MANIFEST_MISSING',
    $release.'/public/sw.js'=>'PUBLIC_SERVICE_WORKER_MISSING',
    $release.'/private/app/bootstrap.php'=>'PRIVATE_BOOTSTRAP_MISSING',
    $release.'/private/scripts/notifications.php'=>'PRIVATE_NOTIFICATION_WORKER_MISSING',
    $release.'/private/scripts/webhooks.php'=>'PRIVATE_WEBHOOK_WORKER_MISSING',
    $release.'/private/scripts/badge-print.php'=>'PRIVATE_BADGE_PRINT_WORKER_MISSING',
    $release.'/private/intelligence/engine.py'=>'PRIVATE_INTELLIGENCE_ENGINE_MISSING',
    $release.'/private/intelligence/app.py'=>'PRIVATE_INTELLIGENCE_API_MISSING',
    $release.'/private/build/release.json'=>'BUILD_MANIFEST_MISSING',
    $release.'/RELEASE.json'=>'RELEASE_MANIFEST_MISSING',
];

foreach($required as $file=>$error){
    if(!is_file($file)){
        fwrite(STDERR,$error.': '.$file.PHP_EOL);
        exit(1);
    }
}

foreach([
    $release.'/public/storage',
    $release.'/public/.env',
    $release.'/private/.env',
    $release.'/private/storage',
] as $forbidden){
    if(file_exists($forbidden)){
        fwrite(STDERR,'FORBIDDEN_RELEASE_PATH: '.$forbidden.PHP_EOL);
        exit(1);
    }
}

$index=(string)file_get_contents($release.'/public/index.html');
if(!preg_match('#(?:src|href)="/digisangam/(?:assets|manifest\.webmanifest)#',$index)){
    fwrite(STDERR,'DIGISANGAM_ASSET_BASE_MISSING'.PHP_EOL);
    exit(1);
}

$ht=(string)file_get_contents($release.'/public/.htaccess');
foreach([
    'RewriteBase /digisangam/',
    'RewriteRule ^api/v1/?$ api/index.php?path= [QSA,L]',
    'RewriteRule ^ index.html [L]',
] as $needle){
    if(!str_contains($ht,$needle)){
        fwrite(STDERR,'HTACCESS_RULE_MISSING: '.$needle.PHP_EOL);
        exit(1);
    }
}

$api=(string)file_get_contents($release.'/public/api/index.php');
foreach(['DIGISANGAM_PRIVATE_ROOT','private_html/digisangam','DIGISANGAM_STORAGE_ROOT'] as $needle){
    if(!str_contains($api,$needle)){
        fwrite(STDERR,'SPLIT_RUNTIME_CONTRACT_MISSING: '.$needle.PHP_EOL);
        exit(1);
    }
}

$manifest=json_decode((string)file_get_contents($release.'/private/build/release.json'),true);
if(!is_array($manifest)
    || ($manifest['schema']??'')!=='DIGIOPS-RELEASE/1'
    || ($manifest['name']??'')!=='DigiSangam'
    || ($manifest['version']??'')!=='3.1.0'
    || ($manifest['publicPath']??'')!=='public_html/digisangam/'
    || ($manifest['privatePath']??'')!=='private_html/digisangam/'
){
    fwrite(STDERR,'BUILD_MANIFEST_INVALID'.PHP_EOL);
    exit(1);
}

$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($release,FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file){
    if($file->isLink()){
        fwrite(STDERR,'SYMLINK_NOT_ALLOWED: '.$file->getPathname().PHP_EOL);
        exit(1);
    }
}

echo "DigiSangam DigiOps release verification: PASS".PHP_EOL;
