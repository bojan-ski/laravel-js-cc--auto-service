<?php

namespace Tests\Feature;

use Tests\TestCase;

class FlashMessageTest extends TestCase
{
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
}
