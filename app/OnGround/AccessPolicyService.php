<?php
declare(strict_types=1);

namespace DigiSangam\OnGround;

use DigiSangam\Venue\VenueRepository;

final class AccessPolicyService
{
    public function __construct(private readonly VenueRepository $venues) {}

    public function evaluate(string $eventId,string $category,string $zoneId=''): array
    {
        if($zoneId==='') return ['allowed'=>true,'reason'=>'NO_ZONE_RULE'];
        $venue=$this->venues->get($eventId);
        foreach((array)($venue['zones']??[]) as $zone){
            if(($zone['id']??'')!==$zoneId) continue;
            $allowed=(array)($zone['categories']??[]);
            return [
                'allowed'=>$allowed===[]||in_array($category,$allowed,true),
                'reason'=>($allowed===[]||in_array($category,$allowed,true))?'ZONE_ALLOWED':'ZONE_DENIED',
                'zone'=>$zone,
            ];
        }
        return ['allowed'=>false,'reason'=>'ZONE_NOT_FOUND'];
    }
}
