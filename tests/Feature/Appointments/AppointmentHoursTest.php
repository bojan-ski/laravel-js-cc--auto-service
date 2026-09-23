<?php

namespace Tests\Feature\Appointments;

use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentHoursTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_an_admin_saves_the_hours_and_returns_to_the_appointments_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.appointment-hours.update'), ['open_at' => '07:30', 'close_at' => '18:00'])
            ->assertRedirect(route('appointments.index'))
            ->assertSessionHas('success', __('appointments.flash.hours_updated', ['open' => '07:30', 'close' => '18:00']));

        $settings = ShopSetting::query()->sole();

        $this->assertSame('07:30', $settings->opensAt());
        $this->assertSame('18:00', $settings->closesAt());
    }

    public function test_saving_twice_keeps_a_single_settings_row(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.appointment-hours.update'), ['open_at' => '07:30', 'close_at' => '18:00']);
        $this->actingAs($admin)->put(route('admin.appointment-hours.update'), ['open_at' => '09:00', 'close_at' => '17:00']);

        $this->assertSame('09:00', ShopSetting::query()->sole()->opensAt());
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function invalidHoursProvider(): array
    {
        return [
            'closing before opening' => [['open_at' => '18:00', 'close_at' => '08:00'], 'close_at'],
            'closing equal to opening' => [['open_at' => '08:00', 'close_at' => '08:00'], 'close_at'],
            'opening missing' => [['open_at' => '', 'close_at' => '18:00'], 'open_at'],
            'opening not a time' => [['open_at' => '8 am', 'close_at' => '18:00'], 'open_at'],
        ];
    }

    /**
     * @param  array<string, string>  $payload
     */
    #[DataProvider('invalidHoursProvider')]
    public function test_invalid_hours_are_refused(array $payload, string $field): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.appointment-hours.update'), $payload)
            ->assertSessionHasErrors($field);

        $this->assertSame(0, ShopSetting::query()->count());
    }

    public function test_closing_before_opening_explains_itself(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.appointment-hours.update'), ['open_at' => '18:00', 'close_at' => '08:00'])
            ->assertSessionHasErrors(['close_at' => __('appointments.hours.close_after_open')]);
    }

    public function test_an_admin_gets_the_set_hours_button_and_its_dialog(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('appointments.index'))
            ->assertOk()
            ->getContent();

        preg_match('/<a\b[^>]*data-dialog-open="appointment-hours"[^>]*>/', $html, $trigger);

        $this->assertNotEmpty($trigger, 'The Set hours button must be present.');
        $this->assertStringContainsString('href="'.route('admin.appointment-hours.edit').'"', $trigger[0]);
        $this->assertMatchesRegularExpression('/<dialog\b[^>]*id="appointment-hours"[^>]*>/', $html);
        $this->assertStringContainsString('action="'.route('admin.appointment-hours.update').'"', $html);
        $this->assertStringNotContainsString('data-dialog-open-on-load', $html);
    }

    public function test_the_button_sits_before_new_appointment(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('appointments.index'))
            ->getContent();

        $this->assertLessThan(
            strpos($html, 'href="'.route('appointments.create').'"'),
            strpos($html, 'data-dialog-open="appointment-hours"'),
        );
    }

    public function test_a_receptionist_gets_neither_the_button_nor_the_dialog(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee(__('appointments.index.new_appointment'))
            ->assertDontSee('data-dialog-open', false)
            ->assertDontSee('<dialog', false)
            ->assertDontSee(route('admin.appointment-hours.update'), false);
    }

    public function test_a_refused_save_from_the_dialog_reopens_it_with_the_error(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('appointments.index'))
            ->put(route('admin.appointment-hours.update'), ['open_at' => '18:00', 'close_at' => '08:00'])
            ->assertRedirect(route('appointments.index'));

        $html = $this->actingAs($admin)->get(route('appointments.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<dialog\b[^>]*data-dialog-open-on-load[^>]*>/', $html);
        $this->assertStringContainsString(__('appointments.hours.close_after_open'), $html);
    }

    public function test_the_page_prefills_the_current_hours(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.appointment-hours.edit'))
            ->assertOk()
            ->assertSee('value="08:00"', false)
            ->assertSee('value="20:00"', false);
    }
}
