<?php
declare(strict_types=1);

namespace DigiSangam\Tickets;

use DigiSangam\Core\Storage\JsonFileStore;

final class TicketRepository
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array
    {
        return $this->store->read('tickets/index.json', [
            ['id'=>'tic_1','name'=>'Early Bird','price'=>2499,'quantity'=>500,'sold'=>432,'status'=>'Active'],
            ['id'=>'tic_2','name'=>'General','price'=>3999,'quantity'=>1000,'sold'=>642,'status'=>'Active'],
            ['id'=>'tic_3','name'=>'Student','price'=>1499,'quantity'=>300,'sold'=>210,'status'=>'Active'],
            ['id'=>'tic_4','name'=>'VIP','price'=>9999,'quantity'=>100,'sold'=>85,'status'=>'Active'],
            ['id'=>'tic_5','name'=>'Speaker','price'=>0,'quantity'=>200,'sold'=>120,'status'=>'Active'],
            ['id'=>'tic_6','name'=>'Sponsor Delegate','price'=>0,'quantity'=>500,'sold'=>320,'status'=>'Active'],
            ['id'=>'tic_7','name'=>'Media','price'=>0,'quantity'=>100,'sold'=>64,'status'=>'Active'],
        ]);
    }
}
