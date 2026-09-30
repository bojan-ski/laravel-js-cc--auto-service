<?php

namespace Tests\Feature\CheckIns;

use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobCheckIn;
use App\Models\User;
use App\Policies\JobAttachmentPolicy;
use App\Policies\JobPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckInPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function roles(): array
    {
        return ['mechanic' => ['mechanic'], 'receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    #[DataProvider('roles')]
    public function test_every_role_may_record_and_sign_an_open_sheet_and_none_may_touch_a_signed_one(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        $policy = new JobPolicy;

        $open = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($open)->create();

        $signed = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($signed)->signedOnPaper()->create();

        $late = Job::factory()->completed()->create();
        JobCheckIn::factory()->forJob($late)->create();

        $this->assertTrue($policy->recordCheckIn($user, $open->fresh()));
        $this->assertTrue($policy->signCheckIn($user, $open->fresh()));
        $this->assertFalse($policy->recordCheckIn($user, $signed->fresh()));
        $this->assertFalse($policy->signCheckIn($user, $signed->fresh()));
        $this->assertFalse($policy->recordCheckIn($user, $late->fresh()), 'A completed job\'s sheet is not editable.');
        $this->assertTrue($policy->signCheckIn($user, $late->fresh()), 'C2: an unsigned sheet may be signed until invoiced.');
    }

    public function test_front_office_deletes_any_general_attachment_and_a_mechanic_only_their_own(): void
    {
        $policy = new JobAttachmentPolicy;
        $mechanic = User::factory()->mechanic()->create();
        $colleague = User::factory()->mechanic()->create();
        $receptionist = User::factory()->receptionist()->create();

        $own = JobAttachment::factory()->photo()->uploadedBy($mechanic)->create();
        $theirs = JobAttachment::factory()->photo()->uploadedBy($colleague)->create();

        $this->assertTrue($policy->delete($mechanic, $own));
        $this->assertFalse($policy->delete($mechanic, $theirs));
        $this->assertTrue($policy->delete($receptionist, $theirs));
    }

    /**
     * The mutation target: a policy that ignores the category lets anyone who
     * may delete a photo delete one from a signed sheet.
     */
    public function test_a_check_in_photo_is_deletable_only_while_its_sheet_is_open(): void
    {
        $policy = new JobAttachmentPolicy;
        $mechanic = User::factory()->mechanic()->create();
        $admin = User::factory()->admin()->create();

        $open = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($open)->create();
        $openPhoto = JobAttachment::factory()->checkIn()->forJob($open)->uploadedBy($mechanic)->create();

        $signed = Job::factory()->inProgress()->create();
        $signedPhoto = JobAttachment::factory()->checkIn()->forJob($signed)->uploadedBy($mechanic)->create();
        JobCheckIn::factory()->forJob($signed)->signedOnPaper()->create();

        $locked = Job::factory()->locked()->create();
        $lockedPhoto = JobAttachment::factory()->checkIn()->forJob($locked)->uploadedBy($mechanic)->create();
        JobCheckIn::factory()->forJob($locked)->create();

        $this->assertTrue($policy->delete($mechanic, $openPhoto));
        $this->assertTrue($policy->delete($admin, $openPhoto));

        foreach ([$signedPhoto, $lockedPhoto] as $photo) {
            $this->assertFalse($policy->delete($mechanic, $photo));
            $this->assertFalse($policy->delete($admin, $photo));
        }
    }

    public function test_a_general_photo_on_a_locked_job_with_a_signed_sheet_stays_deletable(): void
    {
        $policy = new JobAttachmentPolicy;
        $receptionist = User::factory()->receptionist()->create();

        $job = Job::factory()->locked()->create();
        JobCheckIn::factory()->forJob($job)->signedOnPaper()->create();
        $photo = JobAttachment::factory()->photo()->forJob($job)->create();

        $this->assertTrue($policy->delete($receptionist, $photo));
    }
}
