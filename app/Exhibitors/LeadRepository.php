<?php
declare(strict_types=1);

namespace DigiSangam\Exhibitors;

use DigiSangam\Core\Storage\JsonFileStore;

final class LeadRepository
{
    private const PATH='exhibitors/leads.json';
    public function __construct(private readonly JsonFileStore $store) {}
    public function all(): array{return $this->store->read(self::PATH,[]);}

    public function create(array $input): array
    {
        $record=[
            'id'=>'lead_'.bin2hex(random_bytes(6)),
            'event_id'=>trim((string)($input['event_id']??'')),
            'exhibitor_id'=>(string)($input['exhibitor_id']??''),
            'attendee_id'=>(string)($input['attendee_id']??''),
            'score'=>max(0,min(100,(int)($input['score']??50))),
            'intent'=>(string)($input['intent']??'warm'),
            'notes'=>trim((string)($input['notes']??'')),
            'owner'=>(string)($input['owner']??''),
            'created_at'=>date(DATE_ATOM),
        ];
        if($record['event_id']==='')throw new \InvalidArgumentException('Event is required.');
        if($record['exhibitor_id']===''||$record['attendee_id']==='')throw new \InvalidArgumentException('Exhibitor and attendee are required.');
        return $this->store->transaction(self::PATH,static function(array $rows) use ($record): array {
            array_unshift($rows,$record);return ['data'=>$rows,'result'=>$record];
        },[]);
    }
}
