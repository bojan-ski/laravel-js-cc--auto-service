<?php

namespace Tests\Feature\Workflows;

use App\Enums\StockMovementReason;
use App\Models\Appointment;
use App\Models\Job;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobItemService;
use App\Services\JobService;
use App\Services\StockService;
use App\Services\SupplierService;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The four row-lock sites that had no lock-query assertion when spec 24 was
 * loaded. Each is a real mutex: the appointment lock is what makes "arrival
 * creates exactly one job" true, the vehicle lock is what stops a lost or
 * downward mileage write, the job lock under every line-item write is what
 * stops a write racing a cancellation and stranding a job_use no path can
 * return, and the supplier lock is what stops two restocks creating two rows
 * for one name.
 *
 * REAL CONTENTION IS NOT TESTED HERE, and cannot be: LazilyRefreshDatabase
 * wraps each test in a transaction, so every service transaction is a savepoint
 * and no second connection can contend for a row. What is evidenced is the
 * mechanism — the `for update` read is issued, and it precedes the write it
 * protects. The sequential-refusal half of each site is already pinned
 * elsewhere and is named on each test. Matchers are table-specific, so a lock
 * on another table in the same transaction cannot satisfy them.
 */
class ConcurrencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Rule (spec 09): arrival creates exactly one job. The refusal of a stale
     * second arrival is pinned by JobCreationTest; this proves the lock that
     * makes the re-check under it meaningful.
     */
    public function test_arriving_locks_the_appointment_row_before_marking_it_arrived_and_inserting_the_job(): void
    {
        $appointment = Appointment::factory()->booked()->create();
        $receptionist = $this->receptionist();

        $queries = $this->queriesDuring(fn () => app(JobService::class)->createFromAppointment(
            $appointment,
            $receptionist,
            ['complaint' => 'Service'],
        ));

        $this->assertLockPrecedes($queries, 'appointments', 'update `appointments`');
        $this->assertLockPrecedes($queries, 'appointments', 'insert into `jobs`');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function creationPathProvider(): array
    {
        return [
            'walk-in' => ['walk-in'],
            'appointment' => ['appointment'],
        ];
    }

    /**
     * Rule (spec 09): a mileage reading moves the vehicle only upwards, under a
     * locked re-read, on both creation paths. A lower reading being ignored is
     * pinned by JobMileageTest; this proves the lock.
     */
    #[DataProvider('creationPathProvider')]
    public function test_recording_a_mileage_reading_locks_the_vehicle_row_before_updating_it(string $path): void
    {
        $vehicle = Vehicle::factory()->create(['mileage' => 100_000]);
        $receptionist = $this->receptionist();
        $attributes = ['complaint' => 'Service', 'mileage_in' => 150_000];
        $appointment = $path === 'appointment'
            ? Appointment::factory()->booked()->for($vehicle)->create()
            : null;

        $queries = $this->queriesDuring(fn () => $appointment === null
            ? app(JobService::class)->createWalkIn($vehicle, $receptionist, $attributes)
            : app(JobService::class)->createFromAppointment($appointment, $receptionist, $attributes));

        $this->assertLockPrecedes($queries, 'vehicles', 'update `vehicles`');
        $this->assertSame(150_000, $vehicle->fresh()->mileage);
    }

    /**
     * Control for the test above: the vehicle lock is taken only when there is
     * a reading to record, so the lock it observes belongs to recordMileage()
     * and not to some other read on the creation path.
     */
    public function test_an_omitted_reading_takes_no_vehicle_lock_at_all(): void
    {
        $vehicle = Vehicle::factory()->create(['mileage' => 100_000]);
        $receptionist = $this->receptionist();

        $queries = $this->queriesDuring(fn () => app(JobService::class)->createWalkIn(
            $vehicle,
            $receptionist,
            ['complaint' => 'Service'],
        ));

        $this->assertNoLockOn($queries, 'vehicles');
        $this->assertNotNull(
            $this->firstIndexMatching($queries, fn (string $query): bool => str_contains($query, 'insert into `jobs`')),
            'The job was not created, so the control proves nothing.',
        );
        $this->assertSame(100_000, $vehicle->fresh()->mileage);
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function lineItemWriterProvider(): array
    {
        return [
            'add labor' => ['add labor', ['insert into `job_items`']],
            'add part' => ['add part', ['insert into `job_items`', 'insert into `stock_movements`']],
            'edit' => ['edit', ['update `job_items`', 'insert into `stock_movements`']],
            'delete' => ['delete', ['delete from `job_items`', 'insert into `stock_movements`']],
        ];
    }

    /**
     * Rule (spec 16): every line-item write locks the job row so it cannot race
     * a cancellation. The refusal on a cancelled job is pinned by
     * JobPartLineGuardTest and JobCancellationStockTest; this proves the lock,
     * and that it is the JOB row — StockService locks `parts` inside the same
     * transaction, which the table-specific matcher cannot mistake for it.
     *
     * @param  list<string>  $writes
     */
    #[DataProvider('lineItemWriterProvider')]
    public function test_every_line_item_write_locks_the_job_row_before_writing(string $writer, array $writes): void
    {
        $receptionist = $this->receptionist();
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart(10, $receptionist);
        $items = app(JobItemService::class);

        $existing = $writer === 'edit' || $writer === 'delete'
            ? $items->addPart($job, $part, $receptionist, ['quantity' => 2])
            : null;

        $queries = $this->queriesDuring(fn () => match ($writer) {
            'add labor' => $items->addLabor($job, $receptionist, [
                'description' => 'Brake service',
                'quantity' => '1.50',
                'user_id' => $mechanic->getKey(),
            ]),
            'add part' => $items->addPart($job, $part, $receptionist, ['quantity' => 2]),
            'edit' => $items->update($existing, $receptionist, [
                'description' => $existing->description,
                'quantity' => '3',
            ]),
            'delete' => $items->delete($existing, $receptionist),
        });

        foreach ($writes as $write) {
            $this->assertLockPrecedes($queries, 'jobs', $write);
        }
    }

    /**
     * Rule (spec 23a): a supplier name resolves find-or-create on its
     * normalized form, so case and spacing drift never create duplicates.
     * Reuse of an existing row is pinned by StockSupplierTest; this proves the
     * lock. On a missing row InnoDB's `for update` takes a gap lock on the
     * name_normalized index, which is the actual mutex against two callers
     * inserting the same name — not observable from PHP, so only the read and
     * its position are asserted. Reached from the stock-adjustment form and
     * from every CSV import row.
     */
    public function test_resolving_an_unknown_supplier_name_locks_the_matching_slot_before_inserting(): void
    {
        $queries = $this->queriesDuring(fn () => app(SupplierService::class)->resolveByName('Bosch Srbija'));

        $this->assertLockPrecedes($queries, 'suppliers', 'insert into `suppliers`');

        $lock = $queries[$this->firstIndexMatching($queries, $this->lockOn('suppliers'))];

        $this->assertStringContainsString('`name_normalized` = ?', $lock, 'The lock must sit on the matching slot, not the whole table.');
        $this->assertSame(1, Supplier::query()->count());
    }

    public function test_resolving_a_known_supplier_name_locks_its_row_and_inserts_nothing(): void
    {
        $service = app(SupplierService::class);
        $first = $service->resolveByName('Bosch Srbija');

        $queries = $this->queriesDuring(fn () => $service->resolveByName('  bosch-srbija '));

        $this->assertNotNull(
            $this->firstIndexMatching($queries, $this->lockOn('suppliers')),
            "No locking read of `suppliers` was issued:\n".implode("\n", $queries),
        );
        $this->assertNull(
            $this->firstIndexMatching($queries, fn (string $query): bool => str_contains($query, 'insert into `suppliers`')),
            'A second row was inserted for the same name.',
        );
        $this->assertSame(1, Supplier::query()->count());
        $this->assertTrue($service->resolveByName('  bosch-srbija ')->is($first));
    }

    private function receptionist(): User
    {
        return User::factory()->receptionist()->create();
    }

    private function stockedPart(int $quantity, User $by): Part
    {
        $part = Part::factory()->create();

        app(StockService::class)->record($part, $quantity, StockMovementReason::Restock, $by);

        return $part;
    }

    /**
     * @return list<string>
     */
    private function queriesDuring(Closure $work): array
    {
        DB::enableQueryLog();

        try {
            $work();
        } finally {
            $queries = array_column(DB::getQueryLog(), 'query');
            DB::disableQueryLog();
        }

        return $queries;
    }

    /**
     * @param  list<string>  $queries
     * @param  callable(string): bool  $matches
     */
    private function firstIndexMatching(array $queries, callable $matches): ?int
    {
        foreach ($queries as $index => $query) {
            if ($matches($query)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return callable(string): bool
     */
    private function lockOn(string $table): callable
    {
        return fn (string $query): bool => str_contains($query, "from `{$table}`") && str_contains($query, 'for update');
    }

    /**
     * @param  list<string>  $queries
     */
    private function assertLockPrecedes(array $queries, string $table, string $write): void
    {
        $log = implode("\n", $queries);

        $lockIndex = $this->firstIndexMatching($queries, $this->lockOn($table));
        $writeIndex = $this->firstIndexMatching($queries, fn (string $query): bool => str_contains($query, $write));

        $this->assertNotNull($lockIndex, "No locking read of `{$table}` was issued:\n{$log}");
        $this->assertNotNull($writeIndex, "No `{$write}` statement was issued:\n{$log}");
        $this->assertLessThan($writeIndex, $lockIndex, "The `{$table}` row must be locked before `{$write}`:\n{$log}");
    }

    /**
     * @param  list<string>  $queries
     */
    private function assertNoLockOn(array $queries, string $table): void
    {
        $this->assertNull(
            $this->firstIndexMatching($queries, $this->lockOn($table)),
            "An unexpected locking read of `{$table}` was issued:\n".implode("\n", $queries),
        );
    }
}
