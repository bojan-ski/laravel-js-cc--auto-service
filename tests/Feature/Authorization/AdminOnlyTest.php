<?php

namespace Tests\Feature\Authorization;

use App\Exceptions\PartImportFailed;
use App\Models\Customer;
use App\Models\Part;
use App\Models\PartNumber;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\BrandPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PartPolicy;
use App\Policies\UserPolicy;
use App\Policies\VehiclePolicy;
use App\Services\PartImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use ReflectionMethod;
use Tests\Support\AuthorizationMatrix;
use Tests\TestCase;

/**
 * The eleven admin-only capabilities, each asserting a RECEPTIONIST refusal.
 *
 * These are the most valuable assertions in the matrix. A receptionist is
 * trusted with almost everything, which is exactly why an accidental
 * isFrontOffice() on a void or a cost price would look like the application
 * working correctly right up until it mattered.
 */
class AdminOnlyTest extends TestCase
{
    use AuthorizationMatrix;
    use LazilyRefreshDatabase;

    public function test_a_receptionist_is_refused_every_admin_only_capability(): void
    {
        $this->assertColumnRefuses('receptionist');
    }

    public function test_a_mechanic_is_refused_every_admin_only_capability(): void
    {
        $this->assertColumnRefuses('mechanic');
    }

    /**
     * Without this the refusals above could all pass against a capability that
     * is simply broken for everyone.
     */
    public function test_an_admin_is_permitted_every_admin_only_capability(): void
    {
        foreach (self::adminOnlyMatrix() as $key => $row) {
            if (($row['walkable'] ?? true) === false) {
                continue;
            }

            $admin = User::factory()->admin()->create();
            $this->seedScenario($admin);

            foreach ($row['requests'] as $request) {
                $this->assertCell(
                    $admin,
                    $request,
                    $this->verdictFor($row, $request, 'admin'),
                    $this->cellContext((string) $key, $row, $request, 'admin'),
                );
            }
        }
    }

    /**
     * Row 21, which has no route: the cost price is withheld by the query, so
     * the refusal is an ability rather than a status code.
     */
    public function test_a_receptionist_is_refused_the_part_cost_price_ability(): void
    {
        $this->assertFalse(Gate::forUser(User::factory()->receptionist()->create())->allows('viewCostPrice', Part::class));
        $this->assertFalse(Gate::forUser(User::factory()->mechanic()->create())->allows('viewCostPrice', Part::class));
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('viewCostPrice', Part::class));
    }

    /**
     * Row 29's other half. The overview's capability is "see soft-deleted
     * vehicles AND parts at all"; the parts half is a route refusal the matrix
     * walks, but the vehicle half has no route of its own to refuse.
     *
     * `vehicles.show` binds without withTrashed(), so a trashed vehicle 404s
     * for every role alike and proves nothing about who may see one. The only
     * place the question is decided is the customer page's conditional eager
     * load, so that is what this asserts — and it asserts the relation was not
     * LOADED, not merely that the section did not render, because a hidden
     * section is never the protection.
     */
    public function test_only_an_admin_sees_a_customers_deleted_vehicles(): void
    {
        $customer = Customer::factory()->create();
        Vehicle::factory()->for($customer)->create();

        $trashed = Vehicle::factory()->for($customer)->create(['registration_plate' => 'MTX-DEL']);
        $trashed->delete();

        foreach (['mechanic', 'receptionist'] as $role) {
            $response = $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('customers.show', $customer))
                ->assertOk();

            $this->assertFalse(
                $response->viewData('customer')->relationLoaded('deletedVehicles'),
                "A {$role} loaded the customer's soft-deleted vehicles.",
            );
            $response->assertDontSee('MTX-DEL');
        }

        $admin = $this->actingAs(User::factory()->admin()->create())
            ->get(route('customers.show', $customer))
            ->assertOk();

        // The control: without it, the refusals above would also pass against a
        // page that shows nobody their deleted vehicles.
        $this->assertTrue($admin->viewData('customer')->relationLoaded('deletedVehicles'));
        $admin->assertSee('MTX-DEL');
    }

    /**
     * Row 37: the fourth shape of the money boundary. The rule concerns the
     * contents of an uploaded file, so only the service can see it — and the
     * refusal rejects the WHOLE file rather than skipping the column.
     */
    public function test_a_receptionist_is_refused_a_cost_price_column_in_an_import(): void
    {
        $csv = "sku,name,quantity,unit_price,cost_price\nMTX-8001,Matrix part,3,1500.00,900.00\n";

        $receptionist = User::factory()->receptionist()->create();

        try {
            app(PartImportService::class)->import($this->csvUpload($csv), $receptionist);
            $this->fail('A receptionist imported a cost_price column.');
        } catch (PartImportFailed $failure) {
            $this->assertNotEmpty($failure->errors());
        }

        // The whole file is refused, not merely the column: nothing at all is
        // written, which is what makes this a refusal rather than a filter.
        $this->assertSame(0, Part::query()->count(), 'A refused import still wrote a part.');
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, PartNumber::query()->count());

        $admin = User::factory()->admin()->create();
        app(PartImportService::class)->import($this->csvUpload($csv), $admin);

        $this->assertSame(1, Part::query()->count(), 'An admin was refused their own cost_price column.');
        $this->assertSame('900.00', Part::query()->sole()->cost_price);
    }

    /**
     * The convention behind every row above, asserted by inspection as the
     * spec's Acceptance Criteria require: none of these methods may be written
     * in terms of isFrontOffice(), because a receptionist is front office.
     *
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function adminOnlyPolicyMethods(): array
    {
        return [
            'row 13 customers' => [CustomerPolicy::class, 'delete'],
            'row 13 vehicles' => [VehiclePolicy::class, 'delete'],
            'row 14' => [VehiclePolicy::class, 'reassign'],
            'row 20a' => [PartPolicy::class, 'delete'],
            'row 21 / 37 / 54' => [PartPolicy::class, 'viewCostPrice'],
            'row 25' => [InvoicePolicy::class, 'void'],
            'row 26 viewAny' => [UserPolicy::class, 'viewAny'],
            'row 26 create' => [UserPolicy::class, 'create'],
            'row 26 update' => [UserPolicy::class, 'update'],
            'row 26 delete' => [UserPolicy::class, 'delete'],
            'row 26 restore' => [UserPolicy::class, 'restore'],
            'row 27' => [VehiclePolicy::class, 'restore'],
            'row 28' => [PartPolicy::class, 'restore'],
            'row 29 parts' => [PartPolicy::class, 'viewDeleted'],
            'row 29 vehicles' => [VehiclePolicy::class, 'viewDeleted'],
            'row 33 viewAny' => [BrandPolicy::class, 'viewAny'],
            'row 33 create' => [BrandPolicy::class, 'create'],
            'row 33 update' => [BrandPolicy::class, 'update'],
            'row 33 delete' => [BrandPolicy::class, 'delete'],
            'row 33 restore' => [BrandPolicy::class, 'restore'],
        ];
    }

    public function test_no_policy_method_behind_an_admin_only_row_is_written_in_terms_of_front_office(): void
    {
        foreach (self::adminOnlyPolicyMethods() as $label => [$policy, $method]) {
            $source = $this->sourceOf($policy, $method);

            $this->assertStringNotContainsString(
                'isFrontOffice',
                $source,
                "{$label} ({$policy}::{$method}) admits every receptionist.",
            );
            $this->assertStringContainsString(
                'isAdmin',
                $source,
                "{$label} ({$policy}::{$method}) does not check for an admin at all.",
            );
        }
    }

    /**
     * Walk the admin-only rows for a role that must be refused every one.
     */
    private function assertColumnRefuses(string $role): void
    {
        $cells = 0;

        foreach (self::adminOnlyMatrix() as $key => $row) {
            if (($row['walkable'] ?? true) === false) {
                continue;
            }

            $actor = User::factory()->{$role}()->create();
            $this->seedScenario($actor);

            foreach ($row['requests'] as $request) {
                $verdict = $this->verdictFor($row, $request, $role);

                $this->assertContains(
                    $verdict,
                    [403, 404],
                    $this->cellContext((string) $key, $row, $request, $role).' is not declared as a refusal at all.',
                );

                $this->assertCell(
                    $actor,
                    $request,
                    $verdict,
                    $this->cellContext((string) $key, $row, $request, $role),
                );

                $cells++;
            }
        }

        // A literal floor rather than a count of the table being walked, which
        // would pass on an empty table. 25 cells across 9 routed rows today;
        // rows 21 and 37 have no route and are asserted separately above.
        $this->assertGreaterThanOrEqual(
            20,
            $cells,
            "Only {$cells} admin-only cells were refused for a {$role}; the admin-only set has shrunk.",
        );
    }

    private function csvUpload(string $contents): UploadedFile
    {
        return $this->realUpload('matrix-cost.csv', $contents, 'text/csv');
    }

    /**
     * @param  class-string  $policy
     */
    private function sourceOf(string $policy, string $method): string
    {
        $reflection = new ReflectionMethod($policy, $method);
        $lines = file((string) $reflection->getFileName());

        return implode('', array_slice(
            (array) $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));
    }
}
