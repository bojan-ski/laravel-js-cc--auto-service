<?php

namespace Tests\Feature\Mail;

use App\Enums\ReminderKind;
use App\Mail\ServiceReminderMail;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use App\Services\CustomerMailer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use ReflectionMethod;
use Tests\Support\AssertsMailRows;
use Tests\TestCase;

class ServiceReminderMailContentTest extends TestCase
{
    use AssertsMailRows;
    use LazilyRefreshDatabase;

    private function mailable(ReminderKind $kind, ?int $mileage = 180000): ServiceReminderMail
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrovic', 'email' => 'milan@example.test']);
        $vehicle = Vehicle::factory()->for($customer)->create([
            'registration_plate' => 'BG123-ZZ',
            'make' => 'VW',
            'model' => 'Golf IV',
            'next_service_on' => '2026-10-12',
            'next_service_mileage' => $mileage,
            'registration_expires_on' => '2026-10-20',
        ]);
        $reminder = VehicleReminder::factory()->forVehicle($vehicle, $kind)->create();

        return new ServiceReminderMail($reminder, $customer, $vehicle);
    }

    public function test_a_service_reminder_names_the_service_and_its_date(): void
    {
        $mailable = $this->mailable(ReminderKind::Service);

        $mailable->assertHasSubject('Service reminder for BG123-ZZ');

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $assertion) {
            $mailable->{$assertion}('Dear Milan Petrovic,', false);
            $mailable->{$assertion}('VW Golf IV (BG123-ZZ) is due for its next service on October 12, 2026.', false);
            $mailable->{$assertion}(__('mail.service_reminder.book'), false);
            $mailable->{$assertion}(__('mail.service_reminder.opt_out'), false);
        }

        $mailable->assertSeeInHtml('>Service reminder</h1>', false);
        $mailable->assertSeeInOrderInHtml([__('mail.vehicle'), 'BG123-ZZ', 'VW Golf IV'], false);
        $this->assertHtmlRow($mailable, 'Due on', 'October 12, 2026');
        $this->assertHtmlRow($mailable, 'Or at', '180,000 km');
        $mailable->assertSeeInText('Due on: October 12, 2026', false);
        $mailable->assertSeeInText('Or at: 180,000 km', false);
        $this->assertNoHtmlRowLabelled($mailable, 'Expires on');
    }

    public function test_the_or_at_row_appears_only_when_a_mileage_is_set(): void
    {
        $mailable = $this->mailable(ReminderKind::Service, mileage: null);

        $this->assertHtmlRow($mailable, 'Due on', 'October 12, 2026');
        $this->assertNoHtmlRowLabelled($mailable, 'Or at');
        $mailable->assertDontSeeInText('Or at', false);
    }

    public function test_a_registration_reminder_names_the_expiry(): void
    {
        $mailable = $this->mailable(ReminderKind::Registration);

        $mailable->assertHasSubject('Registration reminder for BG123-ZZ');

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $assertion) {
            $mailable->{$assertion}('The registration of VW Golf IV (BG123-ZZ) expires on October 20, 2026.', false);
        }

        $mailable->assertSeeInHtml('>Registration reminder</h1>', false);
        $this->assertHtmlRow($mailable, 'Expires on', 'October 20, 2026');
        $this->assertNoHtmlRowLabelled($mailable, 'Due on');
        $this->assertNoHtmlRowLabelled($mailable, 'Or at');
    }

    public function test_the_date_comes_from_the_reminder_not_the_vehicles_current_value(): void
    {
        $mailable = $this->mailable(ReminderKind::Service);
        $mailable->vehicle->next_service_on = CarbonImmutable::parse('2027-01-05');

        $this->assertHtmlRow($mailable, 'Due on', 'October 12, 2026');
        $mailable->assertDontSeeInHtml('January 5, 2027', false);
    }

    public function test_the_message_carries_no_link_into_the_application(): void
    {
        $mailable = $this->mailable(ReminderKind::Service);

        foreach (['assertDontSeeInHtml', 'assertDontSeeInText'] as $assertion) {
            $mailable->{$assertion}(config('app.url'), false);
            $mailable->{$assertion}('/vehicles/', false);
        }

        $mailable->assertDontSeeInHtml('<a ', false);
    }

    public function test_the_message_carries_no_money(): void
    {
        $mailable = $this->mailable(ReminderKind::Service);

        foreach (['assertDontSeeInHtml', 'assertDontSeeInText'] as $assertion) {
            $mailable->{$assertion}(__('mail.totals.total'), false);
            $mailable->{$assertion}(__('mail.totals.subtotal'), false);
        }
    }

    public function test_it_is_written_in_the_shop_language_whatever_the_senders_locale(): void
    {
        Mail::fake();
        config(['shop.locale' => 'sr']);
        App::setLocale('de');

        $mailable = $this->mailable(ReminderKind::Service);
        app(CustomerMailer::class)->send($mailable->customer, $mailable);

        Mail::assertQueued(ServiceReminderMail::class, fn (ServiceReminderMail $mail): bool => $mail->locale === 'sr');

        $rendered = $mailable->locale('sr')->render();

        $this->assertStringContainsString('Podsetnik za servis', $rendered);
        $this->assertStringContainsString('12. oktobar 2026.', $rendered);
    }

    /**
     * Every key, not one: sentinels prove each line goes through the
     * translator rather than matching copy the view could hard-code.
     */
    public function test_every_sentence_resolves_through_the_translator(): void
    {
        Lang::addLines([
            'mail.service_reminder.subject_service' => 'ZZ-SUBJECT :plate',
            'mail.service_reminder.heading_service' => 'ZZ-HEADING',
            'mail.service_reminder.greeting' => 'ZZ-GREETING :name',
            'mail.service_reminder.body_service' => 'ZZ-BODY :make :model :plate :date',
            'mail.service_reminder.due_on' => 'ZZ-DUE',
            'mail.service_reminder.or_at' => 'ZZ-OR-AT',
            'mail.service_reminder.book' => 'ZZ-BOOK',
            'mail.service_reminder.opt_out' => 'ZZ-OPT-OUT',
        ], 'en');

        $mailable = $this->mailable(ReminderKind::Service);

        $mailable->assertHasSubject('ZZ-SUBJECT BG123-ZZ');
        $mailable->assertSeeInHtml('ZZ-HEADING', false);

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $assertion) {
            $mailable->{$assertion}('ZZ-GREETING Milan Petrovic', false);
            $mailable->{$assertion}('ZZ-BODY VW Golf IV BG123-ZZ October 12, 2026', false);
            $mailable->{$assertion}('ZZ-BOOK', false);
            $mailable->{$assertion}('ZZ-OPT-OUT', false);
        }

        $this->assertHtmlRow($mailable, 'ZZ-DUE', 'October 12, 2026');
        $this->assertHtmlRow($mailable, 'ZZ-OR-AT', '180,000 km');
    }

    public function test_it_records_the_plate_kind_and_date(): void
    {
        $method = new ReflectionMethod(ServiceReminderMail::class, 'record');

        $this->assertSame('BG123-ZZ registration 2026-10-20', $method->invoke($this->mailable(ReminderKind::Registration)));
    }
}
