<?php

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class InvoicePolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_mechanic_may_not_view_any_invoice(): void
    {
        $this->actingAs(User::factory()->mechanic()->create());

        $this->assertFalse(Gate::allows('viewAny', Invoice::class));
        $this->assertFalse(Gate::allows('view', Invoice::factory()->create()));
    }

    public function test_a_receptionist_may_view_invoices(): void
    {
        $this->actingAs(User::factory()->receptionist()->create());

        $this->assertTrue(Gate::allows('viewAny', Invoice::class));
        $this->assertTrue(Gate::allows('view', Invoice::factory()->create()));
    }

    public function test_an_admin_may_view_invoices(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertTrue(Gate::allows('viewAny', Invoice::class));
        $this->assertTrue(Gate::allows('view', Invoice::factory()->create()));
    }

    /**
     * Guards the standing convention that no Gate::before() exists: an admin is
     * denied an ability nothing grants, so the policies are the only authority.
     */
    public function test_an_admin_is_denied_an_ability_no_policy_grants(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertFalse(Gate::allows('incinerate', Invoice::factory()->create()));
    }
}
