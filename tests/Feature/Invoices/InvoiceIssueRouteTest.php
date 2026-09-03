<?php

namespace Tests\Feature\Invoices;

use App\Exceptions\InvoiceNotIssuable;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\CustomerMailer;
use App\Services\InvoiceNumberGenerator;
use App\Services\InvoiceService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceIssueRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function invoiceableJob(): Job
    {
        $job = Job::factory()->completed()->create();

        JobItem::factory()->forJob($job)->create(['quantity' => 1.50, 'unit_price' => 3000]);

        return $job;
    }

    public function test_a_guest_is_redirected_to_login_from_both_routes(): void
    {
        $job = $this->invoiceableJob();

        $this->get(route('jobs.invoice.create', $job))->assertRedirect(route('login'));
        $this->post(route('jobs.invoice.store', $job))->assertRedirect(route('login'));
    }

    public function test_a_mechanic_is_refused_on_both_routes(): void
    {
        $job = $this->invoiceableJob();
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs($mechanic)->get(route('jobs.invoice.create', $job))->assertForbidden();
        $this->actingAs($mechanic)->post(route('jobs.invoice.store', $job))->assertForbidden();

        $this->assertSame(0, Invoice::query()->count());
        $this->assertNull($job->fresh()->locked_at);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function frontOfficeRoles(): array
    {
        return ['receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    #[DataProvider('frontOfficeRoles')]
    public function test_front_office_may_open_the_confirmation(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.invoice.create', $this->invoiceableJob()))
            ->assertOk();
    }

    #[DataProvider('frontOfficeRoles')]
    public function test_front_office_may_issue(string $role): void
    {
        $job = $this->invoiceableJob();

        $response = $this->actingAs(User::factory()->{$role}()->create())
            ->post(route('jobs.invoice.store', $job));

        $invoice = Invoice::query()->sole();

        $response->assertRedirect(route('invoices.show', $invoice));

        $this->assertTrue($invoice->isUnpaid());
        $this->assertNotNull($job->fresh()->locked_at);
    }

    public function test_issuing_flashes_the_invoice_number(): void
    {
        $job = $this->invoiceableJob();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.invoice.store', $job))
            ->assertSessionHas('success', __('invoices.flash.issued', [
                'number' => Invoice::query()->sole()->number,
            ]));
    }

    public function test_the_issuer_is_the_acting_user(): void
    {
        $job = $this->invoiceableJob();
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->post(route('jobs.invoice.store', $job));

        $this->assertSame($user->getKey(), Invoice::query()->sole()->issued_by);
    }

    public function test_a_due_date_is_stored(): void
    {
        $job = $this->invoiceableJob();
        $due = now()->addWeek()->startOfDay();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.invoice.store', $job), ['due_at' => $due->toDateString()]);

        $this->assertTrue(Invoice::query()->sole()->due_at->equalTo($due));
    }

    public function test_an_empty_due_date_is_stored_as_null(): void
    {
        $job = $this->invoiceableJob();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.invoice.store', $job), ['due_at' => ''])
            ->assertRedirect(route('invoices.show', Invoice::query()->sole()));

        $this->assertNull(Invoice::query()->sole()->due_at);
    }

    public function test_a_due_date_in_the_past_is_refused(): void
    {
        $job = $this->invoiceableJob();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.invoice.store', $job), [
                'due_at' => now()->subDay()->toDateString(),
            ])
            ->assertSessionHasErrors('due_at');

        $this->assertSame(0, Invoice::query()->count());
        $this->assertNull($job->fresh()->locked_at);
    }

    public function test_today_is_an_acceptable_due_date(): void
    {
        $job = $this->invoiceableJob();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.invoice.store', $job), ['due_at' => now()->toDateString()])
            ->assertSessionHasNoErrors();

        $this->assertNotNull(Invoice::query()->sole()->due_at);
    }

    public function test_a_job_that_cannot_be_invoiced_is_refused_on_both_routes(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create();
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)->get(route('jobs.invoice.create', $job))->assertForbidden();
        $this->actingAs($receptionist)->post(route('jobs.invoice.store', $job))->assertForbidden();
    }

    public function test_a_second_submit_after_a_successful_issue_is_refused_and_writes_nothing(): void
    {
        $job = $this->invoiceableJob();
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)->post(route('jobs.invoice.store', $job));
        $this->assertSame(1, Invoice::query()->count());

        $this->actingAs($receptionist)
            ->post(route('jobs.invoice.store', $job))
            ->assertForbidden();

        $this->actingAs($receptionist)
            ->get(route('jobs.invoice.create', $job))
            ->assertForbidden();

        $this->assertSame(1, Invoice::query()->count(), 'The second attempt must write nothing.');
    }

    /**
     * The loser of a genuine race passes the policy check against an unlocked
     * job, then fails the service's re-check under the row lock. That window
     * cannot be opened from a test — LazilyRefreshDatabase makes the service's
     * transaction a savepoint, so no second connection can contend — so the
     * service is stubbed to throw exactly what a lost race throws. What this
     * pins is the controller's handling: a redirect to the job with an error
     * flash, never an unhandled 500.
     */
    public function test_a_refusal_from_the_service_redirects_with_an_error_flash(): void
    {
        $job = $this->invoiceableJob();

        $this->app->instance(InvoiceService::class, new RefusingInvoiceService);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.invoice.store', $job))
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('error', __('invoices.flash.not_issuable'));

        $this->assertSame(0, Invoice::query()->count());
        $this->assertNull($job->fresh()->locked_at);
    }
}

class RefusingInvoiceService extends InvoiceService
{
    public function __construct()
    {
        parent::__construct(new InvoiceNumberGenerator, new CustomerMailer);
    }

    public function issue(Job $job, User $issuedBy, ?CarbonInterface $dueAt = null): Invoice
    {
        throw new InvoiceNotIssuable($job, InvoiceNotIssuable::ALREADY_INVOICED);
    }
}
