<?php
declare(strict_types=1);

// HTTP HTML shell with public event metadata for Cloudways / Apache.
// Never read user-specific invitation or confirmation tokens here.
use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Events\EventRepository;

$privateRoot=trim((string)getenv('DIGISANGAM_PRIVATE_ROOT'));
$candidates=array_filter([
    $privateRoot!=='' ? rtrim($privateRoot,'/').'/app/bootstrap.php' : '',
    dirname(__DIR__).'/app/bootstrap.php',
    dirname(__DIR__,2).'/private_html/digisangam/app/bootstrap.php',
]);
$bootstrapFile='';
foreach($candidates as $candidate){if(is_file($candidate)){$bootstrapFile=$candidate;break;}}
if($bootstrapFile===''){
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'DigiSangam runtime unavailable';
    exit;
}
require $bootstrapFile;

$index=__DIR__.'/index.html';
if(!is_file($index)){
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'DigiSangam frontend unavailable';
    exit;
}
$eventId=trim((string)($_GET['event_id']??''));
$event=null;
if(preg_match('/^[A-Za-z0-9_-]{1,100}$/',$eventId)){
    $store=new JsonFileStore(DIGISANGAM_STORAGE_ROOT);
    $candidate=(new EventRepository($store))->find($eventId);
    if($candidate&&in_array((string)($candidate['status']??''),['Published','Live'],true)
        &&($candidate['privacy']??'public')==='public')$event=$candidate;
}
if(!$event)http_response_code(404);

$escape=static fn(string $v):string => htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$host=(string)($_SERVER['HTTP_HOST']??'');
if(!preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{2,5})?$/',$host))$host='';
$configuredOrigin=rtrim((string)getenv('DIGISANGAM_PUBLIC_ORIGIN'),'/');
$origin=$configuredOrigin!==''&&filter_var($configuredOrigin,FILTER_VALIDATE_URL)
    ? $configuredOrigin
    : ($host!=='' ? ((($_SERVER['HTTPS']??'')==='on'?'https':'http').'://'.$host) : '');
$canonical=$origin!=='' ? $origin.'/digisangam/e/'.rawurlencode($eventId) : '';
$title=(string)($event['public_page']['headline']??'');
if($title==='')$title=(string)($event['name']??'Event');
$description=(string)($event['description']??'');
if($description==='')$description='Discover and register for events with DigiSangam.';
$description=mb_substr($description,0,200,'UTF-8');
$image=(string)($event['branding']['cover_url']??$event['branding']['poster_url']??'');
if($image!==''&&!filter_var($image,FILTER_VALIDATE_URL))$image='';
$schema=null;
if($event && $canonical!==''){
    $schema=[
        '@context'=>'https://schema.org','@type'=>'Event','name'=>(string)$event['name'],
        'description'=>$description,'url'=>$canonical,
        'eventStatus'=>'https://schema.org/EventScheduled',
        'eventAttendanceMode'=>match((string)($event['format']??'in_person')){
            'virtual'=>'https://schema.org/OnlineEventAttendanceMode',
            'hybrid'=>'https://schema.org/MixedEventAttendanceMode',
            default=>'https://schema.org/OfflineEventAttendanceMode',
        },
    ];
    if(!empty($event['start_date']))$schema['startDate']=$event['start_date'];
    if(!empty($event['end_date']))$schema['endDate']=$event['end_date'];
    if(!empty($event['location'])||!empty($event['venue_name'])){
        $schema['location']=['@type'=>'Place','name'=>(string)($event['venue_name']?:$event['location']),'address'=>(string)($event['location']??'')];
    }
    if($image!=='')$schema['image']=$image;
    if(!empty($event['organizer']['name']))$schema['organizer']=['@type'=>'Organization','name'=>(string)$event['organizer']['name']];
}

$html=file_get_contents($index);
if($html===false)throw new RuntimeException('Unable to read frontend shell.');
$tags=[
    '<meta name="robots" content="'.($event?'index,follow':'noindex,nofollow').'">',
];
if($event){
    $tags[]='<meta name="description" content="'.$escape($description).'">';
    $tags[]='<meta property="og:title" content="'.$escape($title.' | DigiSangam').'">';
    $tags[]='<meta property="og:description" content="'.$escape($description).'">';
    $tags[]='<meta property="og:type" content="event">';
    if($image!=='')$tags[]='<meta property="og:image" content="'.$escape($image).'">';
    if($canonical!==''){
        $tags[]='<link rel="canonical" href="'.$escape($canonical).'">';
        $tags[]='<meta property="og:url" content="'.$escape($canonical).'">';
    }
    if($schema){
        $tags[]='<script type="application/ld+json">'.json_encode($schema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR).'</script>';
    }
}
$replacement='  <title>'.$escape($event?$title.' | DigiSangam':'Event unavailable | DigiSangam').'</title>';
$html=preg_replace('/<title>[^<]*<\/title>/i',$replacement,$html,1)??$html;
$html=str_replace('</head>',implode("\n",array_map(static fn(string $tag):string=>'  '.$tag,$tags))."\n</head>",$html);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=60, stale-while-revalidate=60');
header('X-Content-Type-Options: nosniff');
echo $html;
