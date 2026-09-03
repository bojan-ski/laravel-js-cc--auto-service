<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\StaffService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffOffboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_offboarding_soft_deletes_and_never_removes_the_row(): void
    {
        $member = User::factory()->mechanic()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.users.destroy', $member))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $member->id]);
        $this->assertDatabaseHas('users', ['id' => $member->id]);
        $this->assertNull(User::find($member->id), 'An offboarded user must leave the default scope.');
        $this->assertTrue(User::withTrashed()->find($member->id)->isOffboarded());
    }

    public function test_the_offboarded_account_stays_in_the_list_marked_offboarded(): void
    {
        $member = User::factory()->mechanic()->create(['name' => 'Nikola Stankovic']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $member));

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Nikola Stankovic')
            ->assertSee(__('staff.actions.restore'));
    }

    public function test_the_offboarded_person_cannot_sign_in(): void
    {
        $member = User::factory()->mechanic()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.users.destroy', $member));

        Auth::forgetGuards();
        $this->flushSession();

        $this->post('/login', ['email' => $member->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_offboarded_person_cannot_obtain_a_reset_link(): void
    {
        Notification::fake();

        $member = User::factory()->mechanic()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.users.destroy', $member));

        Auth::forgetGuards();
        $this->flushSession();

        $this->post('/forgot-password', ['email' => $member->email]);

        Notification::assertNothingSent();
    }

    public function test_an_offboarded_persons_open_session_is_a_guest_on_the_next_request(): void
    {
        $member = User::factory()->mechanic()->create();

        $this->post('/login', ['email' => $member->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($member);

        $this->get(route('jobs.index'))->assertOk();

        app(StaffService::class)->offboard($member);

        Auth::forgetGuards();

        $this->get(route('jobs.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * This proves the page renders all four bullets, NOT that the four bullets
     * say anything true — the expectation is read from the same `__()` call the
     * view uses. The substance of each claim is proven independently:
     * cannot-sign-in and no-reset-link below, name-stays-on-records and
     * restorable in StaffHistoryTest, address-stays-reserved in StaffCreateTest.
     */
    public function test_the_confirmation_page_states_every_consequence(): void
    {
        $member = User::factory()->mechanic()->create(['name' => 'Dragan Jovanovic']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.offboard', $member))
            ->assertOk();

        $consequences = __('staff.offboard.consequences');

        $this->assertIsArray($consequences);
        $this->assertCount(4, $consequences);

        foreach ($consequences as $consequence) {
            $response->assertSee($consequence);
        }

        $response->assertSee(__('staff.offboard.title', ['name' => 'Dragan Jovanovic']));
        $response->assertSee(__('staff.offboard.submit', ['name' => 'Dragan Jovanovic']));
    }

    public function test_the_confirmation_ties_the_consequences_to_the_submit_button(): void
    {
        $member = User::factory()->mechanic()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.offboard', $member))
            ->assertOk()
            ->assertSee('id="offboard-consequences"', false)
            ->assertSee('aria-describedby="offboard-consequences"', false)
            ->assertSee('name="_method" value="DELETE"', false);
    }

    public function test_offboarding_an_already_offboarded_account_is_not_found(): void
    {
        $gone = User::factory()->mechanic()->trashed()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.users.offboard', $gone))->assertNotFound();
        $this->actingAs($admin)->delete(route('admin.users.destroy', $gone))->assertNotFound();
    }
}
