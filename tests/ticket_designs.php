<?php
declare(strict_types=1);

use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Events\EventRepository;

$root=sys_get_temp_dir().'/digisangam-ticket-design-'.bin2hex(random_bytes(5));
mkdir($root,0700,true);
require dirname(__DIR__).'/app/bootstrap.php';

try {
    $events=new EventRepository(new JsonFileStore($root));
    $created=$events->create(['name'=>'Premium Ticket Tests','start_date'=>'2027-01-15','privacy'=>'public']);
    if(($created['ticket_design']??null)!=='editorial-mono') throw new RuntimeException('New events need a safe ticket design default.');
    foreach(['editorial-mono','grand-theatre-dark','neon-festival','commerce-blue','soft-luxe-wellness'] as $design){
        $updated=$events->update((string)$created['id'],['ticket_design'=>$design]);
        if(($updated['ticket_design']??null)!==$design) throw new RuntimeException('Ticket design was not persisted.');
        if(($events->find((string)$created['id'])['ticket_design']??null)!==$design) throw new RuntimeException('Ticket design reload mismatch.');
    }
    $rejected=false;
    try{$events->update((string)$created['id'],['ticket_design'=>'invalid-design']);}catch(InvalidArgumentException){$rejected=true;}
    if(!$rejected)throw new RuntimeException('Invalid design accepted.');
    if(($events->find((string)$created['id'])['ticket_design']??null)!=='soft-luxe-wellness')throw new RuntimeException('Invalid update changed persisted design.');
    echo "Ticket design persistence and validation passed.\n";
} finally {
    $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($items as $item)$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
    rmdir($root);
}
