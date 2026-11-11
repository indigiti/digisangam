<?php
declare(strict_types=1);

use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Events\EventRepository;
use DigiSangam\Tickets\TicketRepository;
use DigiSangam\PublicFlow\PublicDiscoveryService;

require dirname(__DIR__).'/app/bootstrap.php';

$root=sys_get_temp_dir().'/digisangam-discovery-'.bin2hex(random_bytes(5));
mkdir($root,0700,true);
function discoveryAssert(bool $condition,string $message): void {
    if(!$condition)throw new RuntimeException($message);
}

try{
    $store=new JsonFileStore($root);
    $events=new EventRepository($store);
    $tickets=new TicketRepository($store);
    $service=new PublicDiscoveryService($events,$tickets);
    $year=(int)date('Y')+2;
    $pune=$events->create(['name'=>'Mountain Music Night','description'=>'A live concert','category'=>'Music',
        'start_date'=>$year.'-03-20','end_date'=>$year.'-03-21','location'=>'Pune, Maharashtra, India',
        'venue_name'=>'Valley Auditorium','privacy'=>'public']);
    $mumbai=$events->create(['name'=>'Ceramics Workshop','description'=>'Make pottery together','category'=>'Workshops',
        'start_date'=>$year.'-02-10','location'=>'Mumbai, Maharashtra, India','venue_name'=>'Art Studio','privacy'=>'public']);
    $hidden=$events->create(['name'=>'Secret Music Evening','category'=>'Music','start_date'=>$year.'-02-11','location'=>'Pune','privacy'=>'private']);
    $draft=$events->create(['name'=>'Unpublished Concert','category'=>'Music','start_date'=>$year.'-02-09','location'=>'Delhi','privacy'=>'public']);
    $events->update($pune['id'],['status'=>'Published']);
    $events->update($mumbai['id'],['status'=>'Live']);
    $events->update($hidden['id'],['status'=>'Published']);
    $tickets->create(['event_id'=>$pune['id'],'name'=>'General','price'=>1200,'quantity'=>20]);
    $tickets->create(['event_id'=>$mumbai['id'],'name'=>'Workshop pass','price'=>350,'quantity'=>10]);

    $all=$service->browse();
    discoveryAssert(count($all['events'])===2 && $all['total']===2,'Public catalogue leaked hidden or draft events.');
    discoveryAssert($all['cities']===['Mumbai','Pune'],'Discovery cities must be derived from public records.');
    discoveryAssert(in_array('Music',$all['categories'],true) && in_array('Workshops',$all['categories'],true),'Public category facets missing.');
    discoveryAssert(count($service->browse(['city'=>'Pune'])['events'])===1,'City filtering failed.');
    discoveryAssert($service->browse(['city'=>'Pune'])['events'][0]['id']===$pune['id'],'City filter returned incorrect event.');
    discoveryAssert($service->browse(['q'=>'valley auditorium'])['events'][0]['id']===$pune['id'],'Venue search failed.');
    discoveryAssert(count($service->browse(['q'=>'secret'])['events'])===0,'Private event exposed by public search.');
    discoveryAssert(count($service->browse(['category'=>'Music'])['events'])===1,'Category filtering failed.');
    discoveryAssert(count($service->browse(['max_price'=>500])['events'])===1,'Maximum price filter failed.');
    discoveryAssert(count($service->browse(['min_price'=>1000])['events'])===1,'Minimum price filter failed.');
    discoveryAssert($service->browse(['sort'=>'price_desc'])['events'][0]['id']===$pune['id'],'Price sorting failed.');
    discoveryAssert(count($service->browse(['date'=>'upcoming'])['events'])===2,'Upcoming window failed.');
    $first=$service->browse(['per_page'=>1,'page'=>1,'sort'=>'name']);
    $second=$service->browse(['per_page'=>1,'page'=>2,'sort'=>'name']);
    discoveryAssert($first['total']===2 && $first['has_more'] && !$second['has_more'],'Pagination metadata incorrect.');
    discoveryAssert($first['events'][0]['id']!==$second['events'][0]['id'],'Pagination returned duplicate event.');
    discoveryAssert(count($service->browse(['max_price'=>-100])['events'])===0,'Negative price input should safely clamp to zero.');
    discoveryAssert(count($service->browse(['city'=>'Unknown'])['events'])===0,'Unknown city must not match.');
    echo "Public discovery filtering, privacy, facets and pagination passed.\n";
}finally{
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $item)$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
    rmdir($root);
}
