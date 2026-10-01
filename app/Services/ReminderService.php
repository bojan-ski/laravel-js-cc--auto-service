<?php

namespace App\Services;

use App\Enums\ReminderKind;
use App\Exceptions\ReminderNotQueued;
use App\Mail\ServiceReminderMail;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReminderService
{
    public const int DUE_SOON_DAYS = 30;

    public const int DUE_SOON_LIMIT = 15;

    public function __construct(
        private CustomerMailer $mailer,
    ) {}

    public function sendDue(CarbonImmutable $today): int
    {
        $queued = 0;

        foreach ($this->dueCandidates($today) as $candidate) {
            try {
                DB::transaction(fn () => $this->record($candidate));
            } catch (UniqueConstraintViolationException|ReminderNotQueued) {
                continue;
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            $queued++;
        }

        return $queued;
    }

    /**
     * @return list<array{vehicle: Vehicle, kind: ReminderKind, due_on: CarbonImmutable, overdue: bool, contact: 'emailed'|'pending'|'call', sent_at: ?CarbonImmutable, phone: string}>
     */
    public function dueSoon(CarbonImmutable $today): array
    {
        return $this->dueSoonQuery($today)
            ->orderBy('due_on')
            ->orderBy('registration_plate')
            ->orderBy('kind')
            ->limit(self::DUE_SOON_LIMIT)
            ->get()
            ->map(fn (Vehicle $row): array => $this->dueSoonRow($row, $today))
            ->all();
    }

    public function countDueSoon(CarbonImmutable $today): int
    {
        return DB::query()->fromSub($this->dueSoonQuery($today), 'due_soon')->count();
    }

    /**
     * @return list<array{vehicle: Vehicle, customer: Customer, kind: ReminderKind, due_on: CarbonImmutable}>
     */
    protected function dueCandidates(CarbonImmutable $today): array
    {
        $until = $today->addDays((int) config('shop.reminders.lead_days'));
        $candidates = [];

        foreach (ReminderKind::cases() as $kind) {
            $column = $kind->dateColumn();

            $vehicles = Vehicle::query()
                ->with('customer')
                ->whereBetween($column, [$today->toDateString(), $until->toDateString()])
                ->whereHas('customer', fn (Builder $customer) => $customer
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->where('receives_reminders', true))
                ->whereNotExists(fn (QueryBuilder $reminder) => $reminder
                    ->selectRaw('1')
                    ->from('vehicle_reminders')
                    ->whereColumn('vehicle_reminders.vehicle_id', 'vehicles.id')
                    ->where('vehicle_reminders.kind', $kind->value)
                    ->whereColumn('vehicle_reminders.due_on', "vehicles.{$column}"))
                ->orderBy($column)
                ->orderBy('id')
                ->get();

            foreach ($vehicles as $vehicle) {
                $candidates[] = [
                    'vehicle' => $vehicle,
                    'customer' => $vehicle->customer,
                    'kind' => $kind,
                    'due_on' => $vehicle->{$column}->toImmutable(),
                ];
            }
        }

        return $candidates;
    }

    /**
     * @param  array{vehicle: Vehicle, customer: Customer, kind: ReminderKind, due_on: CarbonImmutable}  $candidate
     */
    private function record(array $candidate): void
    {
        $reminder = new VehicleReminder;
        $reminder->vehicle_id = $candidate['vehicle']->getKey();
        $reminder->customer_id = $candidate['customer']->getKey();
        $reminder->kind = $candidate['kind'];
        $reminder->due_on = $candidate['due_on'];
        $reminder->sent_at = now();
        $reminder->save();

        $mail = new ServiceReminderMail($reminder, $candidate['customer'], $candidate['vehicle']);

        if (! $this->mailer->send($candidate['customer'], $mail)) {
            throw new ReminderNotQueued($candidate['customer']);
        }
    }

    /**
     * @return Builder<Vehicle>
     */
    private function dueSoonQuery(CarbonImmutable $today): Builder
    {
        $until = $today->addDays(self::DUE_SOON_DAYS);

        return $this->dueSoonHalf(ReminderKind::Service, $until)
            ->unionAll($this->dueSoonHalf(ReminderKind::Registration, $until));
    }

    /**
     * @return Builder<Vehicle>
     */
    private function dueSoonHalf(ReminderKind $kind, CarbonImmutable $until): Builder
    {
        $column = "vehicles.{$kind->dateColumn()}";

        return Vehicle::query()
            ->join('customers', 'customers.id', '=', 'vehicles.customer_id')
            ->leftJoin('vehicle_reminders', fn (JoinClause $join) => $join
                ->on('vehicle_reminders.vehicle_id', '=', 'vehicles.id')
                ->where('vehicle_reminders.kind', '=', $kind->value)
                ->on('vehicle_reminders.due_on', '=', $column))
            ->whereNull('customers.deleted_at')
            ->whereNotNull($column)
            ->where($column, '<=', $until->toDateString())
            ->select([
                'vehicles.id',
                'vehicles.registration_plate',
                'vehicles.make',
                'vehicles.model',
            ])
            ->selectRaw('? as kind', [$kind->value])
            ->addSelect([
                "{$column} as due_on",
                'customers.id as owner_id',
                'customers.phone as owner_phone',
                'customers.email as owner_email',
                'customers.receives_reminders as owner_receives_reminders',
                'vehicle_reminders.customer_id as reminder_customer_id',
                'vehicle_reminders.sent_at as reminder_sent_at',
            ]);
    }

    /**
     * @return array{vehicle: Vehicle, kind: ReminderKind, due_on: CarbonImmutable, overdue: bool, contact: 'emailed'|'pending'|'call', sent_at: ?CarbonImmutable, phone: string}
     */
    private function dueSoonRow(Vehicle $row, CarbonImmutable $today): array
    {
        $dueOn = CarbonImmutable::parse($row->getAttribute('due_on'))->startOfDay();
        $remindedOwner = $row->getAttribute('reminder_customer_id');

        $owner = (new Customer)->newFromBuilder([
            'id' => $row->getAttribute('owner_id'),
            'email' => $row->getAttribute('owner_email'),
            'receives_reminders' => $row->getAttribute('owner_receives_reminders'),
            'deleted_at' => null,
        ]);

        $contact = match (true) {
            $remindedOwner !== null && (int) $remindedOwner === (int) $owner->getKey() => 'emailed',
            $remindedOwner === null && $owner->canBeReminded() && $dueOn->gte($today) => 'pending',
            default => 'call',
        };

        return [
            'vehicle' => $row,
            'kind' => ReminderKind::from($row->getAttribute('kind')),
            'due_on' => $dueOn,
            'overdue' => $dueOn->lt($today),
            'contact' => $contact,
            'sent_at' => $contact === 'emailed' ? CarbonImmutable::parse($row->getAttribute('reminder_sent_at')) : null,
            'phone' => $row->getAttribute('owner_phone'),
        ];
    }
}
