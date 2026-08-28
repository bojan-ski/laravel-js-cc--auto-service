<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_the_empty_state_title_is_a_level_two_heading_by_default(): void
    {
        $html = Blade::render('<x-ui.empty-state title="No customers yet." />');

        $this->assertStringContainsString('<h2 class="text-sm font-semibold">No customers yet.</h2>', $html);
    }

    public function test_the_empty_state_title_level_can_be_changed_or_demoted_to_a_paragraph(): void
    {
        $this->assertStringContainsString(
            '<h3 class="text-sm font-semibold">Nothing here.</h3>',
            Blade::render('<x-ui.empty-state title="Nothing here." :level="3" />'),
        );

        $html = Blade::render('<x-ui.empty-state title="Nothing here." :level="0" />');

        $this->assertStringContainsString('<p class="text-sm font-semibold">Nothing here.</p>', $html);
        $this->assertStringNotContainsString('<h', $html);
    }

    public function test_in_section_empty_states_do_not_add_a_heading_under_their_section_heading(): void
    {
        $customer = Customer::factory()->create();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<p class="text-sm font-semibold">'.__('vehicles.section.empty').'</p>', $html);
        $this->assertStringNotContainsString('<h3', $html);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function moneyProvider(): array
    {
        return [
            'a thousands separator' => ['3000.00', '3,000.00'],
            'a computed subtotal' => ['6000.00', '6,000.00'],
            'a whole number gains its decimals' => ['3000', '3,000.00'],
            'millions keep every group' => ['1234567.89', '1,234,567.89'],
            'zero' => ['0.00', '0.00'],
            'a fraction rounds to two places' => ['0.125', '0.13'],
        ];
    }

    #[DataProvider('moneyProvider')]
    public function test_money_renders_with_two_decimals_and_no_currency_symbol(string $value, string $expected): void
    {
        $html = Blade::render('<x-ui.money :value="$value" />', ['value' => $value]);

        $this->assertStringContainsString('>'.$expected.'<', $html);
        $this->assertStringContainsString('tabular-nums', $html);
        $this->assertDoesNotMatchRegularExpression('/[€$£]|RSD|din/i', $html);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function quantityProvider(): array
    {
        return [
            'a half hour keeps one decimal' => ['1.50', '1.5'],
            'a whole hour drops both' => ['1.00', '1'],
            'a quarter keeps both' => ['2.25', '2.25'],
            'zero' => ['0.00', '0'],
        ];
    }

    #[DataProvider('quantityProvider')]
    public function test_quantity_shows_decimals_only_when_they_are_meaningful(string $value, string $expected): void
    {
        $html = Blade::render('<x-ui.quantity :value="$value" />', ['value' => $value]);

        $this->assertStringContainsString('>'.$expected.'<', $html);
        $this->assertStringContainsString('tabular-nums', $html);
    }

    /**
     * Ten of the twelve call sites omit `method` and inherit this default. If it
     * ever changed, every one of them would 405 in production against its PATCH
     * route while the rest of the suite stayed green.
     */
    public function test_the_action_form_spoofs_patch_by_default(): void
    {
        $html = Blade::render('<x-ui.action-form action="/jobs/1/start">Start</x-ui.action-form>');

        $this->assertStringContainsString('<form method="POST" action="/jobs/1/start">', $html);
        $this->assertStringContainsString('name="_method" value="PATCH"', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }

    public function test_the_action_form_honours_an_explicit_method(): void
    {
        $html = Blade::render('<x-ui.action-form action="/customers/1" method="DELETE">Delete</x-ui.action-form>');

        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringNotContainsString('value="PATCH"', $html);
    }

    public function test_the_action_form_carries_a_confirm_hook_only_when_asked(): void
    {
        $bare = Blade::render('<x-ui.action-form action="/jobs/1/start">Start</x-ui.action-form>');
        $confirmed = Blade::render('<x-ui.action-form action="/jobs/1/cancel" confirm="Sure?">Cancel</x-ui.action-form>');

        $this->assertStringNotContainsString('data-confirm', $bare);
        $this->assertStringContainsString('data-confirm="Sure?"', $confirmed);
    }

    public function test_the_action_form_forwards_the_accessible_name_and_variant_to_its_button(): void
    {
        $html = Blade::render('<x-ui.action-form action="/customers/1" variant="danger" aria-label="Delete Ana Marić">Delete</x-ui.action-form>');

        $this->assertSame(['bg-danger'], $this->backgroundUtilities(substr($html, strpos($html, '<button'))));
        $this->assertStringContainsString('aria-label="Delete Ana Marić"', $html);
        $this->assertSame(1, substr_count($html, 'aria-label='));
    }
}
