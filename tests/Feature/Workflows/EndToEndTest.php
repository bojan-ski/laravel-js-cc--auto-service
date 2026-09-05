<?php

namespace Tests\Feature\Workflows;

use App\Mail\InvoiceIssuedMail;
use App\Mail\JobCompletedMail;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InvoiceService;
use App\Services\JobItemService;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ResolvesJobService;
use Tests\TestCase;

/**
 * Sequence F of spec 24, the one end-to-end walk the suite did not already
 * hold. The snapshot discipline of specs 05, 06, 08 and 09 exists so that
 * selling a car between booking and arrival does not rewrite who is billed.
 * JobHistorySnapshotTest proves it as far as the job; this carries it through
 * completion and invoicing, to both customer emails and the invoice page.
 */
class EndToEndTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ResolvesJobService;

    public function test_a_reassigned_vehicles_invoice_bills_the_customer_who_booked_it(): void
    {
        $seller = Customer::factory()->create(['name' => 'Milan Petrović', 'email' => 'milan@example.test']);
        $buyer = Customer::factory()->create(['name' => 'Jelena Ilić', 'email' => 'jelena@example.test']);
        $vehicle = Vehicle::factory()->for($seller)->create();
        $appointment = Appointment::factory()->booked()->for($vehicle)->create();
        $receptionist = User::factory()->receptionist()->create();
        $mechanic = User::factory()->mechanic()->create();

        $this->assertSame($seller->getKey(), $appointment->customer_id, 'The booking must be in the seller\'s name for the sequence to mean anything.');

        app(VehicleService::class)->reassign($vehicle, $buyer);
        $this->assertSame($buyer->getKey(), $vehicle->fresh()->customer_id);

        Mail::fake();

        $job = $this->service()->createFromAppointment($appointment, $receptionist, ['complaint' => 'Pulls right under braking']);
        $this->assertSame($seller->getKey(), $job->customer_id);

        $this->service()->start($job->fresh());
        app(JobItemService::class)->addLabor($job->fresh(), $receptionist, [
            'description' => 'Brake service',
            'quantity' => '1.50',
            'user_id' => $mechanic->getKey(),
        ]);
        $this->service()->complete($job->fresh());

        Mail::assertQueued(
            JobCompletedMail::class,
            fn (JobCompletedMail $mail): bool => $mail->hasTo('milan@example.test') && ! $mail->hasTo('jelena@example.test'),
        );

        $invoice = app(InvoiceService::class)->issue($job->fresh(), $receptionist);

        Mail::assertQueued(
            InvoiceIssuedMail::class,
            fn (InvoiceIssuedMail $mail): bool => $mail->hasTo('milan@example.test') && ! $mail->hasTo('jelena@example.test'),
        );
        Mail::assertQueuedCount(2);

        $this->assertSame($seller->getKey(), $job->fresh()->customer_id);
        $this->assertNotNull($job->fresh()->locked_at);
        $this->assertSame($buyer->getKey(), $vehicle->fresh()->customer_id, 'Invoicing must not rewrite the vehicle\'s current owner either.');

        $this->actingAs($receptionist)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Milan Petrović')
            ->assertDontSee('Jelena Ilić');
    }
}
