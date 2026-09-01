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
     * markPaid composes the role with the invoice's own guard, so both halves
     * have to be exercised independently — a policy that only checked the role
     * would let a paid invoice be paid twice, and one that only checked the
     * guard would let a mechanic record payment.
     */
    public function test_only_front_office_may_mark_an_unpaid_invoice_paid(): void
    {
        $unpaid = Invoice::factory()->unpaid()->create();

        $this->actingAs(User::factory()->mechanic()->create());
        $this->assertFalse(Gate::allows('markPaid', $unpaid));

        $this->actingAs(User::factory()->receptionist()->create());
        $this->assertTrue(Gate::allows('markPaid', $unpaid));

        $this->actingAs(User::factory()->admin()->create());
        $this->assertTrue(Gate::allows('markPaid', $unpaid));
    }

    public function test_no_role_may_mark_a_paid_or_void_invoice_paid(): void
    {
        $paid = Invoice::factory()->paid()->create();
        $void = Invoice::factory()->void()->create();

        foreach (['receptionist', 'admin'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());

            $this->assertFalse(Gate::allows('markPaid', $paid), "A {$role} must not re-pay a paid invoice.");
            $this->assertFalse(Gate::allows('markPaid', $void), "A {$role} must not pay a void invoice.");
        }
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
