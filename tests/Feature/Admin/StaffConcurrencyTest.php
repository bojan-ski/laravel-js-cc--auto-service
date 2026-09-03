<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Exceptions\AdminLockoutPrevented;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * With two admins, A and B, each offboarding the other at the same instant, both
 * pass every self-check and both read an active-admin count of two. Only a lock
 * held across the count stops them leaving zero admins behind.
 *
 * REAL CONTENTION IS NOT TESTED HERE, and cannot be: LazilyRefreshDatabase wraps
 * each test in a transaction, so the service's own transaction is a savepoint and
 * no second connection can contend for the rows. Specs 15, 16, 17, 18 and 19 all
 * hit this wall and reported it rather than faking it.
 *
 * What is proven here is the mechanism, and one property in particular that the
 * outcome alone cannot distinguish: the lock covers EVERY active admin row.
 * Written the way the spec's prose reads — `activeAdmins()->whereKeyNot($user)
 * ->lockForUpdate()` — A locks {A} and B locks {B}, the two lock sets never
 * intersect, neither blocks, and both commit. Every sequential test below passes
 * under that broken form, so the query itself is asserted.
 */
class StaffConcurrencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return list<string>
     */
    private function queriesWhile(callable $operation): array
    {
        DB::enableQueryLog();

        try {
            $operation();
        } finally {
            $queries = array_column(DB::getQueryLog(), 'query');
            DB::disableQueryLog();
        }

        return $queries;
    }

    private function lockingSelect(array $queries): string
    {
        foreach ($queries as $query) {
            if (str_contains($query, 'for update')) {
                return $query;
            }
        }

        $this->fail("No locking read was issued:\n".implode("\n", $queries));
    }

    public function test_offboarding_an_admin_locks_the_active_admin_rows(): void
    {
        User::factory()->admin()->create();
        $target = User::factory()->admin()->create();

        $queries = $this->queriesWhile(fn () => app(StaffService::class)->offboard($target));

        $lock = $this->lockingSelect($queries);

        $this->assertStringContainsString('from `users`', $lock);
        $this->assertStringContainsString('`role` = ?', $lock);
        $this->assertStringContainsString('`deleted_at` is null', $lock);
        $this->assertStringEndsWith('for update', $lock);
    }

    /**
     * The discriminating assertion of this file. A lock narrowed to exclude the
     * target produces two disjoint lock sets and no mutex at all.
     */
    public function test_the_lock_does_not_exclude_the_row_being_changed(): void
    {
        User::factory()->admin()->create();
        $target = User::factory()->admin()->create();

        $lock = $this->lockingSelect(
            $this->queriesWhile(fn () => app(StaffService::class)->offboard($target))
        );

        $this->assertStringNotContainsString('!=', $lock);
        $this->assertStringNotContainsString('not in', $lock);
        $this->assertStringNotContainsString('<>', $lock);
    }

    public function test_the_lock_is_taken_before_the_write(): void
    {
        User::factory()->admin()->create();
        $target = User::factory()->admin()->create();

        $queries = $this->queriesWhile(fn () => app(StaffService::class)->offboard($target));

        $lockAt = null;
        $updateAt = null;

        foreach ($queries as $index => $query) {
            if ($lockAt === null && str_contains($query, 'for update')) {
                $lockAt = $index;
            }

            if ($updateAt === null && str_starts_with($query, 'update `users`')) {
                $updateAt = $index;
            }
        }

        $this->assertNotNull($lockAt, 'No locking read was issued.');
        $this->assertNotNull($updateAt, 'No write was issued.');
        $this->assertLessThan($updateAt, $lockAt, 'The count was read outside the lock.');
    }

    public function test_the_demotion_path_takes_the_same_lock(): void
    {
        User::factory()->admin()->create();
        $target = User::factory()->admin()->create(['name' => 'Bojana Admin']);

        $lock = $this->lockingSelect($this->queriesWhile(
            fn () => app(StaffService::class)->update($target, [
                'name' => 'Bojana Admin',
                'email' => $target->email,
            ], UserRole::Mechanic)
        ));

        $this->assertStringEndsWith('for update', $lock);
        $this->assertSame(UserRole::Mechanic, $target->fresh()->role);
    }

    public function test_a_promotion_or_a_rename_takes_no_lock_at_all(): void
    {
        User::factory()->admin()->create();
        $member = User::factory()->mechanic()->create(['name' => 'Dragan Jovanovic']);

        $queries = $this->queriesWhile(
            fn () => app(StaffService::class)->update($member, [
                'name' => 'Dragan Renamed',
                'email' => $member->email,
            ], UserRole::Receptionist)
        );

        foreach ($queries as $query) {
            $this->assertStringNotContainsString('for update', $query);
        }
    }

    public function test_reconfirming_another_admins_role_as_admin_takes_no_lock(): void
    {
        User::factory()->admin()->create();
        $target = User::factory()->admin()->create(['name' => 'Bojana Admin']);

        $queries = $this->queriesWhile(fn () => app(StaffService::class)->update($target, [
            'name' => 'Bojana Admin',
            'email' => $target->email,
        ], UserRole::Admin));

        foreach ($queries as $query) {
            $this->assertStringNotContainsString('for update', $query);
        }

        $this->assertSame(UserRole::Admin, $target->fresh()->role);
    }

    public function test_offboarding_a_non_admin_takes_no_lock_at_all(): void
    {
        User::factory()->admin()->create();
        $member = User::factory()->mechanic()->create();

        $queries = $this->queriesWhile(fn () => app(StaffService::class)->offboard($member));

        foreach ($queries as $query) {
            $this->assertStringNotContainsString('for update', $query);
        }
    }

    /**
     * Sequentially, which is as close as this suite reaches: the second caller
     * re-reads the count after the first has committed and is refused, so the
     * pair leaves exactly one admin active rather than none.
     */
    public function test_two_admins_offboarding_each_other_in_sequence_leave_exactly_one(): void
    {
        $first = User::factory()->admin()->create();
        $second = User::factory()->admin()->create();

        app(StaffService::class)->offboard($second);

        try {
            app(StaffService::class)->offboard($first);
            $this->fail('The second offboard was allowed and left zero admins.');
        } catch (AdminLockoutPrevented) {
            // expected
        }

        $this->assertSame(1, User::query()->activeAdmins()->count());
        $this->assertTrue(User::query()->activeAdmins()->sole()->is($first));
    }

    public function test_two_admins_demoting_each_other_in_sequence_leave_exactly_one(): void
    {
        $first = User::factory()->admin()->create(['name' => 'First Admin']);
        $second = User::factory()->admin()->create(['name' => 'Second Admin']);

        $staff = app(StaffService::class);

        $staff->update($second, ['name' => 'Second Admin', 'email' => $second->email], UserRole::Mechanic);

        try {
            $staff->update($first, ['name' => 'First Admin', 'email' => $first->email], UserRole::Mechanic);
            $this->fail('The second demotion was allowed and left zero admins.');
        } catch (AdminLockoutPrevented) {
            // expected
        }

        $this->assertSame(1, User::query()->activeAdmins()->count());
        $this->assertSame(UserRole::Admin, $first->fresh()->role);
    }
}
