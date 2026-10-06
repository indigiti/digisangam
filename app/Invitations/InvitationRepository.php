<?php
declare(strict_types=1);

namespace DigiSangam\Invitations;

use DigiSangam\Core\Storage\JsonFileStore;

final class InvitationRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('invitations/index.json', []);
    }

    public function create(array $input): array
    {
        $rows = $this->all();
        $record = [
            'id'=>'inv_'.bin2hex(random_bytes(6)),
            'event_id'=>trim((string)($input['event_id'] ?? '')),
            'event_id'=>trim((string)($input['event_id'] ?? '')),
            'email'=>strtolower(trim((string)($input['email'] ?? ''))),
            'category'=>trim((string)($input['category'] ?? 'General')),
            'status'=>'pending',
            'token'=>bin2hex(random_bytes(20)),
            'created_at'=>date(DATE_ATOM),
        ];
        if($record['event_id']==='') throw new \InvalidArgumentException('Event is required.');
        if(!filter_var($record['email'], FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Valid email is required.');
        if($record['event_id']==='') throw new \InvalidArgumentException('Event is required.');
        if(!filter_var($record['email'], FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Valid email is required.');
        $rows[] = $record;
        $this->store->write('invitations/index.json', $rows);
        return $record;
    }
}
