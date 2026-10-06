<?php
declare(strict_types=1);

namespace DigiSangam\Auth;

final class Authorization
{
    private const ROLE_PERMISSIONS = [
        'super_admin' => ['*'],
        'workspace_admin' => [
            'events.*','registration.*','attendees.*','tickets.*','commerce.*','analytics.view','workspace.*',
            'communications.*','automation.*','badges.*','agenda.*','exhibitors.*','venue.*','onground.*','intelligence.*'
        ],
        'event_manager' => [
            'events.*','registration.*','attendees.*','tickets.*','analytics.view',
            'communications.*','automation.*','badges.*','agenda.*','exhibitors.*','venue.*','onground.view','intelligence.*'
        ],
        'registration_manager' => ['registration.*','attendees.*','tickets.view','analytics.view','communications.view','badges.view','agenda.view','intelligence.view'],
        'finance' => ['tickets.view','commerce.*','analytics.view','exhibitors.view','intelligence.view'],
        'onsite' => ['attendees.view','attendees.checkin','tickets.view','onground.*','badges.view','venue.view','agenda.view','intelligence.view'],
        'viewer' => [
            'events.view','attendees.view','tickets.view','analytics.view','communications.view','automation.view',
            'badges.view','agenda.view','exhibitors.view','venue.view','onground.view','intelligence.view'
        ],
    ];

    public static function roles(): array
    {
        return array_keys(self::ROLE_PERMISSIONS);
    }

    public static function allows(string $role, string $permission): bool
    {
        foreach (self::ROLE_PERMISSIONS[$role] ?? [] as $granted) {
            if ($granted === '*' || $granted === $permission) return true;
            if (str_ends_with($granted, '.*') && str_starts_with($permission, substr($granted, 0, -1))) return true;
        }
        return false;
    }
}
