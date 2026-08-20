<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UiComponentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * The unprefixed background utilities on the rendered element. A variant
     * hover state such as hover:bg-brand-dark is deliberately excluded — it
     * sets the same property in a different state, so only these compete.
     *
     * @return array<int, string>
     */
    private function backgroundUtilities(string $html): array
    {
        preg_match('/class="([^"]*)"/', $html, $matches);

        $classes = preg_split('/\s+/', trim($matches[1] ?? ''));

        return array_values(array_filter($classes, fn (string $class): bool => str_starts_with($class, 'bg-')));
    }

    public function test_the_primary_button_emits_exactly_one_background(): void
    {
        $html = Blade::render('<x-ui.button>Save</x-ui.button>');

        $this->assertSame(['bg-brand'], $this->backgroundUtilities($html));
        $this->assertStringContainsString('<button type="submit"', $html);
    }

    public function test_the_secondary_button_emits_exactly_one_background(): void
    {
        $html = Blade::render('<x-ui.button variant="secondary">Search</x-ui.button>');

        $this->assertSame(['bg-surface'], $this->backgroundUtilities($html));
    }

    public function test_the_danger_button_emits_exactly_one_background(): void
    {
        $html = Blade::render('<x-ui.button variant="danger">Delete</x-ui.button>');

        $this->assertSame(['bg-danger'], $this->backgroundUtilities($html));
    }

    public function test_the_danger_button_uses_the_action_token_not_the_status_token(): void
    {
        $html = Blade::render('<x-ui.button variant="danger">Delete</x-ui.button>');

        $this->assertStringNotContainsString('status-cancelled', $html);
    }

    public function test_a_button_with_an_href_renders_an_anchor(): void
    {
        $html = Blade::render('<x-ui.button href="/customers">View</x-ui.button>');

        $this->assertStringContainsString('<a href="/customers"', $html);
        $this->assertStringNotContainsString('<button', $html);
    }

    private function notesTextareaMarkup(string $html): string
    {
        preg_match('/<textarea[^>]*id="notes"[^>]*>/', $html, $matches);

        $this->assertNotEmpty($matches, 'No notes textarea was rendered.');

        return $matches[0];
    }

    public function test_the_textarea_renders_one_border_colour_when_valid(): void
    {
        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.create'))
            ->assertOk()
            ->getContent();

        $markup = $this->notesTextareaMarkup($html);

        $this->assertStringContainsString('border-border', $markup);
        $this->assertStringNotContainsString('border-status-cancelled', $markup);
    }

    public function test_the_textarea_swaps_to_the_error_border_and_drops_the_default(): void
    {
        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->from(route('customers.create'))
            ->followingRedirects()
            ->post(route('customers.store'), [
                'name' => 'Milan Petrović',
                'phone' => '063 123 456',
                'notes' => str_repeat('a', 2001),
            ])
            ->getContent();

        $markup = $this->notesTextareaMarkup($html);

        $this->assertStringContainsString('border-status-cancelled', $markup);
        $this->assertStringNotContainsString('border-border', $markup);
    }

    public function test_an_errored_field_is_programmatically_associated_with_its_message(): void
    {
        $html = $this->from(route('login'))
            ->followingRedirects()
            ->post(route('login.store'), ['email' => 'not-an-email', 'password' => ''])
            ->getContent();

        preg_match('/<input[^>]*id="email"[^>]*>/', $html, $input);

        $this->assertNotEmpty($input, 'No email input was rendered.');
        $this->assertStringContainsString('aria-invalid="true"', $input[0]);
        $this->assertStringContainsString('aria-describedby="email-error"', $input[0]);
        $this->assertStringContainsString('id="email-error"', $html);
    }

    public function test_a_valid_field_carries_no_invalid_state(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        preg_match('/<input[^>]*id="email"[^>]*>/', $html, $input);

        $this->assertStringNotContainsString('aria-invalid', $input[0]);
        $this->assertStringNotContainsString('aria-describedby', $input[0]);
    }

    public function test_an_errored_textarea_is_programmatically_associated_with_its_message(): void
    {
        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->from(route('customers.create'))
            ->followingRedirects()
            ->post(route('customers.store'), [
                'name' => 'Milan Petrović',
                'phone' => '063 123 456',
                'notes' => str_repeat('a', 2001),
            ])
            ->getContent();

        $markup = $this->notesTextareaMarkup($html);

        $this->assertStringContainsString('aria-invalid="true"', $markup);
        $this->assertStringContainsString('aria-describedby="notes-error"', $markup);
        $this->assertStringContainsString('id="notes-error"', $html);
    }

    public function test_an_absent_value_is_spoken_as_words_not_a_bare_dash(): void
    {
        $html = Blade::render('<x-ui.value :value="null" />');

        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString(__('app.value_none'), $html);
        $this->assertStringContainsString(__('app.value_none_spoken'), $html);
    }

    public function test_a_present_value_renders_plainly(): void
    {
        $html = Blade::render('<x-ui.value value="milan@example.test" />');

        $this->assertStringContainsString('milan@example.test', $html);
        $this->assertStringNotContainsString('sr-only', $html);
        $this->assertStringNotContainsString(__('app.value_none'), $html);
    }

    public function test_the_empty_state_renders_its_body_and_slot(): void
    {
        $html = Blade::render('<x-ui.empty-state title="No customers yet." body="Add the first one.">CTA</x-ui.empty-state>');

        $this->assertStringContainsString('No customers yet.', $html);
        $this->assertStringContainsString('Add the first one.', $html);
        $this->assertStringContainsString('CTA', $html);
    }
}
