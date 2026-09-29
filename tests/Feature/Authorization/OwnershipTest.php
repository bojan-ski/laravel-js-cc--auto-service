<?php

namespace Tests\Feature\Authorization;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobItem;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

/**
 * The rows that are not a simple yes-or-no.
 *
 * Two capabilities depend on who owns the record rather than what role the
 * actor holds, and one depends on where the request came from. Each was
 * resolved during specification rather than by a policy alone, so each is
 * asserted in both directions — a test that only proves the refusal would pass
 * against an implementation that has broken the permission entirely.
 */
class OwnershipTest extends TestCase
{
    use CreatesRealUploads;
    use LazilyRefreshDatabase;

    /**
     * Measured, so a later reader does not over-read this test: the property is
     * enforced TWICE — JobItemRequest::prepareForValidation() merges the actor's
     * id before validation, and JobItemService::assignedUserId() ignores the
     * submitted one for a non-front-office actor. Removing either alone leaves
     * this test green; removing both fails it with "recorded labor against a
     * colleague". It asserts the end-to-end guarantee, not one layer.
     */
    public function test_a_mechanics_labor_line_is_assigned_to_them_whatever_they_submit(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $colleague = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();

        $this->actingAs($mechanic)
            ->post(route('jobs.items.store', $job), [
                'description' => 'Brake service',
                'quantity' => '1.50',
                'user_id' => $colleague->getKey(),
            ])
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame(
            $mechanic->getKey(),
            JobItem::query()->sole()->user_id,
            'A mechanic recorded labor against a colleague.',
        );
    }

    public function test_front_office_may_assign_a_labor_line_to_another_mechanic(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();

        $this->actingAs($receptionist)
            ->post(route('jobs.items.store', $job), [
                'description' => 'Brake service',
                'quantity' => '1.50',
                'user_id' => $mechanic->getKey(),
            ])
            ->assertRedirect(route('jobs.show', $job));

        // The control for the test above: the override is the mechanic rule,
        // not a rule that everyone's line is assigned to themselves.
        $this->assertSame($mechanic->getKey(), JobItem::query()->sole()->user_id);
    }

    /**
     * Rule::prohibitedIf PASSES when the field is present but empty, so an
     * empty string does not fail. A test written with '' would read as though
     * it proved the money boundary and would prove nothing at all.
     */
    public function test_a_mechanic_submitting_a_real_price_fails_validation(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();

        $this->actingAs($mechanic)
            ->from(route('jobs.show', $job))
            ->post(route('jobs.items.store', $job), [
                'description' => 'Brake service',
                'quantity' => '1.50',
                'unit_price' => '4200.00',
            ])
            ->assertSessionHasErrors('unit_price');

        $this->assertSame(0, JobItem::query()->count());
    }

    /**
     * The control for the test above. Without it, a validation regression that
     * rejected unit_price for EVERYONE would leave the mechanic refusal green
     * while the front office had quietly lost the ability to price a line.
     */
    public function test_front_office_may_submit_the_same_price_the_mechanic_is_refused(): void
    {
        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.items.store', $job), [
                'description' => 'Brake service',
                'quantity' => '1.50',
                'user_id' => $mechanic->getKey(),
                'unit_price' => '4200.00',
            ])
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame('4200.00', JobItem::query()->sole()->unit_price);
    }

    public function test_an_empty_price_is_accepted_from_a_mechanic_which_is_why_the_test_above_sends_a_real_one(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();

        $this->actingAs($mechanic)
            ->post(route('jobs.items.store', $job), [
                'description' => 'Brake service',
                'quantity' => '1.50',
                'unit_price' => '',
            ])
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame(1, JobItem::query()->count());
    }

    public function test_a_mechanic_may_edit_their_own_line_and_is_refused_a_colleagues(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $colleague = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();

        $own = JobItem::factory()->forJob($job)->performedBy($mechanic)->create();
        $theirs = JobItem::factory()->forJob($job)->performedBy($colleague)->create();

        $payload = ['description' => 'Revised', 'quantity' => '2.00', 'user_id' => $mechanic->getKey()];

        $this->actingAs($mechanic)->put(route('job-items.update', $own), $payload)->assertRedirect();
        $this->actingAs($mechanic)->put(route('job-items.update', $theirs), $payload)->assertForbidden();

        $this->actingAs($mechanic)->delete(route('job-items.destroy', $theirs))->assertForbidden();
        $this->actingAs($mechanic)->delete(route('job-items.destroy', $own))->assertRedirect();
    }

    public function test_front_office_may_edit_any_line(): void
    {
        $colleague = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $line = JobItem::factory()->forJob($job)->performedBy($colleague)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-items.update', $line), [
                'description' => 'Revised',
                'quantity' => '2.00',
                'user_id' => $colleague->getKey(),
            ])
            ->assertRedirect();
    }

    /**
     * Row 5. Authorization follows the ORIGIN: a walk-in asks JobPolicy::create,
     * an arrival asks AppointmentPolicy::markArrived. A mechanic holds neither,
     * so both doors must be shut, and each is asserted separately.
     */
    public function test_a_mechanic_cannot_open_a_job_from_either_origin(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $appointment = Appointment::factory()->booked()->create();

        $this->actingAs($mechanic)->get(route('jobs.create'))->assertForbidden();
        $this->actingAs($mechanic)
            ->post(route('jobs.store'), [
                'vehicle_id' => $appointment->vehicle_id,
                'complaint' => 'Walk-in',
            ])
            ->assertForbidden();

        $this->actingAs($mechanic)
            ->get(route('jobs.create', ['appointment' => $appointment->getKey()]))
            ->assertForbidden();

        $this->actingAs($mechanic)
            ->post(route('jobs.store'), [
                'appointment_id' => $appointment->getKey(),
                'complaint' => 'Service due',
            ])
            ->assertForbidden();

        $this->assertSame(0, Job::query()->count());
    }

    public function test_a_mechanic_may_delete_their_own_attachment_and_is_refused_a_colleagues(): void
    {
        Storage::fake(config('attachments.disk'));

        $mechanic = User::factory()->mechanic()->create();
        $colleague = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();

        $own = $this->uploadFor($job, $mechanic);
        $theirs = $this->uploadFor($job, $colleague);

        $this->actingAs($mechanic)->delete(route('attachments.destroy', $theirs))->assertForbidden();
        $this->actingAs($mechanic)->delete(route('attachments.destroy', $own))->assertRedirect();

        $this->assertSame([$theirs->getKey()], JobAttachment::query()->pluck('id')->all());
    }

    public function test_front_office_may_delete_any_attachment(): void
    {
        Storage::fake(config('attachments.disk'));

        $job = Job::factory()->inProgress()->create();
        $theirs = $this->uploadFor($job, User::factory()->mechanic()->create());

        $this->actingAs(User::factory()->receptionist()->create())
            ->delete(route('attachments.destroy', $theirs))
            ->assertRedirect();

        $this->assertSame(0, JobAttachment::query()->count());
    }

    public function test_an_admin_cannot_offboard_themselves_or_change_their_own_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertForbidden();

        $this->assertNull($admin->fresh()->deleted_at);

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $admin))
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'mechanic',
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_a_receptionist_may_issue_and_mark_paid_but_is_refused_voiding(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();

        $this->actingAs($receptionist)
            ->post(route('jobs.invoice.store', $job))
            ->assertRedirect();

        $invoice = Invoice::query()->sole();
        $this->assertNotNull($job->fresh()->locked_at, 'Issuing did not lock the job.');

        $this->actingAs($receptionist)
            ->patch(route('invoices.paid', $invoice), ['payment_method' => 'cash'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($invoice->fresh()->isPaid());

        $this->actingAs($receptionist)
            ->patch(route('invoices.void', $invoice))
            ->assertForbidden();

        $this->assertFalse($invoice->fresh()->isVoid());
        $this->assertNotNull($job->fresh()->locked_at, 'A refused void still unlocked the job.');
    }

    private function uploadFor(Job $job, User $uploader): JobAttachment
    {
        return app(AttachmentService::class)->upload(
            $job,
            $this->realImageUpload('ownership.jpg', 200, 150),
            $uploader,
        );
    }
}
