<?php
declare(strict_types=1);

namespace DigiSangam\Attendees;

use DigiSangam\Core\Storage\JsonFileStore;

final class AttendeeRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('attendees/index.json', [
            ['id'=>'TKT20394823','name'=>'Rohit Sharma','initials'=>'RS','category'=>'VIP','company'=>'Google','status'=>'Confirmed','email'=>'rohit@example.test'],
            ['id'=>'TKT20394824','name'=>'Priya Mehta','initials'=>'PM','category'=>'General','company'=>'Infosys','status'=>'Confirmed','email'=>'priya@example.test'],
            ['id'=>'TKT20394825','name'=>'Amit Kumar','initials'=>'AK','category'=>'Sponsor','company'=>'Microsoft','status'=>'Pending','email'=>'amit@example.test'],
            ['id'=>'TKT20394826','name'=>'Sneha Patel','initials'=>'SP','category'=>'Speaker','company'=>'TEDx','status'=>'Confirmed','email'=>'sneha@example.test'],
            ['id'=>'TKT20394827','name'=>'Vikram Singh','initials'=>'VS','category'=>'Media','company'=>'NDTV','status'=>'Confirmed','email'=>'vikram@example.test'],
            ['id'=>'TKT20394828','name'=>'Ananya Rao','initials'=>'AR','category'=>'General','company'=>'TCS','status'=>'Waitlist','email'=>'ananya@example.test'],
        ]);
    }
}
