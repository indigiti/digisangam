<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/bootstrap.php';

use DigiSangam\Core\Storage\JsonFileStore;

$root=sys_get_temp_dir().'/digisangam-event-seo-'.bin2hex(random_bytes(6));
$publicIndex=dirname(__DIR__).'/public/index.html';
$exists=is_file($publicIndex);
$original=$exists?file_get_contents($publicIndex):null;
mkdir($root,0700,true);
putenv('DIGISANGAM_STORAGE_ROOT='.$root);
putenv('DIGISANGAM_PUBLIC_ORIGIN=https://events.example.test');
$_SERVER['HTTP_HOST']='events.example.test';
$_SERVER['HTTPS']='on';

try{
    // The PHP CI job runs without a Vite build; use a minimal local template.
    file_put_contents($publicIndex,'<!doctype html><html><head><title>DigiSangam</title></head><body><div id="app"></div></body></html>');
    $store=new JsonFileStore($root);
    $store->write('events/index.json',[
        [
            'id'=>'evt_public','name'=>'Trusted public expo',
            'status'=>'Published','privacy'=>'public','format'=>'in_person',
            'start_date'=>'2027-01-15','end_date'=>'2027-01-16',
            'location'=>'Pune, India','venue_name'=>'Expo Centre',
            'description'=>'Experience live demos </script><script>alert(1)</script> & exhibits',
            'branding'=>['cover_url'=>'https://example.test/banner.webp'],
            'organizer'=>['name'=>'Test Organizer'],
        ],
        [
            'id'=>'evt_secret','name'=>'Private investor event',
            'status'=>'Draft','privacy'=>'private','description'=>'Sensitive private details',
        ],
    ]);
    $_GET['event_id']='evt_public';
    ob_start();include dirname(__DIR__).'/public/event-meta.php';$html=ob_get_clean();
    if(!str_contains($html,'<title>Trusted public expo | DigiSangam</title>'))throw new RuntimeException('SSR event title missing.');
    if(!str_contains($html,'rel="canonical" href="https://events.example.test/digisangam/e/evt_public"'))throw new RuntimeException('Canonical event URL missing.');
    if(!str_contains($html,'application/ld+json'))throw new RuntimeException('Event structured data missing.');
    if(!str_contains($html,'name="robots" content="index,follow"'))throw new RuntimeException('Published event indexing flag missing.');
    if(str_contains($html,'<script>alert(1)</script>'))throw new RuntimeException('Untrusted script in event description.');
    if(!str_contains($html,'\\u003C/script\\u003E'))throw new RuntimeException('Structured data content was not escaped.');
    http_response_code(200);
    $_GET['event_id']='evt_secret';
    ob_start();include dirname(__DIR__).'/public/event-meta.php';$hidden=ob_get_clean();
    if(http_response_code()!==404)throw new RuntimeException('Private event must not return HTTP 200.');
    if(!str_contains($hidden,'noindex,nofollow'))throw new RuntimeException('Private event must be noindex.');
    if(str_contains($hidden,'Private investor event')||str_contains($hidden,'Sensitive private details'))throw new RuntimeException('Private event data leaked into the public HTML.');
    echo "Public event HTML SEO rendering checks passed.\n";
} finally {
    if($exists&&$original!==false)file_put_contents($publicIndex,$original);
    else @unlink($publicIndex);
    $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($items as $item) $item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
    rmdir($root);
    putenv('DIGISANGAM_STORAGE_ROOT');
    putenv('DIGISANGAM_PUBLIC_ORIGIN');
}
