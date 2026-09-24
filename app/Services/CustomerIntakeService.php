<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CustomerIntakeService
{
    public const int LOOKUP_LIMIT = 8;

    public function __construct(
        private readonly CustomerService $customers,
        private readonly VehicleService $vehicles,
        private readonly AppointmentService $appointments,
        private readonly JobService $jobs,
    ) {}

    /**
     * @return Collection<int, Customer>
     */
    public function lookup(string $term): Collection
    {
        return Customer::query()
            ->with(['vehicles' => fn ($query) => $query->select(['id', 'customer_id', 'registration_plate', 'make', 'model'])])
            ->matching($term)
            ->orderBy('name')
            ->limit(self::LOOKUP_LIMIT)
            ->get(['id', 'name', 'phone', 'email']);
    }

    public function picked(?int $customerId): ?Customer
    {
        if ($customerId === null) {
            return null;
        }

        return Customer::query()
            ->with(['vehicles' => fn ($query) => $query->select(['id', 'customer_id', 'registration_plate', 'make', 'model'])])
            ->find($customerId, ['id', 'name', 'phone', 'email']);
    }

    /**
     * @param  array{customer_id: int|null, vehicle_id: int|null, customer: array<string, mixed>, vehicle: array<string, mixed>}  $intake
     * @param  array<string, mixed>  $attributes
     */
    public function book(array $intake, User $bookedBy, array $attributes): Appointment
    {
        return DB::transaction(fn (): Appointment => $this->appointments->create(
            $this->resolveVehicle($intake),
            $bookedBy,
            $attributes,
        ));
    }

    /**
     * @param  array{customer_id: int|null, vehicle_id: int|null, customer: array<string, mixed>, vehicle: array<string, mixed>}  $intake
     * @param  array<string, mixed>  $attributes
     */
    public function openWalkIn(array $intake, User $openedBy, array $attributes): Job
    {
        return $this->jobs->createWalkInFor(fn (): Vehicle => $this->resolveVehicle($intake), $openedBy, $attributes);
    }

    /**
     * @param  array{customer_id: int|null, vehicle_id: int|null, customer: array<string, mixed>, vehicle: array<string, mixed>}  $intake
     */
    private function resolveVehicle(array $intake): Vehicle
    {
        if ($intake['vehicle_id'] !== null) {
            return Vehicle::query()->findOrFail($intake['vehicle_id']);
        }

        $customer = $intake['customer_id'] !== null
            ? Customer::query()->findOrFail($intake['customer_id'])
            : $this->customers->create($intake['customer']);

        return $this->vehicles->create($customer, $intake['vehicle']);
    }
}
