<?php

namespace Tests\Feature\Quality;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Part;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\WalksPages;
use Tests\TestCase;

/**
 * The shop's first morning: a fresh migration, one seeded administrator, and
 * nothing else. Every feature was built against data its developer had just
 * created; this is the state nobody builds in and the one the shop judges
 * the application by.
 */
class EmptyInstallTest extends TestCase
{
    use LazilyRefreshDatabase;
    use WalksPages;

    /**
     * @var array<string, list<string>> route => the resolved copy its empty state must show
     */
    private const EMPTY_STATES = [
        'dashboard' => ['dashboard.today.empty', 'dashboard.active_jobs.empty', 'dashboard.low_stock.empty'],
        'jobs.index' => ['jobs.board.empty.none.title'],
        'customers.index' => ['customers.empty.none.title'],
        'appointments.index' => ['appointments.empty.none_title'],
        'parts.index' => ['parts.empty.none.title'],
        'invoices.index' => ['invoices.empty.none.title'],
        'admin.brands.index' => ['brands.empty.none.title'],
    ];

    public function test_the_install_holds_only_the_seeded_admin(): void
    {
        $this->seedAdmin();

        $this->assertSame(1, User::withTrashed()->count());
        $this->assertSame(0, Customer::withTrashed()->count());
        $this->assertSame(0, Vehicle::withTrashed()->count());
        $this->assertSame(0, Appointment::query()->count());
        $this->assertSame(0, Job::query()->count());
        $this->assertSame(0, Part::withTrashed()->count());
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_every_parameterless_page_renders_on_an_empty_install(): void
    {
        $admin = $this->seedAdmin();
        $this->installViolationCollectors();
        $this->actingAs($admin);

        $walked = 0;

        foreach (self::pageInventory() as $label => $entry) {
            if (! ($entry['parameterless'] ?? false) || ! in_array('admin', $entry['roles'], true)) {
                continue;
            }

            $this->currentPage = $label;
            $response = $this->get($this->pageUrl($entry, null));

            $this->assertPageRendered($response, $label);
            $this->assertNoBareKey($response, $label, $entry);

            foreach (self::EMPTY_STATES[$entry['route']] ?? [] as $key) {
                $this->assertNotSame($key, __($key));
                $response->assertSee(__($key));
            }

            $walked++;
        }

        $this->currentPage = 'admin.users.index';
        $this->get(route('admin.users.index'))->assertOk()->assertSee($admin->name);

        $this->currentPage = 'admin.users.edit:self';
        $self = $this->get(route('admin.users.edit', $admin));
        $this->assertPageRendered($self, 'admin.users.edit:self');
        $this->assertNoBareKey($self, 'admin.users.edit:self', []);

        $this->assertNoViolations('The empty-install walk');
        $this->assertGreaterThanOrEqual(15, $walked, "Only {$walked} parameterless pages were walked; the inventory has shrunk.");
    }

    public function test_the_guest_pages_render_on_an_empty_install(): void
    {
        $this->seedAdmin();
        $walked = 0;

        foreach (self::pageInventory() as $label => $entry) {
            if (! ($entry['parameterless'] ?? false) || ! in_array('guest', $entry['roles'], true)) {
                continue;
            }

            $response = $this->get($this->pageUrl($entry, null));

            $this->assertPageRendered($response, $label);
            $this->assertNoBareKey($response, $label, $entry);
            $walked++;
        }

        $this->assertSame(2, $walked, 'The parameterless guest pages should be exactly login and forgot-password.');
    }
}
