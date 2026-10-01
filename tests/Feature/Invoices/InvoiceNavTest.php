<?php

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\User;
use App\View\Components\Global\Nav;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceNavTest extends TestCase
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
     * nav.blade.php renders its menu twice — a desktop `<nav>` and a mobile
     * `<details>` — so "not visible to a mechanic" has to hold in both.
     */
    public function test_a_mechanic_sees_no_invoices_link_in_either_menu(): void
    {
        $html = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(0, substr_count($html, 'href="'.route('invoices.index').'"'));
        $this->assertStringNotContainsString(__('app.nav.invoices'), $html);
    }

    #[DataProvider('frontOfficeRoles')]
    public function test_front_office_sees_the_invoices_link_in_both_menus(string $role): void
    {
        $html = $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            2,
            substr_count($html, 'href="'.route('invoices.index').'"'),
            'The item must appear in the desktop nav and the mobile menu.',
        );
    }

    public function test_the_item_lands_after_parts_for_front_office(): void
    {
        $this->actingAs(User::factory()->receptionist()->create());

        $this->assertSame([
            __('app.nav.jobs'),
            __('app.nav.appointments'),
            __('app.nav.customers'),
            __('app.nav.parts'),
            __('app.nav.invoices'),
            __('app.nav.reports'),
        ], array_column((new Nav)->items, 'label'));
    }

    public function test_a_mechanics_item_list_ends_at_parts(): void
    {
        $this->actingAs(User::factory()->mechanic()->create());

        $this->assertSame([
            __('app.nav.jobs'),
            __('app.nav.appointments'),
            __('app.nav.customers'),
            __('app.nav.parts'),
        ], array_column((new Nav)->items, 'label'));
    }

    public function test_the_invoices_item_is_current_across_invoice_routes(): void
    {
        $invoice = Invoice::factory()->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<a\s+href="'.preg_quote(route('invoices.index'), '/').'"[^>]*aria-current="page"/',
            $html,
            'The nav item must be current on invoices.show, not only on invoices.index.',
        );
    }
}
