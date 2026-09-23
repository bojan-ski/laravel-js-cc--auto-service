<?php

namespace Database\Seeders\Demo;

use App\Enums\UserRole;
use App\Services\StaffService;

class StaffSeeder extends DemoSeeder
{
    /** @var list<array{string, string, UserRole}> */
    public const array STAFF = [
        ['milica', 'Milica Stanković', UserRole::Admin],
        ['jelena', 'Jelena Popović', UserRole::Receptionist],
        ['ana', 'Ana Marković', UserRole::Receptionist],
        ['dragan', 'Dragan Jovanović', UserRole::Mechanic],
        ['nikola', 'Nikola Petrović', UserRole::Mechanic],
        ['stefan', 'Stefan Ilić', UserRole::Mechanic],
        ['zoran', 'Zoran Đorđević', UserRole::Mechanic],
    ];

    public function __construct(private readonly StaffService $staffService) {}

    protected function populate(): void
    {
        $this->travelTo($this->daysAgo(120, '08:00'));

        foreach (self::STAFF as [$handle, $name, $role]) {
            $this->staffService->create([
                'name' => $name,
                'email' => self::emailFor($handle),
                'password' => self::PASSWORD,
            ], $role);
        }
    }
}
