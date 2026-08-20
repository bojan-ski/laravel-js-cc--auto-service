<?php

namespace Tests\Feature;

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
}
