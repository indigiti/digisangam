<?php
declare(strict_types=1);

namespace DigiSangam\Attendees;

final class ApprovalService
{
    public static function normalize(string $status): string
    {
        return match (strtolower(trim($status))) {
            'approved', 'confirmed' => 'Confirmed',
            'rejected', 'declined' => 'Rejected',
            'waitlist', 'waitlisted' => 'Waitlist',
            default => 'Pending',
        };
    }
}
