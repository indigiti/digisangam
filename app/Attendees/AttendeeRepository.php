<?php
declare(strict_types=1);

namespace DigiSangam\Attendees;

use DigiSangam\Core\Storage\JsonFileStore;

final class AttendeeRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('attendees/index.json', self::demo());
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $row) if (($row['id'] ?? '') === $id) return $row;
        return null;
    }

    public function create(array $input): array
    {
        $rows = $this->all();
        $name = trim((string)($input['name'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Name and a valid email are required.');
        }
        foreach ($rows as $existing) {
            if (strtolower((string)($existing['email'] ?? '')) === $email) {
                throw new \InvalidArgumentException('An attendee with this email already exists.');
            }
        }
        $record = [
            'id'=>'TKT'.strtoupper(bin2hex(random_bytes(5))),
            'name'=>$name,
            'initials'=>$this->initials($name),
            'category'=>trim((string)($input['category'] ?? 'General')),
            'company'=>trim((string)($input['company'] ?? '')),
            'status'=>ApprovalService::normalize((string)($input['status'] ?? 'Pending')),
            'email'=>$email,
            'phone'=>trim((string)($input['phone'] ?? '')),
            'event_id'=>(string)($input['event_id'] ?? 'evt_001'),
            'created_at'=>date(DATE_ATOM),
        ];
        array_unshift($rows, $record);
        $this->store->write('attendees/index.json', $rows);
        return $record;
    }

    public function update(string $id, array $input): ?array
    {
        $rows = $this->all();
        $updated = null;
        foreach ($rows as &$row) {
            if (($row['id'] ?? '') !== $id) continue;
            foreach (['name','category','company','email','phone'] as $field) {
                if (array_key_exists($field, $input)) $row[$field] = trim((string)$input[$field]);
            }
            if (isset($input['status'])) $row['status'] = ApprovalService::normalize((string)$input['status']);
            if (isset($input['name'])) $row['initials'] = $this->initials((string)$row['name']);
            $row['updated_at'] = date(DATE_ATOM);
            $updated = $row;
            break;
        }
        unset($row);
        if ($updated !== null) $this->store->write('attendees/index.json', $rows);
        return $updated;
    }

    public function import(array $rows): array
    {
        $created = 0; $skipped = 0; $errors = [];
        foreach ($rows as $index => $row) {
            try { $this->create($row); $created++; }
            catch (\Throwable $e) { $skipped++; $errors[] = ['row'=>$index + 2,'error'=>$e->getMessage()]; }
        }
        return ['created'=>$created,'skipped'=>$skipped,'errors'=>$errors];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        return strtoupper(substr((string)($parts[0] ?? ''),0,1).substr((string)($parts[count($parts)-1] ?? ''),0,1));
    }

    private static function demo(): array
    {
        return [
            ['id'=>'TKT20394823','name'=>'Rohit Sharma','initials'=>'RS','category'=>'VIP','company'=>'Google','status'=>'Confirmed','email'=>'rohit@example.test','event_id'=>'evt_001'],
            ['id'=>'TKT20394824','name'=>'Priya Mehta','initials'=>'PM','category'=>'General','company'=>'Infosys','status'=>'Confirmed','email'=>'priya@example.test','event_id'=>'evt_001'],
            ['id'=>'TKT20394825','name'=>'Amit Kumar','initials'=>'AK','category'=>'Sponsor','company'=>'Microsoft','status'=>'Pending','email'=>'amit@example.test','event_id'=>'evt_001'],
            ['id'=>'TKT20394826','name'=>'Sneha Patel','initials'=>'SP','category'=>'Speaker','company'=>'TEDx','status'=>'Confirmed','email'=>'sneha@example.test','event_id'=>'evt_001'],
            ['id'=>'TKT20394827','name'=>'Vikram Singh','initials'=>'VS','category'=>'Media','company'=>'NDTV','status'=>'Confirmed','email'=>'vikram@example.test','event_id'=>'evt_001'],
            ['id'=>'TKT20394828','name'=>'Ananya Rao','initials'=>'AR','category'=>'General','company'=>'TCS','status'=>'Waitlist','email'=>'ananya@example.test','event_id'=>'evt_001'],
        ];
    }
}
