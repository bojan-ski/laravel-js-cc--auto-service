<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidAppointmentTransition;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class AppointmentService
{
    /**
     * @param  array{view: string, date: CarbonImmutable, status: string}  $filters
     * @return LengthAwarePaginator<int, Appointment>
     */
    public function paginateFiltered(array $filters): LengthAwarePaginator
    {
        $days = $this->daysInWindow($filters);

        return Appointment::query()
            ->with(['vehicle', 'customer'])
            ->where('scheduled_at', '>=', $days[0])
            ->where('scheduled_at', '<', end($days)->addDay())
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('scheduled_at')
            ->paginate(50)
            ->withQueryString();
    }

    /**
     * @param  array{view: string, date: CarbonImmutable, status: string}  $filters
     * @return array<int, CarbonImmutable>
     */
    public function daysInWindow(array $filters): array
    {
        if ($filters['view'] !== 'week') {
            return [$filters['date']->startOfDay()];
        }

        $monday = $filters['date']->startOfWeek(CarbonInterface::MONDAY);

        return array_map(fn (int $offset): CarbonImmutable => $monday->addDays($offset), range(0, 6));
    }

    /**
     * @param  iterable<int, Appointment>  $appointments
     * @param  array<int, CarbonImmutable>  $days
     * @return array<string, array{date: CarbonImmutable, appointments: \Illuminate\Support\Collection<int, Appointment>}>
     */
    public function groupByDay(iterable $appointments, array $days): array
    {
        $groups = [];

        foreach ($days as $day) {
            $groups[$day->format('Y-m-d')] = ['date' => $day, 'appointments' => collect()];
        }

        foreach ($appointments as $appointment) {
            $key = $appointment->scheduled_at->format('Y-m-d');

            if (isset($groups[$key])) {
                $groups[$key]['appointments']->push($appointment);
            }
        }

        return $groups;
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function overlapping(CarbonInterface $start, int $durationMinutes, ?int $excludeId = null): Collection
    {
        return Appointment::query()
            ->with(['vehicle', 'customer'])
            ->overlapping($start, $durationMinutes, $excludeId)
            ->orderBy('scheduled_at')
            ->get();
    }

    public function hasAny(): bool
    {
        return Appointment::query()->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Vehicle $vehicle, User $bookedBy, array $attributes): Appointment
    {
        $appointment = new Appointment;
        $appointment->fill($attributes);

        $appointment->customer_id = $vehicle->customer_id;
        $appointment->vehicle_id = $vehicle->getKey();
        $appointment->created_by = $bookedBy->getKey();
        $appointment->status = AppointmentStatus::Booked;

        $appointment->save();

        return $appointment;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function reschedule(Appointment $appointment, array $attributes): Appointment
    {
        if (! $appointment->canBeRescheduled()) {
            throw new InvalidAppointmentTransition($appointment, 'reschedule');
        }

        $appointment->update($attributes);

        return $appointment;
    }

    public function cancel(Appointment $appointment): Appointment
    {
        return $this->transition(
            $appointment,
            $appointment->canBeCancelled(),
            'cancel',
            AppointmentStatus::Cancelled,
            'cancelled_at',
        );
    }

    public function markArrived(Appointment $appointment): Appointment
    {
        return $this->transition(
            $appointment,
            $appointment->canBeMarkedArrived(),
            'mark arrived',
            AppointmentStatus::Arrived,
            'arrived_at',
        );
    }

    public function markNoShow(Appointment $appointment): Appointment
    {
        return $this->transition(
            $appointment,
            $appointment->canBeMarkedNoShow(),
            'mark no-show',
            AppointmentStatus::NoShow,
            'no_show_at',
        );
    }

    private function transition(
        Appointment $appointment,
        bool $permitted,
        string $name,
        AppointmentStatus $status,
        string $timestampColumn,
    ): Appointment {
        if (! $permitted) {
            throw new InvalidAppointmentTransition($appointment, $name);
        }

        $appointment->status = $status;
        $appointment->{$timestampColumn} = now();
        $appointment->save();

        return $appointment;
    }
}
