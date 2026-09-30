<?php

namespace Tests\Feature\CheckIns;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobCheckIn;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The check-in carries no money, and the pages that show it must not load
 * any. Asserted against the query log, never the markup — a price that was
 * selected and not rendered is still a price in a mechanic's request.
 */
class CheckInMoneyBoundaryTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function roles(): array
    {
        return ['mechanic' => ['mechanic'], 'receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    #[DataProvider('roles')]
    public function test_the_sheet_print_and_signature_routes_read_no_money_bearing_table(string $role): void
    {
        Storage::fake(config('attachments.disk'));

        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();
        Invoice::factory()->unpaid()->forJob($job)->create();
        JobAttachment::factory()->checkIn()->forJob($job)->create();
        JobCheckIn::factory()->forJob($job)->signedByDrawing()->create();

        $user = User::factory()->{$role}()->create();

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('jobs.check-in.show', $job))->assertOk();
        $this->actingAs($user)->get(route('jobs.check-in.print', $job))->assertOk();
        $this->actingAs($user)->get(route('jobs.check-in.signature', $job))->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertGreaterThan(6, count($queries), 'The query log captured nothing, so the check proves nothing.');

        foreach (['job_items', 'invoices', 'parts'] as $table) {
            $this->assertSame([], array_values(array_filter(
                $queries,
                fn (string $query): bool => str_contains($query, "`{$table}`"),
            )), "A check-in route read `{$table}`:\n".implode("\n", $queries));
        }
    }
}
