<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FlashMessageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_success_flash_renders_its_message(): void
    {
        $this->withSession(['success' => 'Saved.'])
            ->get('/')
            ->assertOk()
            ->assertSee('Saved.');
    }

    public function test_no_flash_markup_when_session_is_empty(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('data-flash', false);
    }

    public function test_each_flash_key_renders_its_own_region(): void
    {
        $messages = [
            'success' => 'Job completed.',
            'error' => 'Job is locked.',
            'warning' => 'Stock is negative.',
            'info' => 'Invoice emailed.',
        ];

        $response = $this->withSession($messages)->get('/')->assertOk();

        foreach ($messages as $key => $message) {
            $response->assertSee($message);
            $response->assertSee('data-flash="'.$key.'"', false);
        }

        $this->assertSame(4, substr_count($response->getContent(), 'data-flash="'));
    }

    public function test_error_flash_uses_an_assertive_live_region(): void
    {
        $this->withSession(['error' => 'Job is locked.'])
            ->get('/')
            ->assertSee('role="alert"', false)
            ->assertSee('aria-live="assertive"', false);
    }

    public function test_success_flash_uses_a_polite_live_region(): void
    {
        $this->withSession(['success' => 'Saved.'])
            ->get('/')
            ->assertSee('role="status"', false)
            ->assertSee('aria-live="polite"', false);
    }

    public function test_flash_region_carries_a_localized_accessible_label(): void
    {
        $this->withSession(['warning' => 'Stock is negative.'])
            ->get('/')
            ->assertSee(__('app.flash.warning'));
    }

    /**
     * The stack is an overlay: fixed-position, so showing a message never
     * pushes the page down.
     */
    public function test_the_flashes_render_inside_one_fixed_overlay_stack(): void
    {
        $html = $this->withSession(['success' => 'Saved.', 'error' => 'Job is locked.'])
            ->get('/')
            ->getContent();

        $this->assertSame(1, preg_match_all('/<div\b[^>]*data-flash-stack[^>]*>/', $html, $stacks));
        $this->assertMatchesRegularExpression('/class="[^"]*\bfixed\b[^"]*"/', $stacks[0][0]);
    }

    /**
     * Success and info fade on their own. Errors and warnings stay until closed,
     * because a message that vanishes after three seconds is easy to miss.
     */
    public function test_only_success_and_info_carry_an_auto_dismiss_timeout(): void
    {
        $html = $this->withSession([
            'success' => 'Job completed.',
            'error' => 'Job is locked.',
            'warning' => 'Stock is negative.',
            'info' => 'Invoice emailed.',
        ])->get('/')->getContent();

        preg_match_all('/<div\b[^>]*data-flash="(\w+)"[^>]*>/', $html, $toasts, PREG_SET_ORDER);

        $this->assertCount(4, $toasts);

        foreach ($toasts as [$tag, $key]) {
            if (in_array($key, ['success', 'info'], true)) {
                $this->assertStringContainsString('data-flash-timeout="3000"', $tag, "{$key} must auto-dismiss.");
            } else {
                $this->assertStringNotContainsString('data-flash-timeout', $tag, "{$key} must stay until closed.");
            }
        }
    }

    /**
     * The close button does nothing without JavaScript, so it ships hidden and
     * the flash module reveals it.
     */
    public function test_every_flash_carries_a_hidden_labelled_dismiss_button(): void
    {
        $html = $this->withSession(['success' => 'Saved.', 'error' => 'Job is locked.'])
            ->get('/')
            ->getContent();

        preg_match_all('/<button\b[^>]*data-flash-dismiss[^>]*>.*?<\/button>/s', $html, $buttons);

        $this->assertCount(2, $buttons[0]);

        foreach ($buttons[0] as $button) {
            $this->assertMatchesRegularExpression('/<button\b[^>]*\bhidden\b[^>]*>/', $button);
            $this->assertStringContainsString('type="button"', $button);
            $this->assertStringContainsString('<span class="sr-only">'.__('app.flash.dismiss').'</span>', $button);
        }
    }
}
