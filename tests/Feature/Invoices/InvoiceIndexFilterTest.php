<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InvoiceIndexFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function invoiceFor(string $customerName, string $state = 'unpaid'): Invoice
    {
        $customer = Customer::factory()->create(['name' => $customerName]);
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->for($customer)->for($vehicle)->completed()->create();

        return Invoice::factory()->forJob($job)->{$state}()->create();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function page(array $query = []): string
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.index', $query))
            ->assertOk()
            ->getContent();
    }

    /**
     * The tab counts in document order: All, Unpaid, Paid, Void.
     *
     * @return array<int, int>
     */
    private function tabCounts(string $html): array
    {
        preg_match(
            '/<nav[^>]*aria-label="'.preg_quote(__('invoices.tabs.label'), '/').'".*?<\/nav>/s',
            $html,
            $nav,
        );

        $this->assertNotEmpty($nav, 'The status tabs did not render.');

        preg_match_all('/aria-hidden="true">(\d+)<\/span>/', $nav[0], $counts);

        return array_map('intval', $counts[1]);
    }

    public function test_each_status_tab_shows_only_that_status(): void
    {
        $unpaid = $this->invoiceFor('A. Nikolić');
        $paid = $this->invoiceFor('J. Ilić', 'paid');
        $void = $this->invoiceFor('M. Petrović', 'void');

        $unpaidPage = $this->page(['status' => InvoiceStatus::Unpaid->value]);
        $this->assertStringContainsString($unpaid->number, $unpaidPage);
        $this->assertStringNotContainsString($paid->number, $unpaidPage);
        $this->assertStringNotContainsString($void->number, $unpaidPage);

        $paidPage = $this->page(['status' => InvoiceStatus::Paid->value]);
        $this->assertStringContainsString($paid->number, $paidPage);
        $this->assertStringNotContainsString($unpaid->number, $paidPage);
    }

    /**
     * Void invoices are retained forever and stay visible; one that vanished
     * would make the numbering look like it had gaps.
     */
    public function test_a_void_invoice_appears_in_all_and_in_its_own_tab(): void
    {
        $void = $this->invoiceFor('M. Petrović', 'void');

        $this->assertStringContainsString($void->number, $this->page());
        $this->assertStringContainsString($void->number, $this->page(['status' => InvoiceStatus::Void->value]));
    }

    public function test_the_tab_counts_match_and_all_equals_their_sum(): void
    {
        $this->invoiceFor('A. Nikolić');
        $this->invoiceFor('B. Marković');
        $this->invoiceFor('J. Ilić', 'paid');
        $this->invoiceFor('M. Petrović', 'void');

        $counts = $this->tabCounts($this->page());

        $this->assertSame([4, 2, 1, 1], $counts);
        $this->assertSame($counts[0], $counts[1] + $counts[2] + $counts[3]);
    }

    /**
     * The counts respect the search term but not the status filter, or the All
     * tab stops equalling the sum the moment a status is selected.
     */
    public function test_the_counts_respect_the_search_but_not_the_selected_status(): void
    {
        $this->invoiceFor('Petrović Holdings');
        $this->invoiceFor('Petrović Holdings', 'paid');
        $this->invoiceFor('Unrelated Garage', 'paid');

        $counts = $this->tabCounts($this->page(['search' => 'Petrović', 'status' => InvoiceStatus::Paid->value]));

        $this->assertSame([2, 1, 1, 0], $counts, 'The search must narrow the counts and the status must not.');
        $this->assertSame($counts[0], $counts[1] + $counts[2] + $counts[3]);
    }

    public function test_search_matches_a_partial_invoice_number(): void
    {
        $wanted = $this->invoiceFor('A. Nikolić');
        $other = $this->invoiceFor('J. Ilić');

        $fragment = substr($wanted->number, -4);
        $html = $this->page(['search' => $fragment]);

        $this->assertStringContainsString($wanted->number, $html);
        $this->assertStringNotContainsString($other->number, $html);
    }

    public function test_search_matches_a_partial_customer_name(): void
    {
        $wanted = $this->invoiceFor('Milica Petrović');
        $other = $this->invoiceFor('Jovan Ilić');

        $html = $this->page(['search' => 'etrov']);

        $this->assertStringContainsString($wanted->number, $html);
        $this->assertStringNotContainsString($other->number, $html);
    }

    /**
     * Search reads the invoice's own buyer_name snapshot, which no customer
     * change reaches — so deleting the customer cannot hide the invoice.
     */
    public function test_search_finds_an_invoice_whose_customer_is_soft_deleted(): void
    {
        $invoice = $this->invoiceFor('Milica Petrović');
        $invoice->job->customer->delete();

        $this->assertSoftDeleted($invoice->job->customer);

        $html = $this->page(['search' => 'Petrović']);

        $this->assertStringContainsString($invoice->number, $html);
        $this->assertSame([1, 1, 0, 0], $this->tabCounts($html));
    }

    public function test_a_percent_sign_is_escaped_rather_than_matching_everything(): void
    {
        $this->invoiceFor('A. Nikolić');

        $html = $this->page(['search' => '%']);

        $this->assertStringContainsString(__('invoices.empty.no_matches.title'), $html);
    }

    /**
     * `_` is LIKE's single-character wildcard, so an unescaped one would make
     * "P_trović" match "Petrović" too.
     */
    public function test_an_underscore_is_escaped_rather_than_matching_any_character(): void
    {
        $literal = $this->invoiceFor('P_trović');
        $wildcarded = $this->invoiceFor('Petrović');

        $html = $this->page(['search' => 'P_trović']);

        $this->assertStringContainsString($literal->number, $html);
        $this->assertStringNotContainsString($wildcarded->number, $html);
    }

    public function test_an_unknown_status_is_refused_by_validation(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.index', ['status' => 'settled']))
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    }

    public function test_an_overlong_search_is_refused_by_validation(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.index', ['search' => str_repeat('a', 256)]))
            ->assertRedirect()
            ->assertSessionHasErrors('search');
    }

    /**
     * Every tab has to carry the search term forward and drop the page, or
     * switching status from page 3 lands on an empty page 3 of the new filter.
     */
    public function test_each_tab_url_preserves_the_search_and_drops_the_page(): void
    {
        $this->invoiceFor('Petrović Holdings');

        $html = $this->page(['search' => 'Petrović', 'status' => InvoiceStatus::Unpaid->value, 'page' => 1]);

        preg_match(
            '/<nav[^>]*aria-label="'.preg_quote(__('invoices.tabs.label'), '/').'".*?<\/nav>/s',
            $html,
            $nav,
        );

        preg_match_all('/href="([^"]+)"/', $nav[0], $hrefs);

        $this->assertCount(4, $hrefs[1], 'All, Unpaid, Paid and Void.');

        foreach ($hrefs[1] as $href) {
            $this->assertStringContainsString('search=Petrovi', $href, "Tab {$href} dropped the search.");
            $this->assertStringNotContainsString('page=', $href, "Tab {$href} carried the page number.");
        }

        $this->assertStringNotContainsString('status=', $hrefs[1][0], 'The All tab must clear the status.');
        $this->assertStringContainsString('status=void', $hrefs[1][3]);
    }

    /**
     * Every other compose test matches on the customer branch; this one drives
     * the number branch, where a leaking OR would let a status filter stop
     * constraining anything matched by number.
     *
     * Note the belt here is not the closure in `matching()`: Eloquent's
     * callScope() counts the wheres a scope adds and wraps them with
     * addNewWheresWithinGroup(), so the grouping holds even without it —
     * measured, not assumed. The closure stays because it says so explicitly
     * and matches Job::matching(), not because removing it breaks this.
     */
    public function test_a_number_match_still_obeys_the_status_filter(): void
    {
        $unpaid = $this->invoiceFor('Zzz Unrelated Garage');
        $fragment = substr($unpaid->number, -4);

        $filtered = $this->page(['search' => $fragment, 'status' => InvoiceStatus::Paid->value]);
        $this->assertStringNotContainsString(
            $unpaid->number,
            $filtered,
            'An invoice matched by number must still be excluded by a status it does not have.',
        );

        $unfiltered = $this->page(['search' => $fragment, 'status' => InvoiceStatus::Unpaid->value]);
        $this->assertStringContainsString(
            $unpaid->number,
            $unfiltered,
            'The same fragment must find it under its own status, or the assertion above is vacuous.',
        );
    }

    public function test_the_counts_are_all_zero_when_nothing_matches(): void
    {
        $this->invoiceFor('A. Nikolić');

        $this->assertSame([0, 0, 0, 0], $this->tabCounts($this->page(['search' => 'nothing-matches-this'])));
    }

    public function test_the_search_form_carries_the_active_status_as_a_hidden_field(): void
    {
        $withStatus = $this->page(['status' => InvoiceStatus::Paid->value]);
        $this->assertStringContainsString('<input type="hidden" name="status" value="paid">', $withStatus);

        $this->assertStringNotContainsString('name="status"', $this->page());
    }

    public function test_the_clear_search_control_drops_the_search_but_keeps_the_status(): void
    {
        $this->invoiceFor('Petrović Holdings', 'paid');

        $html = $this->page(['search' => 'Petrović', 'status' => InvoiceStatus::Paid->value]);

        $this->assertStringContainsString(
            'href="'.route('invoices.index', ['status' => InvoiceStatus::Paid->value]).'"',
            $html,
            'Clearing the search must not also clear the status tab.',
        );
    }

    public function test_status_and_search_compose_and_survive_pagination(): void
    {
        $customer = Customer::factory()->create(['name' => 'Petrović Holdings']);
        $vehicle = Vehicle::factory()->for($customer)->create();

        foreach (range(1, 26) as $ignored) {
            $job = Job::factory()->for($customer)->for($vehicle)->completed()->create();
            Invoice::factory()->forJob($job)->paid()->create();
        }

        $this->invoiceFor('Unrelated Garage', 'paid');

        $html = $this->page([
            'status' => InvoiceStatus::Paid->value,
            'search' => 'Petrović',
            'page' => 2,
        ]);

        $this->assertSame(1, preg_match_all('/href="[^"]*\/invoices\/\d+"/', $html), 'Page 2 holds the 26th match.');
        $this->assertStringContainsString('search=Petrovi', $html, 'The pagination links must carry the search.');
        $this->assertStringContainsString('status=paid', $html, 'The pagination links must carry the status.');
    }

    public function test_the_three_empty_states_are_distinct(): void
    {
        $none = $this->page();
        $this->assertStringContainsString(__('invoices.empty.none.title'), $none);
        $this->assertStringNotContainsString(__('invoices.empty.no_matches.title'), $none);
        $this->assertStringNotContainsString(__('invoices.empty.none_in_status.title'), $none);

        $this->invoiceFor('A. Nikolić');

        $noStatus = $this->page(['status' => InvoiceStatus::Paid->value]);
        $this->assertStringContainsString(__('invoices.empty.none_in_status.title'), $noStatus);
        $this->assertStringNotContainsString(__('invoices.empty.none.title'), $noStatus);

        $noMatch = $this->page(['search' => 'nothing-matches-this']);
        $this->assertStringContainsString(__('invoices.empty.no_matches.title'), $noMatch);
        $this->assertStringNotContainsString(__('invoices.empty.none.title'), $noMatch);
    }
}
