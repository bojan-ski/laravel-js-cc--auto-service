<?php

namespace Database\Seeders\Demo;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\CustomerService;
use App\Services\ReminderService;
use App\Services\VehicleService;

class ReminderSeeder extends DemoSeeder
{
    /**
     * Plate => [next service in days, next service mileage, registration expiry in days].
     *
     * @var array<string, array{?int, ?int, ?int}>
     */
    private const array DATES = [
        'BG-123-AB' => [5, 190000, null],
        'BG-164-KS' => [null, null, 9],
        'BG-908-IN' => [12, null, null],
        'NI-552-NS' => [-12, 270000, null],
        'BG-315-DM' => [24, 260000, null],
        'BG-2101-BK' => [null, null, 27],
        'BG-440-FC' => [7, 180000, null],
        'BG-650-BV' => [null, null, -3],
        'KG-088-MP' => [90, 230000, 200],
    ];

    private const string OPTED_OUT = 'Aleksandar Nikolić';

    public function __construct(
        private readonly VehicleService $vehicles,
        private readonly CustomerService $customers,
        private readonly ReminderService $reminders,
    ) {}

    protected function populate(): void
    {
        $this->customers->update(
            Customer::query()->where('name', self::OPTED_OUT)->firstOrFail(),
            ['receives_reminders' => false],
        );

        foreach (self::DATES as $plate => [$serviceIn, $mileage, $registrationIn]) {
            $this->vehicles->update(Vehicle::query()->where('registration_plate', $plate)->firstOrFail(), [
                'next_service_on' => $serviceIn === null ? null : $this->today->addDays($serviceIn)->toDateString(),
                'next_service_mileage' => $mileage,
                'registration_expires_on' => $registrationIn === null ? null : $this->today->addDays($registrationIn)->toDateString(),
            ]);
        }

        $this->reminders->sendDue($this->today->startOfDay());
    }
}
