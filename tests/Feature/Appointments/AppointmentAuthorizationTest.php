<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Every role crossed with every ability, against a booked appointment.
     *
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function abilityProvider(): array
    {
        $matrix = [
            'mechanic' => [
                'viewAny' => true, 'view' => true, 'create' => false, 'update' => false,
                'cancel' => false, 'markArrived' => true, 'markNoShow' => true,
            ],
            'receptionist' => [
                'viewAny' => true, 'view' => true, 'create' => true, 'update' => true,
                'cancel' => true, 'markArrived' => true, 'markNoShow' => true,
            ],
            'admin' => [
                'viewAny' => true, 'view' => true, 'create' => true, 'update' => true,
                'cancel' => true, 'markArrived' => true, 'markNoShow' => true,
            ],
        ];

        $cases = [];

        foreach ($matrix as $role => $abilities) {
            foreach ($abilities as $ability => $allowed) {
                $cases["a {$role} ".($allowed ? 'may' : 'may not')." {$ability}"] = [$role, $ability, $allowed];
            }
        }

        return $cases;
    }

    #[DataProvider('abilityProvider')]
    public function test_the_policy_matrix_holds_for_a_booked_appointment(string $role, string $ability, bool $allowed): void
    {
        $user = User::factory()->{$role}()->create();
        $appointment = Appointment::factory()->create();

        $subject = in_array($ability, ['viewAny', 'create'], true)
            ? Appointment::class
            : $appointment;

        $this->assertSame($allowed, $user->can($ability, $subject));
    }

    /**
     * Every instance ability that acts on an appointment, crossed with every
     * terminal status and every role — including admin.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function terminalAbilityProvider(): array
    {
        $cases = [];

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            foreach (['arrived', 'noShow', 'cancelled'] as $state) {
                foreach (['update', 'cancel', 'markArrived', 'markNoShow'] as $ability) {
                    $cases["a {$role} may not {$ability} an {$state} appointment"] = [$role, $state, $ability];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('terminalAbilityProvider')]
    public function test_no_role_may_act_on_a_terminal_appointment(string $role, string $state, string $ability): void
    {
        $user = User::factory()->{$role}()->create();
        $appointment = Appointment::factory()->{$state}()->create();

        $this->assertFalse($user->can($ability, $appointment));
    }

    public function test_a_terminal_appointment_may_still_be_viewed(): void
    {
        $appointment = Appointment::factory()->cancelled()->create();

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->assertTrue($user->can('view', $appointment));
            $this->assertTrue($user->can('viewAny', Appointment::class));
        }
    }

    public function test_appointments_have_no_delete_ability(): void
    {
        $appointment = Appointment::factory()->create();

        $this->assertFalse(User::factory()->admin()->create()->can('delete', $appointment));
    }

    public function test_a_mechanic_may_record_arrival_without_front_office_rights(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $appointment = Appointment::factory()->create();

        $this->assertFalse($mechanic->isFrontOffice());
        $this->assertTrue($mechanic->can('markArrived', $appointment));
        $this->assertTrue($mechanic->can('markNoShow', $appointment));
    }
}
