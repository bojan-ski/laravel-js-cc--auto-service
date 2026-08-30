<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Part;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FormComponentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function emailInputMarkup(string $html): string
    {
        preg_match('/<input[^>]*id="email"[^>]*>/', $html, $matches);

        $this->assertNotEmpty($matches, 'No email input was rendered.');

        return $matches[0];
    }

    public function test_a_field_without_an_error_carries_the_neutral_border(): void
    {
        $markup = $this->emailInputMarkup($this->get('/login')->getContent());

        $this->assertStringContainsString('border-border', $markup);
        $this->assertStringNotContainsString('border-status-cancelled', $markup);
    }

    public function test_an_errored_field_swaps_the_border_rather_than_stacking_it(): void
    {
        $html = $this->from('/login')
            ->followingRedirects()
            ->post('/login', ['email' => 'not-an-email', 'password' => ''])
            ->getContent();

        $markup = $this->emailInputMarkup($html);

        $this->assertStringContainsString('border-status-cancelled', $markup);
        $this->assertStringNotContainsString('border-border', $markup);
    }

    public function test_a_password_field_never_repopulates_its_value(): void
    {
        $html = $this->from('/login')
            ->followingRedirects()
            ->post('/login', ['email' => 'not-an-email', 'password' => 'secret-in-flight'])
            ->getContent();

        $this->assertStringNotContainsString('secret-in-flight', $html);
    }

    public function test_a_readonly_field_renders_the_readonly_attribute(): void
    {
        $html = $this->get('/reset-password/some-token?email=someone@auto-service.test')->getContent();

        $this->assertStringContainsString('readonly', $this->emailInputMarkup($html));
    }

    private function ownerSelectMarkup(string $html): string
    {
        preg_match('/<select[^>]*id="customer_id"[^>]*>/', $html, $matches);

        $this->assertNotEmpty($matches, 'No owner select was rendered.');

        return $matches[0];
    }

    public function test_a_select_without_an_error_carries_the_neutral_border(): void
    {
        $vehicle = Vehicle::factory()->create();
        Customer::factory()->create();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('vehicles.owner.edit', $vehicle))
            ->getContent();

        $markup = $this->ownerSelectMarkup($html);

        $this->assertStringContainsString('border-border', $markup);
        $this->assertStringNotContainsString('border-status-cancelled', $markup);
        $this->assertStringNotContainsString('aria-invalid', $markup);
    }

    public function test_an_errored_select_swaps_the_border_rather_than_stacking_it(): void
    {
        $vehicle = Vehicle::factory()->create();
        Customer::factory()->create();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->from(route('vehicles.owner.edit', $vehicle))
            ->followingRedirects()
            ->patch(route('vehicles.owner.update', $vehicle), ['customer_id' => ''])
            ->getContent();

        $markup = $this->ownerSelectMarkup($html);

        $this->assertStringContainsString('border-status-cancelled', $markup);
        $this->assertStringNotContainsString('border-border', $markup);
        $this->assertStringContainsString('aria-describedby="customer_id-error"', $markup);
    }

    private function reasonFieldsetMarkup(string $html): string
    {
        preg_match('/<fieldset\b[^>]*>/', $html, $matches);

        $this->assertNotEmpty($matches, 'No fieldset was rendered.');

        return $matches[0];
    }

    private function adjustForm(): string
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.adjust.create', Part::factory()->create()))
            ->assertOk()
            ->getContent();
    }

    public function test_a_radio_group_without_an_error_carries_the_neutral_border(): void
    {
        $markup = $this->reasonFieldsetMarkup($this->adjustForm());

        $this->assertStringContainsString('border-border', $markup);
        $this->assertStringNotContainsString('border-status-cancelled', $markup);
        $this->assertStringNotContainsString('aria-invalid', $markup);
    }

    /**
     * A bare `<fieldset>` maps to the implicit ARIA role `group`, which does not
     * support `aria-invalid` — assistive technology would drop the invalid state
     * silently while sighted users saw a red border. `role="radiogroup"` is a
     * role that does support it, and the explicit `aria-labelledby` keeps the
     * legend as the group's accessible name rather than relying on the native
     * fieldset mapping surviving the role override.
     */
    public function test_a_radio_group_declares_a_role_that_can_carry_an_invalid_state(): void
    {
        $html = $this->adjustForm();
        $markup = $this->reasonFieldsetMarkup($html);

        $this->assertStringContainsString('role="radiogroup"', $markup);
        $this->assertStringContainsString('aria-labelledby="reason-label"', $markup);
        $this->assertMatchesRegularExpression('/<legend[^>]*id="reason-label"/', $html);
    }

    public function test_a_required_radio_group_marks_its_options_required(): void
    {
        $html = $this->adjustForm();

        foreach (['restock', 'manual_adjustment'] as $value) {
            $this->assertMatchesRegularExpression(
                '/<input[^>]*id="reason-'.$value.'"[^>]*\srequired/',
                $html,
                "The {$value} radio does not carry the required attribute.",
            );
        }

        $this->assertStringNotContainsString('required', $this->reasonFieldsetMarkup($html));
    }

    public function test_an_errored_radio_group_swaps_the_border_rather_than_stacking_it(): void
    {
        $part = Part::factory()->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->from(route('parts.adjust.create', $part))
            ->followingRedirects()
            ->post(route('parts.adjust.store', $part), ['reason' => '', 'quantity_change' => 5])
            ->getContent();

        $markup = $this->reasonFieldsetMarkup($html);

        $this->assertStringContainsString('border-status-cancelled', $markup);
        $this->assertStringNotContainsString('border-border', $markup);
        $this->assertStringContainsString('aria-invalid="true"', $markup);
        $this->assertStringContainsString('aria-describedby="reason-error"', $markup);
        $this->assertStringContainsString('id="reason-error"', $html);
    }

    public function test_each_radio_option_is_labelled_and_tied_to_its_own_help_text(): void
    {
        $html = $this->adjustForm();

        foreach (['restock', 'manual_adjustment'] as $value) {
            $this->assertMatchesRegularExpression(
                '/<input[^>]*id="reason-'.$value.'"[^>]*aria-describedby="reason-'.$value.'-help"/',
                $html,
                "The {$value} radio is not tied to its help text.",
            );
            $this->assertStringContainsString('for="reason-'.$value.'"', $html);
            $this->assertStringContainsString('id="reason-'.$value.'-help"', $html);
            $this->assertStringContainsString(__('parts.adjust.help.'.$value), $html);
        }
    }

    public function test_a_radio_group_restores_the_chosen_option_after_a_failed_submit(): void
    {
        $part = Part::factory()->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->from(route('parts.adjust.create', $part))
            ->followingRedirects()
            ->post(route('parts.adjust.store', $part), [
                'reason' => 'manual_adjustment',
                'quantity_change' => -3,
            ])
            ->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*id="reason-manual_adjustment"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="reason-restock"[^>]*checked/', $html);
    }
}
