<?php

namespace Tests\Feature;

use App\Models\Customer;
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
}
