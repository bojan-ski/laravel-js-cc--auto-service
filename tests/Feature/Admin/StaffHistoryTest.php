<?php

namespace Tests\Feature\Admin;

use App\Enums\StockMovementReason;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\StockService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Offboarding a person and keeping their name on everything they touched is the
 * whole reason users are soft-deleted rather than removed. Every relation that
 * resolves a user `withTrashed()` — job creators, line-item mechanics, stock
 * movement causers, invoice issuers — exists to make this work. This is the test
 * that proves the convention holds end to end rather than per relation.
 */
class StaffHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_an_offboarded_mechanic_keeps_their_name_on_every_record_they_touched(): void
    {
        $admin = User::factory()->admin()->create();

        // 1. The admin creates a mechanic through the staff form.
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Dragan Jovanovic',
            'email' => 'dragan@shop.rs',
            'role' => 'mechanic',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('admin.users.index'));

        $mechanic = User::where('email', 'dragan@shop.rs')->sole();

        // 2. They can sign in straight away. /login is behind the guest band,
        //    so the admin's own session has to go first.
        Auth::forgetGuards();
        $this->flushSession();

        $this->post('/login', ['email' => 'dragan@shop.rs', 'password' => 'correct-horse-battery']);
        $this->assertAuthenticatedAs($mechanic);

        // 3. Their name is attached to a labor line, a stock movement and an invoice.
        $job = Job::factory()->completed()->create(['created_by' => $mechanic->id]);

        $labor = JobItem::factory()->forJob($job)->create([
            'user_id' => $mechanic->id,
            'quantity' => 1.5,
            'unit_price' => 2000,
        ]);

        $part = Part::factory()->inStock()->create();
        app(StockService::class)->record($part, -1, StockMovementReason::JobUse, $mechanic);

        $invoice = app(InvoiceService::class)->issue($job, $mechanic);

        // 4. The admin offboards them.
        Auth::forgetGuards();
        $this->flushSession();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $mechanic))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $mechanic->id]);

        // 5. They can no longer sign in.
        Auth::forgetGuards();
        $this->flushSession();

        $this->post('/login', ['email' => 'dragan@shop.rs', 'password' => 'correct-horse-battery'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // 6. Every record still resolves and renders their name.
        $this->assertSame('Dragan Jovanovic', $labor->fresh()->user->name);
        $this->assertSame('Dragan Jovanovic', $job->fresh()->createdBy->name);
        $this->assertSame('Dragan Jovanovic', StockMovement::query()->latest('id')->sole()->causedBy->name);
        $this->assertSame('Dragan Jovanovic', $invoice->fresh()->issuedBy->name);

        $this->actingAs($admin)->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('Dragan Jovanovic');

        $this->actingAs($admin)->get(route('parts.show', $part))
            ->assertOk()
            ->assertSee('Dragan Jovanovic');

        $this->actingAs($admin)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Dragan Jovanovic');

        // 7. The admin restores them and they can sign in again.
        $this->actingAs($admin)->patch(route('admin.users.restore', $mechanic))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        Auth::forgetGuards();
        $this->flushSession();

        $this->post('/login', ['email' => 'dragan@shop.rs', 'password' => 'correct-horse-battery']);
        $this->assertAuthenticatedAs($mechanic->fresh());
    }

    public function test_no_user_row_is_ever_hard_deleted_by_the_section(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->mechanic()->create();

        $before = User::withTrashed()->count();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $member));

        $this->assertSame($before, User::withTrashed()->count());
        $this->assertDatabaseHas('users', ['id' => $member->id]);
    }

    public function test_an_offboarded_mechanic_leaves_the_labor_line_picker_but_keeps_existing_lines(): void
    {
        $admin = User::factory()->admin()->create();
        $mechanic = User::factory()->mechanic()->create(['name' => 'Dragan Jovanovic']);

        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create(['user_id' => $mechanic->id]);

        $this->actingAs($admin)->get(route('jobs.show', $job, ['add' => 'labor']))
            ->assertOk()
            ->assertSee('Dragan Jovanovic');

        $this->actingAs($admin)->delete(route('admin.users.destroy', $mechanic));

        $this->actingAs($admin)->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('Dragan Jovanovic');

        $this->assertArrayNotHasKey(
            $mechanic->id,
            app(UserService::class)->pickerOptions(),
            'An offboarded mechanic must not be assignable to new labor.'
        );
    }
}
