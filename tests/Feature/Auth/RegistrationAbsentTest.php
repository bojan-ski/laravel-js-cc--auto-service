<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegistrationAbsentTest extends TestCase
{
    public function test_the_register_page_does_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_registration_cannot_be_posted_to(): void
    {
        $this->post('/register')->assertNotFound();
    }

    public function test_no_route_is_named_register(): void
    {
        $this->assertFalse(app('router')->has('register'));
    }

    public function test_the_login_page_does_not_link_to_registration(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('/register');
    }
}
