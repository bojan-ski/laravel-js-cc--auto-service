<?php

namespace Tests\Feature\CheckIns;

use App\Enums\FuelLevel;
use App\Enums\SignatureMethod;
use App\Exceptions\CheckInNotOpen;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobCheckIn;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\CreatesRealUploads;
use Tests\Support\CreatesSignatures;
use Tests\TestCase;

/**
 * Two halves of "a lost race is refused, never a 500" (C1).
 *
 * Controller half: the policy saw the sheet open, the service then lost the
 * race. The race cannot be staged on one connection, so the service is stubbed
 * to lose it, and every writing route must turn that into a redirect to the
 * sheet with the not_open flash.
 *
 * Service half: each writer is handed a STALE job — its loaded checkIn still
 * reads unsigned while the database says signed, the state a request that
 * loaded the sheet before a colleague signed it would carry. The sheet is
 * signed with a query-builder write so the model guards stay out of it, which
 * is what lets these tell the service's own re-check under the lock apart from
 * the backstop behind it.
 */
class CheckInRaceTest extends TestCase
{
    use CreatesRealUploads;
    use CreatesSignatures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('attachments.disk'));
    }

    public function test_a_photo_upload_that_loses_the_race_redirects_with_the_flash(): void
    {
        $job = $this->openSheet();
        $this->stubCheckInService('addPhoto', $job);

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.check-in.photos.store', $job), ['photo' => $this->realImageUpload('late.jpg', 200, 150)])
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHas('error', __('check_ins.flash.not_open'));

        $this->assertSame(0, JobAttachment::query()->count());
    }

    public function test_a_signature_that_loses_the_race_redirects_with_the_flash(): void
    {
        $job = $this->openSheet();
        $this->stubCheckInService('sign', $job);

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('jobs.check-in.sign', $job), ['signature_method' => 'paper', 'signed_by_name' => 'Marko'])
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHas('error', __('check_ins.flash.not_open'));

        $this->assertNull(JobCheckIn::query()->sole()->signed_at);
    }

    public function test_a_photo_delete_that_loses_the_race_redirects_to_the_sheet_and_keeps_the_photo(): void
    {
        $job = $this->openSheet();
        $photo = JobAttachment::factory()->checkIn()->forJob($job)->create();

        $service = Mockery::mock(AttachmentService::class);
        $service->shouldReceive('delete')->andThrow(new CheckInNotOpen($job));
        $this->app->instance(AttachmentService::class, $service);

        $this->actingAs(User::factory()->receptionist()->create())
            ->delete(route('attachments.destroy', $photo))
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHas('error', __('check_ins.flash.not_open'));

        $this->assertTrue(JobAttachment::query()->whereKey($photo->getKey())->exists());
    }

    public function test_an_unreadable_image_redirects_back_with_the_attachment_flash(): void
    {
        $job = $this->openSheet();
        $truncated = substr($this->imageBytes(300, 200, 'png'), 0, 120);

        $this->actingAs(User::factory()->mechanic()->create())
            ->from(route('jobs.check-in.show', $job))
            ->post(route('jobs.check-in.photos.store', $job), ['photo' => $this->realUpload('broken.png', $truncated)])
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHas('error', __('jobs.attachments.flash.unreadable'));

        $this->assertSame(0, JobAttachment::query()->count());
        $this->assertSame([], Storage::disk(config('attachments.disk'))->allFiles());
    }

    public function test_record_re_checks_the_locked_row_not_the_callers_stale_job(): void
    {
        $job = $this->staleJobWhoseSheetWasJustSigned();

        try {
            app(CheckInService::class)->record($job, User::factory()->mechanic()->create(), ['fuel_level' => 'full']);
            $this->fail('A stale job let a signed sheet be edited.');
        } catch (CheckInNotOpen) {
            $this->assertSame(FuelLevel::Empty, JobCheckIn::query()->sole()->fuel_level);
        }
    }

    public function test_add_photo_re_checks_the_locked_row_and_writes_no_file(): void
    {
        $job = $this->staleJobWhoseSheetWasJustSigned();

        try {
            app(CheckInService::class)->addPhoto($job, $this->realImageUpload('late.jpg', 200, 150), User::factory()->mechanic()->create());
            $this->fail('A stale job let a photo be added to a signed sheet.');
        } catch (CheckInNotOpen) {
            $this->assertSame(0, JobAttachment::query()->count());
        }

        $this->assertSame([], Storage::disk(config('attachments.disk'))->allFiles());
    }

    public function test_sign_re_checks_the_locked_row_and_writes_no_file(): void
    {
        $job = $this->staleJobWhoseSheetWasJustSigned();

        try {
            app(CheckInService::class)->sign($job, User::factory()->mechanic()->create(), SignatureMethod::Drawn, 'Second', $this->signatureDataUrl());
            $this->fail('A stale job let a signed sheet be signed again.');
        } catch (CheckInNotOpen) {
            $this->assertSame('First', JobCheckIn::query()->sole()->signed_by_name);
        }

        $this->assertSame([], Storage::disk(config('attachments.disk'))->allFiles());
    }

    /**
     * CheckInNotOpen — the service's own refusal — rather than
     * CheckInSignedException from the model guard behind it.
     */
    public function test_delete_re_checks_the_locked_row_not_the_photos_stale_relation(): void
    {
        $job = $this->openSheet();
        $photo = JobAttachment::factory()->checkIn()->forJob($job)->create();
        $photo->load('job.checkIn');

        $this->signBehindTheModels($job);

        $this->expectException(CheckInNotOpen::class);

        app(AttachmentService::class)->delete($photo);
    }

    public function test_a_refused_delete_keeps_both_the_row_and_every_file(): void
    {
        $job = $this->openSheet();
        $photo = JobAttachment::factory()->checkIn()->forJob($job)->create();
        $disk = Storage::disk(config('attachments.disk'));

        foreach ($photo->storedPaths() as $path) {
            $disk->put($path, 'bytes');
        }

        $this->signBehindTheModels($job);

        try {
            app(AttachmentService::class)->delete($photo);
            $this->fail('A signed sheet\'s photo was deleted.');
        } catch (CheckInNotOpen) {
            $this->assertTrue(JobAttachment::query()->whereKey($photo->getKey())->exists());
        }

        foreach ($photo->storedPaths() as $path) {
            $disk->assertExists($path);
        }
    }

    private function openSheet(): Job
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create(['fuel_level' => FuelLevel::Empty]);

        return $job;
    }

    private function staleJobWhoseSheetWasJustSigned(): Job
    {
        $job = $this->openSheet()->load('checkIn');

        $this->assertTrue($job->acceptsCheckIn(), 'The stale job must still read as open for this test to mean anything.');

        $this->signBehindTheModels($job);

        return $job;
    }

    private function signBehindTheModels(Job $job): void
    {
        DB::table('job_check_ins')->where('job_id', $job->getKey())->update([
            'signed_by_name' => 'First',
            'signature_method' => SignatureMethod::Paper->value,
            'signed_at' => now(),
        ]);
    }

    private function stubCheckInService(string $method, Job $job): void
    {
        $service = Mockery::mock(CheckInService::class);
        $service->shouldReceive($method)->andThrow(new CheckInNotOpen($job));
        $this->app->instance(CheckInService::class, $service);
    }
}
