<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidInvoiceTransition;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\CustomerMailer;
use App\Services\InvoiceNumberGenerator;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Voiding is the sole mechanism that reopens an invoiced job, so restricting it
 * to admins is what gives the lock its meaning. Both routes are gated by the
 * same ability: a receptionist must not even reach the confirmation.
 */
class InvoiceVoidRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function issued(): Invoice
    {
        $job = Job::factory()->completed()->create();

        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        return app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deniedRoles(): array
    {
        return ['mechanic' => ['mechanic'], 'receptionist' => ['receptionist']];
    }

    public function test_an_admin_reaches_the_confirmation_page(): void
    {
        $invoice = $this->issued();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('invoices.void.create', $invoice))
            ->assertOk()
            ->assertSee(__('invoices.void.heading'))
            ->assertSee($invoice->number);
    }

    public function test_an_admin_voids_an_unpaid_invoice_and_is_told_the_job_is_unlocked(): void
    {
        $invoice = $this->issued();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('invoices.void', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('success', __('invoices.flash.voided', [
                'number' => $invoice->number,
                'reference' => $invoice->job->reference,
            ]));

        $this->assertSame(InvoiceStatus::Void, $invoice->fresh()->status);
        $this->assertNull($invoice->job->fresh()->locked_at);
    }

    public function test_an_admin_voids_a_paid_invoice(): void
    {
        $invoice = app(InvoiceService::class)->markPaid($this->issued(), PaymentMethod::Cash);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('invoices.void', $invoice))
            ->assertRedirect(route('invoices.show', $invoice));

        $this->assertSame(InvoiceStatus::Void, $invoice->fresh()->status);
    }

    #[DataProvider('deniedRoles')]
    public function test_a_non_admin_is_refused_the_confirmation_page(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('invoices.void.create', $this->issued()))
            ->assertForbidden();
    }

    #[DataProvider('deniedRoles')]
    public function test_a_non_admin_is_refused_the_write_and_changes_nothing(string $role): void
    {
        $invoice = $this->issued();

        $this->actingAs(User::factory()->{$role}()->create())
            ->patch(route('invoices.void', $invoice))
            ->assertForbidden();

        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
        $this->assertNotNull($invoice->job->fresh()->locked_at, 'A refused void must leave the lock in place.');
    }

    public function test_a_void_invoice_is_refused_on_both_routes(): void
    {
        $invoice = Invoice::factory()->void()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('invoices.void.create', $invoice))->assertForbidden();
        $this->actingAs($admin)->patch(route('invoices.void', $invoice))->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login_from_both_routes(): void
    {
        $invoice = $this->issued();

        $this->get(route('invoices.void.create', $invoice))->assertRedirect(route('login'));
        $this->patch(route('invoices.void', $invoice))->assertRedirect(route('login'));
    }

    /**
     * The policy composes canBeVoided(), so a refusal is a 403 at the gate and
     * this exception is only reachable when the status changed between the gate
     * and the lock. That is a lost race, not a bug, so it flashes rather than
     * 500ing — the shape spec 17 established.
     */
    public function test_a_refusal_from_the_service_redirects_with_an_error_flash(): void
    {
        $invoice = $this->issued();

        $this->app->instance(InvoiceService::class, new RefusingVoidService);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('invoices.void', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('error', __('invoices.flash.not_voidable'));

        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
        $this->assertNotNull($invoice->job->fresh()->locked_at);
    }

    public function test_the_confirmation_page_is_a_get_and_the_write_is_a_patch(): void
    {
        $invoice = $this->issued();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('invoices.void', $invoice))
            ->assertMethodNotAllowed();

        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }
}

class RefusingVoidService extends InvoiceService
{
    public function __construct()
    {
        parent::__construct(new InvoiceNumberGenerator, new CustomerMailer);
    }

    public function void(Invoice $invoice, User $voidedBy): Invoice
    {
        throw new InvalidInvoiceTransition($invoice, ['status' => 'void'], 'refused for the test');
    }
}
