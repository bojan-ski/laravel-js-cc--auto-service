<?php

namespace Tests\Feature\Jobs;

use App\Exceptions\JobLockedException;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobItem;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

class AttachmentLockedJobTest extends TestCase
{
    use CreatesRealUploads;
    use LazilyRefreshDatabase;

    public function test_an_upload_to_a_locked_job_succeeds_while_every_other_write_is_refused(): void
    {
        Storage::fake('local');

        $job = Job::factory()->completed()->create();
        $item = JobItem::factory()->forJob($job)->create();
        $this->lock($job);

        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs($mechanic)
            ->post(route('jobs.attachments.store', $job), [
                'file' => $this->realImageUpload('evidence.jpg'),
            ])
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame(1, JobAttachment::count());

        $job->refresh();
        $job->complaint = 'edited after invoicing';

        try {
            $job->save();
            $this->fail('A locked job accepted a write to its own columns.');
        } catch (JobLockedException) {
            $this->assertTrue(true);
        }

        $item->refresh();
        $item->description = 'edited after invoicing';

        try {
            $item->save();
            $this->fail('A locked job accepted a line item write.');
        } catch (JobLockedException) {
            $this->assertTrue(true);
        }

        $this->assertNotNull($job->fresh()->locked_at);
        $this->assertSame(1, JobAttachment::count());
    }

    public function test_deleting_an_attachment_on_a_locked_job_succeeds(): void
    {
        Storage::fake('local');

        $job = Job::factory()->locked()->create();
        $mechanic = User::factory()->mechanic()->create();

        $attachment = app(AttachmentService::class)->upload(
            $job,
            $this->realImageUpload('evidence.jpg'),
            $mechanic,
        );

        $this->actingAs($mechanic)
            ->delete(route('attachments.destroy', $attachment))
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame(0, JobAttachment::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
        $this->assertNotNull($job->fresh()->locked_at);
    }

    public function test_uploading_never_touches_the_jobs_own_columns(): void
    {
        Storage::fake('local');

        $job = Job::factory()->locked()->create();
        $before = $job->fresh()->getAttributes();

        app(AttachmentService::class)->upload(
            $job,
            $this->realImageUpload('evidence.jpg'),
            User::factory()->mechanic()->create(),
        );

        $this->assertSame($before, $job->fresh()->getAttributes());
    }

    public function test_the_upload_control_is_present_on_a_locked_job(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();
        $this->lock($job);

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('action="'.route('jobs.attachments.store', $job).'"', false)
            ->assertSee('enctype="multipart/form-data"', false);
    }

    private function lock(Job $job): void
    {
        $job->locked_at = now();
        $job->save();
    }
}
