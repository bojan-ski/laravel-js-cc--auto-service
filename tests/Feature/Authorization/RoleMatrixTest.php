<?php

namespace Tests\Feature\Authorization;

use App\Models\Part;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\AuthorizationMatrix;
use Tests\TestCase;

/**
 * The authorization matrix, walked once per role.
 *
 * Every assertion is made against a route, never against rendered HTML: a
 * hidden control is never the protection. Much of this is already green
 * elsewhere — AccessControlTest, JobAuthorizationTest, PartAuthorizationTest,
 * StaffAccessTest and the rest — and that duplication is deliberate. This is
 * the one place the whole matrix can be read as a matrix.
 */
class RoleMatrixTest extends TestCase
{
    use AuthorizationMatrix;
    use LazilyRefreshDatabase;

    public function test_the_guest_column_holds(): void
    {
        $this->walkAs(null);
    }

    public function test_the_mechanic_column_holds(): void
    {
        $this->walkAs('mechanic');
    }

    public function test_the_receptionist_column_holds(): void
    {
        $this->walkAs('receptionist');
    }

    public function test_the_admin_column_holds(): void
    {
        $this->walkAs('admin');
    }

    /**
     * Row 1, off the walk: posting credentials mid-walk would change who the
     * walker is signed in as. Asserted here where authentication itself — not
     * merely the status code — can be checked.
     */
    public function test_every_role_can_log_in(): void
    {
        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertRedirect(route('dashboard'));

            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'));
        }
    }

    /**
     * Row 32, off the walk for the same reason: it ends the walker's session.
     */
    public function test_every_role_can_log_out_and_a_guest_cannot(): void
    {
        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->post(route('logout'))
                ->assertRedirect();

            $this->assertGuest();
        }

        $this->post(route('logout'))->assertRedirect(route('login'));
    }

    /**
     * Row 34: a part's identifiers are shop knowledge, not commercial
     * information, so a mechanic sees them. Asserted on the page because the
     * capability IS the rendering — there is no separate route to refuse.
     */
    public function test_every_role_sees_a_parts_oe_and_catalogue_numbers(): void
    {
        $part = Part::factory()->inStock()->create();
        PartNumber::factory()->for($part)->create(['type' => 'oe', 'number' => '1K0 615 301 AA']);
        PartNumber::factory()->for($part)->create(['type' => 'catalog', 'number' => 'BP-9911']);

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('parts.show', $part))
                ->assertOk()
                ->assertSee('1K0 615 301 AA')
                ->assertSee('BP-9911');
        }
    }

    /**
     * Walk every row of the matrix for one role.
     *
     * The scenario is rebuilt before each row, not shared across the walk:
     * permitted cells write — a cancel, a mark paid, a restore — and a shared
     * scenario would let row order decide whether the next row finds the state
     * it assumes.
     */
    private function walkAs(?string $role): void
    {
        $column = $role ?? 'guest';
        $cells = 0;

        foreach (self::walkableMatrix() as $key => $row) {
            $actor = $role === null ? null : User::factory()->{$role}()->create();

            $this->seedScenario($actor);

            foreach ($row['requests'] as $request) {
                $this->assertCell(
                    $actor,
                    $request,
                    $this->verdictFor($row, $request, $column),
                    $this->cellContext((string) $key, $row, $request, $column),
                );

                $cells++;
            }
        }

        // A literal floor, not count(walkableMatrix()): comparing the walk to
        // the same table it walked would pass on an empty table, which is
        // exactly the failure this guards. 89 cells today.
        $this->assertGreaterThanOrEqual(
            80,
            $cells,
            "The {$column} walk asserted only {$cells} cells; the matrix has shrunk or stopped loading.",
        );
    }
}
