<?php

namespace Tests\Feature\CheckIns;

use App\Enums\AttachmentCategory;
use App\Exceptions\CheckInNotOpen;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobCheckIn;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\CheckInService;
use App\Services\JobService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

class CheckInPhotoTest extends TestCase
{
    use CreatesRealUploads;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('attachments.disk'));
    }

    public function test_an_upload_is_stored_as_a_check_in_photo_with_both_variants(): void
    {
        $job = $this->openSheet();
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs($mechanic)
            ->post(route('jobs.check-in.photos.store', $job), ['photo' => $this->realImageUpload('bumper.png', 900, 600, 'png')])
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHas('success', __('check_ins.flash.photo_added'));

        $photo = JobAttachment::query()->sole();
        $disk = Storage::disk(config('attachments.disk'));

        $this->assertSame(AttachmentCategory::CheckIn, $photo->category);
        $this->assertSame($mechanic->getKey(), $photo->uploaded_by);
        $disk->assertExists($photo->path);
        $disk->assertExists($photo->variant_full_path);
        $disk->assertExists($photo->variant_thumb_path);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function refusedFiles(): array
    {
        return [
            'a PDF' => ['inspection.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF"],
            'a PDF named .jpg' => ['bumper.jpg', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF"],
        ];
    }

    #[DataProvider('refusedFiles')]
    public function test_anything_but_an_image_is_refused_on_its_detected_type(string $name, string $bytes): void
    {
        $job = $this->openSheet();

        $this->actingAs(User::factory()->mechanic()->create())
            ->from(route('jobs.check-in.show', $job))
            ->post(route('jobs.check-in.photos.store', $job), ['photo' => $this->realUpload($name, $bytes)])
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHasErrors('photo');

        $this->assertSame(0, JobAttachment::query()->count());
    }

    public function test_an_upload_without_a_sheet_is_not_found(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.check-in.photos.store', $job), ['photo' => $this->realImageUpload('bumper.jpg', 200, 150)])
            ->assertNotFound();

        $this->assertSame(0, JobAttachment::query()->count());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function roles(): array
    {
        return ['mechanic' => ['mechanic'], 'receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    #[DataProvider('roles')]
    public function test_an_upload_to_a_signed_sheet_is_forbidden_for_every_role(string $role): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->signedOnPaper()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->post(route('jobs.check-in.photos.store', $job), ['photo' => $this->realImageUpload('late.jpg', 200, 150)])
            ->assertForbidden();

        $this->assertSame(0, JobAttachment::query()->count());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function closedJobs(): array
    {
        $rows = [];

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            foreach (['completed', 'cancelled', 'locked'] as $state) {
                $rows["{$role}, {$state}"] = [$role, $state];
            }
        }

        return $rows;
    }

    /**
     * On a completed, unlocked job signCheckIn is TRUE while recordCheckIn is
     * false (C2), so this is what tells the photo route's gate apart from the
     * signing one — the signed-sheet case above cannot.
     */
    #[DataProvider('closedJobs')]
    public function test_an_upload_to_a_closed_job_is_forbidden_for_every_role(string $role, string $state): void
    {
        $job = Job::factory()->{$state}()->create();
        JobCheckIn::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->post(route('jobs.check-in.photos.store', $job), ['photo' => $this->realImageUpload('late.jpg', 200, 150)])
            ->assertForbidden();

        $this->assertSame(0, JobAttachment::query()->count());
        $this->assertSame([], Storage::disk(config('attachments.disk'))->allFiles());
    }

    public function test_a_missing_photo_is_refused(): void
    {
        $job = $this->openSheet();

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.check-in.photos.store', $job), [])
            ->assertSessionHasErrors('photo');
    }

    public function test_a_photo_over_10_mb_is_refused(): void
    {
        $job = $this->openSheet();
        $oversized = $this->imageBytes(200, 150).str_repeat("\0", 10 * 1024 * 1024);

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.check-in.photos.store', $job), ['photo' => $this->realUpload('huge.jpg', $oversized)])
            ->assertSessionHasErrors('photo');

        $this->assertSame(0, JobAttachment::query()->count());
    }

    public function test_a_webp_photo_is_accepted(): void
    {
        $job = $this->openSheet();

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.check-in.photos.store', $job), ['photo' => $this->realImageUpload('bumper.webp', 300, 200, 'webp')])
            ->assertSessionHasNoErrors();

        $this->assertSame(AttachmentCategory::CheckIn, JobAttachment::query()->sole()->category);
    }

    public function test_a_refused_upload_renders_its_error_tied_to_the_file_input(): void
    {
        $job = $this->openSheet();
        $user = User::factory()->mechanic()->create();

        $this->actingAs($user)
            ->from(route('jobs.check-in.show', $job))
            ->post(route('jobs.check-in.photos.store', $job), ['photo' => $this->realUpload('notes.pdf', "%PDF-1.4\n%%EOF")])
            ->assertRedirect(route('jobs.check-in.show', $job));

        $html = (string) $this->actingAs($user)->get(route('jobs.check-in.show', $job))->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*id="check-in-photo"[^>]*aria-describedby="check-in-photo-error check-in-photo-hint"[^>]*aria-invalid="true"/s', $html);
        $this->assertStringContainsString('id="check-in-photo-error"', $html);
    }

    public function test_an_open_sheet_offers_the_viewer_and_delete_only_where_the_policy_allows(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = $this->openSheet();
        $own = JobAttachment::factory()->checkIn()->forJob($job)->uploadedBy($mechanic)->create();
        $colleagues = JobAttachment::factory()->checkIn()->forJob($job)->create();
        $deleteAction = fn (JobAttachment $photo): string => 'data-lightbox-delete-action="'.route('attachments.destroy', $photo).'"';

        $this->actingAs($mechanic)
            ->get(route('jobs.check-in.show', $job))
            ->assertSee('data-lightbox-dialog', false)
            ->assertSee($deleteAction($own), false)
            ->assertDontSee($deleteAction($colleagues), false);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.check-in.show', $job))
            ->assertSee($deleteAction($own), false)
            ->assertSee($deleteAction($colleagues), false);
    }

    public function test_cancelling_the_job_leaves_its_check_in_photos(): void
    {
        $job = $this->openSheet();
        $photo = $this->storedCheckInPhoto($job, User::factory()->mechanic()->create());

        app(JobService::class)->cancel($job, User::factory()->receptionist()->create());

        $this->assertTrue(JobAttachment::query()->whereKey($photo->getKey())->exists());
        Storage::disk(config('attachments.disk'))->assertExists($photo->path);
    }

    public function test_the_job_page_lists_only_general_attachments_and_the_sheet_only_check_in_photos(): void
    {
        $job = $this->openSheet();
        $general = JobAttachment::factory()->photo()->forJob($job)->create();
        $checkIn = JobAttachment::factory()->checkIn()->forJob($job)->create();
        $user = User::factory()->receptionist()->create();

        $this->actingAs($user)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertViewHas('photos', fn ($photos): bool => $photos->pluck('id')->all() === [$general->getKey()])
            ->assertSee(route('attachments.show', ['attachment' => $general, 'variant' => 'thumb']), false)
            ->assertDontSee(route('attachments.show', ['attachment' => $checkIn, 'variant' => 'thumb']), false);

        $this->actingAs($user)
            ->get(route('jobs.check-in.show', $job))
            ->assertOk()
            ->assertSee(route('attachments.show', ['attachment' => $checkIn, 'variant' => 'thumb']), false)
            ->assertDontSee(route('attachments.show', ['attachment' => $general, 'variant' => 'thumb']), false);
    }

    public function test_a_mechanic_deletes_their_own_check_in_photo_and_returns_to_the_sheet(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = $this->openSheet();
        $photo = $this->storedCheckInPhoto($job, $mechanic);

        $this->actingAs($mechanic)
            ->delete(route('attachments.destroy', $photo))
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHas('success', __('check_ins.flash.photo_deleted'));

        $this->assertFalse(JobAttachment::query()->whereKey($photo->getKey())->exists());
        Storage::disk(config('attachments.disk'))->assertMissing($photo->path);
    }

    public function test_a_mechanic_may_not_delete_a_colleagues_check_in_photo(): void
    {
        $job = $this->openSheet();
        $photo = $this->storedCheckInPhoto($job, User::factory()->mechanic()->create());

        $this->actingAs(User::factory()->mechanic()->create())
            ->delete(route('attachments.destroy', $photo))
            ->assertForbidden();

        $this->assertTrue(JobAttachment::query()->whereKey($photo->getKey())->exists());
    }

    public function test_front_office_deletes_any_check_in_photo_while_the_sheet_is_open(): void
    {
        $job = $this->openSheet();
        $photo = $this->storedCheckInPhoto($job, User::factory()->mechanic()->create());

        $this->actingAs(User::factory()->receptionist()->create())
            ->delete(route('attachments.destroy', $photo))
            ->assertRedirect(route('jobs.check-in.show', $job));

        $this->assertFalse(JobAttachment::query()->whereKey($photo->getKey())->exists());
    }

    #[DataProvider('roles')]
    public function test_nobody_deletes_a_photo_from_a_signed_sheet(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        $job = Job::factory()->inProgress()->create();
        $photo = $this->storedCheckInPhoto($job, $user);
        JobCheckIn::factory()->forJob($job)->signedOnPaper()->create();

        $this->actingAs($user)
            ->delete(route('attachments.destroy', $photo))
            ->assertForbidden();

        $this->assertTrue(JobAttachment::query()->whereKey($photo->getKey())->exists());
        Storage::disk(config('attachments.disk'))->assertExists($photo->path);
    }

    /**
     * The service re-checks under the job-row lock, independently of the
     * policy. The mutation target: deleting a signed sheet's photo straight
     * through the service must still be refused — by the service's own
     * CheckInNotOpen before the model guard ever runs.
     */
    public function test_the_service_refuses_a_signed_sheets_photo_before_the_model_guard(): void
    {
        $job = Job::factory()->inProgress()->create();
        $photo = $this->storedCheckInPhoto($job, User::factory()->mechanic()->create());
        JobCheckIn::factory()->forJob($job)->signedOnPaper()->create();

        $this->expectException(CheckInNotOpen::class);

        app(AttachmentService::class)->delete($photo);
    }

    public function test_the_service_refuses_a_photo_for_a_job_with_no_sheet_and_writes_no_file(): void
    {
        $job = Job::factory()->inProgress()->create();

        try {
            app(CheckInService::class)->addPhoto($job, $this->realImageUpload('early.jpg', 200, 150), User::factory()->mechanic()->create());
            $this->fail('A check-in photo was stored before the sheet existed.');
        } catch (CheckInNotOpen) {
            $this->assertSame(0, JobAttachment::query()->count());
        }

        $this->assertSame([], Storage::disk(config('attachments.disk'))->allFiles());
    }

    /**
     * The spec's acceptance line. Read it for what it is: it CANNOT fail if
     * the controller's loadMissing('job.checkIn') is deleted — measured by
     * mutation. The attachment is route-bound, a single-row hydration, and the
     * framework arms lazy-loading prevention only on multi-row results
     * (.ai/rules/tests.md), so the spec's premise that the missing load makes
     * every delete a 500 is false. What this does pin is that the path raises
     * no violation today, so a future multi-row read on it would be caught.
     */
    public function test_a_check_in_photo_delete_raises_no_lazy_loading_violation(): void
    {
        $violations = [];
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) use (&$violations): void {
            $violations[] = $model::class.'::'.$relation;
        });

        try {
            $job = $this->openSheet();
            $photo = $this->storedCheckInPhoto($job, User::factory()->mechanic()->create());

            $this->actingAs(User::factory()->receptionist()->create())
                ->delete(route('attachments.destroy', $photo))
                ->assertRedirect(route('jobs.check-in.show', $job));
        } finally {
            Model::handleLazyLoadingViolationUsing(null);
        }

        $this->assertSame([], $violations);
    }

    public function test_a_general_photo_still_deletes_and_returns_to_the_job_page(): void
    {
        $job = Job::factory()->locked()->create();
        $photo = JobAttachment::factory()->photo()->forJob($job)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->delete(route('attachments.destroy', $photo))
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.attachments.flash.deleted'));
    }

    private function openSheet(): Job
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create();

        return $job;
    }

    private function storedCheckInPhoto(Job $job, User $uploader): JobAttachment
    {
        $photo = JobAttachment::factory()->checkIn()->forJob($job)->uploadedBy($uploader)->create();

        foreach ($photo->storedPaths() as $path) {
            Storage::disk(config('attachments.disk'))->put($path, 'bytes');
        }

        return $photo;
    }
}
