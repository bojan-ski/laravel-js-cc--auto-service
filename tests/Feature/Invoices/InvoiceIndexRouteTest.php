<?php

namespace Tests\Feature\Invoices;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceIndexRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function frontOfficeRoles(): array
    {
        return ['receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function invoiceFor(string $customerName, array $state = []): Invoice
    {
        $customer = Customer::factory()->create(['name' => $customerName]);
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->for($customer)->for($vehicle)->completed()->create();

        return Invoice::factory()->forJob($job)->create($state);
    }

    private function rowCount(string $html): int
    {
        return preg_match_all('/href="[^"]*\/invoices\/\d+"/', $html);
    }

    public function test_a_guest_is_redirected_from_every_invoice_route(): void
    {
        $invoice = $this->invoiceFor('M. Petrović');

        $this->get(route('invoices.index'))->assertRedirect(route('login'));
        $this->get(route('invoices.show', $invoice))->assertRedirect(route('login'));
        $this->patch(route('invoices.paid', $invoice))->assertRedirect(route('login'));
    }

    public function test_a_mechanic_is_refused_on_every_invoice_route(): void
    {
        $invoice = $this->invoiceFor('M. Petrović');
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs($mechanic)->get(route('invoices.index'))->assertForbidden();
        $this->actingAs($mechanic)->get(route('invoices.show', $invoice))->assertForbidden();
        $this->actingAs($mechanic)->patch(route('invoices.paid', $invoice), ['payment_method' => 'cash'])->assertForbidden();
    }

    #[DataProvider('frontOfficeRoles')]
    public function test_front_office_may_view_the_list_and_the_detail(string $role): void
    {
        $invoice = $this->invoiceFor('M. Petrović');
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)->get(route('invoices.index'))->assertOk()->assertSee($invoice->number);
        $this->actingAs($user)->get(route('invoices.show', $invoice))->assertOk()->assertSee($invoice->number);
    }

    public function test_an_unknown_invoice_returns_404(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.show', 4040))
            ->assertNotFound();
    }

    public function test_the_list_shows_twenty_five_invoices_a_page(): void
    {
        Invoice::factory()->count(26)->create();

        $first = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(InvoiceService::PER_PAGE, $this->rowCount($first));
        $this->assertSame(25, $this->rowCount($first), 'The page size is 25, not merely whatever the constant says.');

        $second = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.index', ['page' => 2]))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, $this->rowCount($second));
    }

    /**
     * Two invoices issued in the same second must still paginate
     * deterministically, so `id` descending breaks the tie.
     */
    public function test_the_list_orders_by_issued_at_descending_with_an_id_tiebreak(): void
    {
        $older = $this->invoiceFor('A. Nikolić', ['issued_at' => now()->subDay()]);
        $sameSecondFirst = $this->invoiceFor('J. Ilić', ['issued_at' => now()]);
        $sameSecondSecond = $this->invoiceFor('M. Petrović', ['issued_at' => now()]);

        $this->assertGreaterThan($sameSecondFirst->getKey(), $sameSecondSecond->getKey());

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSeeInOrder([
                $sameSecondSecond->number,
                $sameSecondFirst->number,
                $older->number,
            ]);
    }

    public function test_a_full_page_of_invoices_issues_a_pinned_number_of_queries(): void
    {
        Invoice::factory()->count(25)->create();
        $user = User::factory()->receptionist()->create();

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('invoices.index'))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(6, $queries, "Expected 6 queries:\n".implode("\n", array_column($queries, 'query')));
    }
}
