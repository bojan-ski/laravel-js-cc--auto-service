<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffFormMarkupTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_create_form_marks_both_password_fields_required(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.create'))
            ->assertOk()
            ->getContent();

        preg_match_all('/<input[^>]*name="password(?:_confirmation)?"[^>]*>/s', $html, $fields);

        $this->assertCount(2, $fields[0]);

        foreach ($fields[0] as $field) {
            $this->assertStringContainsString('required', $field);
            $this->assertStringContainsString('type="password"', $field);
        }
    }

    public function test_the_edit_form_leaves_the_password_optional(): void
    {
        $member = User::factory()->mechanic()->create();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.edit', $member))
            ->assertOk()
            ->getContent();

        preg_match_all('/<input[^>]*name="password(?:_confirmation)?"[^>]*>/s', $html, $fields);

        $this->assertCount(2, $fields[0]);

        foreach ($fields[0] as $field) {
            $this->assertStringNotContainsString('required', $field);
        }
    }

    /**
     * `x-forms.field` emits its own `aria-describedby` pointing at the error
     * paragraph, so the "blank keeps the current password" note may only claim
     * the attribute when there is no error. Passing it unconditionally would put
     * TWO aria-describedby attributes on one input.
     */
    public function test_the_password_note_is_announced_when_there_is_no_error(): void
    {
        $member = User::factory()->mechanic()->create();

        $clean = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.edit', $member))
            ->assertOk()
            ->getContent();

        preg_match('/<input[^>]*name="password"[^>]*>/s', $clean, $field);

        $this->assertSame(1, substr_count($field[0], 'aria-describedby'));
        $this->assertStringContainsString('aria-describedby="password-note"', $field[0]);
        $this->assertStringContainsString('id="password-note"', $clean);
    }

    /**
     * `x-forms.field` emits its own `aria-describedby` pointing at the error
     * paragraph, so the note may only claim the attribute while there is no
     * error. Passing it unconditionally would put TWO aria-describedby
     * attributes on one input — hence the count, not just the value.
     *
     * `assertSessionHasErrors()` is deliberately NOT called on the redirect:
     * measured in this feature, it consumes the flashed `errors` and the next
     * request then renders as though validation had passed. See .ai/rules/tests.md.
     */
    public function test_the_error_takes_the_description_over_from_the_note(): void
    {
        $member = User::factory()->mechanic()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $member))
            ->put(route('admin.users.update', $member), [
                'name' => $member->name,
                'email' => $member->email,
                'role' => 'mechanic',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertRedirect(route('admin.users.edit', $member));

        $html = $this->actingAs($admin)->get(route('admin.users.edit', $member))
            ->assertOk()
            ->getContent();

        preg_match('/<input[^>]*name="password"[^>]*>/s', $html, $field);

        $this->assertSame(1, substr_count($field[0], 'aria-describedby'));
        $this->assertStringContainsString('aria-describedby="password-error"', $field[0]);
        $this->assertStringContainsString('aria-invalid="true"', $field[0]);
    }

    /**
     * The name assertion is a positive control: without it, a response that had
     * simply lost its old input would satisfy the password assertion vacuously.
     */
    public function test_a_password_is_never_echoed_back_into_the_form(): void
    {
        $member = User::factory()->mechanic()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $member))
            ->put(route('admin.users.update', $member), [
                'name' => 'Repopulated Name',
                'email' => $member->email,
                'role' => 'mechanic',
                'password' => 'rejected-but-memorable',
                'password_confirmation' => 'something-else',
            ])->assertRedirect(route('admin.users.edit', $member));

        $this->actingAs($admin)->get(route('admin.users.edit', $member))
            ->assertOk()
            ->assertSee('value="Repopulated Name"', false)
            ->assertDontSee('rejected-but-memorable');
    }

    public function test_rejected_input_is_repopulated_on_the_create_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'name' => 'Jelena Markovic',
                'email' => 'not-an-email',
                'role' => 'receptionist',
                'password' => 'correct-horse-battery',
                'password_confirmation' => 'correct-horse-battery',
            ])->assertRedirect(route('admin.users.create'));

        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('value="Jelena Markovic"', false)
            ->assertSee('value="not-an-email"', false)
            ->assertSee('value="receptionist" selected', false);
    }

    public function test_every_form_in_the_section_carries_a_csrf_token(): void
    {
        $member = User::factory()->mechanic()->create();
        $admin = User::factory()->admin()->create();

        foreach ([
            route('admin.users.create'),
            route('admin.users.edit', $member),
            route('admin.users.offboard', $member),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee('name="_token"', false);
        }
    }

    public function test_the_role_select_offers_every_role_with_its_label(): void
    {
        $member = User::factory()->mechanic()->create();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.edit', $member))
            ->assertOk()
            ->getContent();

        foreach (UserRole::cases() as $role) {
            $this->assertStringContainsString('value="'.$role->value.'"', $html);
            $this->assertStringContainsString($role->label(), $html);
        }

        $this->assertStringContainsString('value="mechanic" selected', $html);
    }
}
