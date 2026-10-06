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
            'email'=>strtolower(trim((string)($input['email'] ?? ''))),
            'category'=>trim((string)($input['category'] ?? 'General')),
            'status'=>'pending',
            'token'=>bin2hex(random_bytes(20)),
            'created_at'=>date(DATE_ATOM),
        ];
        $rows[] = $record;
        $this->store->write('invitations/index.json', $rows);
        return $record;
    }
}
