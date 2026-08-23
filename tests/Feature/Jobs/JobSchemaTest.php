<?php

namespace Tests\Feature\Jobs;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JobSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_table_has_exactly_the_specified_columns(): void
    {
        $this->assertEqualsCanonicalizing([
            'id', 'reference', 'appointment_id', 'customer_id', 'vehicle_id', 'status',
            'complaint', 'mileage_in', 'started_at', 'awaiting_parts_at', 'completed_at',
            'cancelled_at', 'locked_at', 'created_by', 'created_at', 'updated_at',
        ], Schema::getColumnListing('jobs'));
    }

    public function test_jobs_are_never_soft_deleted(): void
    {
        $this->assertFalse(Schema::hasColumn('jobs', 'deleted_at'));
    }

    public function test_nullability_matches_the_contract(): void
    {
        $nullable = collect(Schema::getColumns('jobs'))->mapWithKeys(
            fn (array $column): array => [$column['name'] => $column['nullable']]
        );

        foreach (['appointment_id', 'mileage_in', 'started_at', 'awaiting_parts_at', 'completed_at', 'cancelled_at', 'locked_at'] as $column) {
            $this->assertTrue($nullable[$column], "{$column} should be nullable");
        }

        foreach (['reference', 'customer_id', 'vehicle_id', 'status', 'complaint', 'created_by'] as $column) {
            $this->assertFalse($nullable[$column], "{$column} should not be nullable");
        }
    }

    public function test_the_reference_is_unique_and_the_lookup_columns_are_indexed(): void
    {
        $indexes = collect(Schema::getIndexes('jobs'));

        $reference = $indexes->first(fn (array $index): bool => $index['columns'] === ['reference']);
        $this->assertNotNull($reference);
        $this->assertTrue($reference['unique']);

        foreach (['customer_id', 'vehicle_id', 'status'] as $column) {
            $this->assertTrue(
                $indexes->contains(fn (array $index): bool => $index['columns'] === [$column]),
                "Expected an index on {$column}."
            );
        }
    }

    public function test_two_jobs_cannot_share_a_reference(): void
    {
        $job = Job::factory()->create();

        $this->expectException(QueryException::class);

        Job::factory()->create(['reference' => $job->reference]);
    }

    public function test_the_queue_uses_its_own_table_and_the_jobs_table_is_the_domain_table(): void
    {
        $this->assertSame('queue_jobs', config('queue.connections.database.table'));
        $this->assertTrue(Schema::hasTable('queue_jobs'));
        $this->assertTrue(Schema::hasColumn('queue_jobs', 'payload'));
        $this->assertFalse(Schema::hasColumn('jobs', 'payload'));
        $this->assertFalse(Schema::hasColumn('jobs', 'queue'));
    }

    public function test_deleting_a_customer_with_a_job_is_restricted(): void
    {
        $job = Job::factory()->create();

        $this->expectException(QueryException::class);

        Customer::withTrashed()->findOrFail($job->customer_id)->forceDelete();
    }

    public function test_deleting_a_vehicle_with_a_job_is_restricted(): void
    {
        $job = Job::factory()->create();

        $this->expectException(QueryException::class);

        Vehicle::withTrashed()->findOrFail($job->vehicle_id)->forceDelete();
    }

    public function test_deleting_the_opening_user_is_restricted(): void
    {
        $job = Job::factory()->create();

        $this->expectException(QueryException::class);

        User::withTrashed()->findOrFail($job->created_by)->forceDelete();
    }

    public function test_deleting_an_appointment_nulls_the_job_link_rather_than_deleting_the_job(): void
    {
        $appointment = Appointment::factory()->arrived()->create();
        $job = Job::factory()->forAppointment($appointment)->create();

        $appointment->delete();

        $this->assertNull($job->fresh()->appointment_id);
    }

    public function test_a_complaint_is_required_at_the_database_level(): void
    {
        $this->expectException(QueryException::class);

        Job::factory()->create(['complaint' => null]);
    }
}
