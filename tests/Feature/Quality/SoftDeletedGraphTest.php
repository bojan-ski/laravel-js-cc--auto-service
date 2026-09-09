<?php

namespace Tests\Feature\Quality;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\QaFixture;
use Tests\Support\WalksPages;
use Tests\TestCase;

/**
 * Every page renders when the customer, the vehicle, and every user a page
 * attributes work to are all soft-deleted at once. Each spec proved its own
 * withTrashed() relations; this is the one place the convention is proven
 * application-wide.
 *
 * A trashed user cannot log in, so "the acting user soft-deleted" is read as
 * the ATTRIBUTED users — creator, technician, issuer, voider, uploader — while
 * the walker stays signed in as a live account of each role.
 *
 * The eight pages whose route binding carries no withTrashed() legitimately
 * 404 on a trashed record; the inventory names them, and everything else
 * must still be 200.
 */
class SoftDeletedGraphTest extends TestCase
{
    use LazilyRefreshDatabase;
    use WalksPages;

    public function test_the_graph_is_actually_trashed(): void
    {
        $shop = QaFixture::build();
        $customers = Customer::withTrashed()->count();
        $vehicles = Vehicle::withTrashed()->count();
        $users = User::withTrashed()->count();

        $shop->trashGraph();

        $this->assertSame(0, Customer::query()->count());
        $this->assertSame(0, Vehicle::query()->count());
        $this->assertSame(3, User::query()->count());
        $this->assertSame($customers, Customer::withTrashed()->count(), 'A customer row was removed rather than soft-deleted.');
        $this->assertSame($vehicles, Vehicle::withTrashed()->count(), 'A vehicle row was removed rather than soft-deleted.');
        $this->assertSame($users, User::withTrashed()->count(), 'A user row was removed rather than soft-deleted.');
        $this->assertGreaterThanOrEqual(25, $customers);
    }

    #[DataProvider('roles')]
    public function test_every_page_renders_over_a_trashed_graph(string $role, int $floor): void
    {
        $shop = QaFixture::build();
        $shop->trashGraph();
        $this->installViolationCollectors();

        $walked = $this->walk($shop, $this->actorFor($shop, $role), $role, function (string $label, array $entry, TestResponse $response): void {
            $this->assertPageRendered($response, $label, $entry['trashed'] ?? 200);
            $this->assertNoBareKey($response, $label, $entry);
        });

        $this->assertNoViolations("The {$role} walk over a trashed graph");
        $this->assertGreaterThanOrEqual($floor, $walked, "The {$role} walk rendered only {$walked} pages; the inventory has shrunk.");
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function roles(): array
    {
        return [
            'mechanic' => ['mechanic', 50],
            'receptionist' => ['receptionist', 70],
            'admin' => ['admin', 90],
        ];
    }
}
