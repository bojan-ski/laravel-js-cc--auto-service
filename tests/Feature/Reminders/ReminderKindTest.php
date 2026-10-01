<?php

namespace Tests\Feature\Reminders;

use App\Enums\Locale;
use App\Enums\ReminderKind;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class ReminderKindTest extends TestCase
{
    public function test_the_cases_are_service_and_registration(): void
    {
        $this->assertSame(['service', 'registration'], array_column(ReminderKind::cases(), 'value'));
    }

    public function test_each_kind_names_its_vehicle_date_column(): void
    {
        $this->assertSame('next_service_on', ReminderKind::Service->dateColumn());
        $this->assertSame('registration_expires_on', ReminderKind::Registration->dateColumn());
    }

    public function test_every_kind_has_a_label_in_every_locale(): void
    {
        foreach (Locale::cases() as $locale) {
            App::setLocale($locale->value);

            foreach (ReminderKind::cases() as $kind) {
                $this->assertNotSame("vehicles.reminder_kinds.{$kind->value}", $kind->label(), "{$locale->value} has no label for {$kind->value}");
            }
        }

        App::setLocale('en');

        $this->assertSame('Service', ReminderKind::Service->label());
        $this->assertSame('Registration', ReminderKind::Registration->label());
    }
}
