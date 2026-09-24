<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JobAttachmentsViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_empty_section_still_offers_the_upload_control(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee(__('jobs.show.attachments_empty'))
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee(__('jobs.attachments.upload.action'));
    }

    public function test_a_photo_renders_as_a_thumbnail_using_the_400px_variant(): void
    {
        $job = Job::factory()->inProgress()->create();
        $photo = JobAttachment::factory()->photo()->forJob($job)->create();

        $response = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();

        $response->assertSee(route('attachments.show', ['attachment' => $photo, 'variant' => 'thumb']), false);
        $response->assertSee(route('attachments.show', ['attachment' => $photo, 'variant' => 'full']), false);
        $response->assertSee('aspect-4/3', false);
        $response->assertSee('object-cover', false);
        $response->assertDontSee(__('jobs.show.attachments_empty'));
    }

    public function test_a_thumbnail_carries_a_visible_caption_and_no_title_attribute(): void
    {
        $job = Job::factory()->inProgress()->create();
        $uploader = User::factory()->mechanic()->create(['name' => 'Dragan Jovanovic']);
        $photo = JobAttachment::factory()->photo()->forJob($job)->uploadedBy($uploader)->create();

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();

        $response->assertSee(__('jobs.attachments.uploaded_by', [
            'name' => $uploader->shortName(),
            'date' => $photo->created_at->format('j M Y'),
        ]));

        $this->assertStringNotContainsString(
            'title="',
            $this->attachmentsSection($response->getContent()),
            'The tile must not hide its caption behind a title attribute.',
        );
    }

    public function test_a_document_renders_as_a_row_with_its_size_and_no_preview(): void
    {
        $job = Job::factory()->inProgress()->create();
        $document = JobAttachment::factory()->document()->forJob($job)->create();

        $response = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();

        $response->assertSee($document->original_filename);
        $response->assertSee($document->humanSize());
        $response->assertSee(__('jobs.attachments.types.document'));
        $response->assertSee(__('jobs.attachments.download.action'));

        $response->assertDontSee(route('attachments.show', ['attachment' => $document, 'variant' => 'thumb']), false);
        $response->assertDontSee(route('attachments.show', ['attachment' => $document, 'variant' => 'full']), false);
    }

    /**
     * A photo's delete lives in the viewer, which reads its target from the
     * trigger — so the gate is whether the trigger carries the action.
     */
    public function test_the_uploader_can_delete_a_photo_from_the_viewer_and_a_colleague_cannot(): void
    {
        $job = Job::factory()->inProgress()->create();
        $uploader = User::factory()->mechanic()->create();
        $photo = JobAttachment::factory()->photo()->forJob($job)->uploadedBy($uploader)->create();

        $deleteTarget = 'data-lightbox-delete-action="'.route('attachments.destroy', $photo).'"';

        $this->actingAs($uploader)
            ->get(route('jobs.show', $job))
            ->assertSee($deleteTarget, false);

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertDontSee($deleteTarget, false);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertSee($deleteTarget, false);
    }

    public function test_a_photo_tile_renders_no_delete_form_of_its_own(): void
    {
        $job = Job::factory()->inProgress()->create();
        $photo = JobAttachment::factory()->photo()->forJob($job)->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]*action="'.preg_quote(route('attachments.destroy', $photo), '/').'"/',
            $html,
        );
    }

    public function test_the_trigger_carries_the_viewers_confirm_message_and_accessible_name(): void
    {
        $job = Job::factory()->inProgress()->create();
        $photo = JobAttachment::factory()->photo()->forJob($job)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertSee('data-lightbox-delete-confirm="'.e(__('jobs.attachments.delete.confirm', ['filename' => $photo->original_filename])).'"', false)
            ->assertSee('data-lightbox-delete-label="'.e(__('jobs.attachments.delete.action_for', ['filename' => $photo->original_filename])).'"', false);
    }

    public function test_the_viewer_holds_one_hidden_delete_form_spoofed_as_delete(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobAttachment::factory()->photo()->forJob($job)->count(2)->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, preg_match_all('/<form[^>]*data-lightbox-delete[^-][^>]*>/', $html, $forms));
        $this->assertStringContainsString(' hidden', $forms[0][0]);
        $this->assertStringContainsString('data-confirm=""', $forms[0][0]);

        $dialog = substr($html, strpos($html, 'data-lightbox-dialog'));

        $this->assertMatchesRegularExpression('/data-lightbox-delete[^-].*?name="_method" value="DELETE"/s', $dialog);
        $this->assertStringContainsString(__('jobs.attachments.delete.action'), $dialog);
    }

    /**
     * The document row carries its own copy of the @can('delete') gate, in a
     * different file from the photo tile. Testing only the photo shape leaves
     * this one free to be hardcoded open with a green suite.
     */
    public function test_the_document_rows_delete_control_is_gated_the_same_way(): void
    {
        $job = Job::factory()->inProgress()->create();
        $uploader = User::factory()->mechanic()->create();
        $document = JobAttachment::factory()->document()->forJob($job)->uploadedBy($uploader)->create();

        $deleteForm = 'action="'.route('attachments.destroy', $document).'"';

        $this->actingAs($uploader)
            ->get(route('jobs.show', $job))
            ->assertSee($deleteForm, false);

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertDontSee($deleteForm, false);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertSee($deleteForm, false);
    }

    /**
     * Every thumbnail on a job used to announce the same accessible name,
     * because the label interpolated only the job reference. In a grid that
     * makes the links indistinguishable to a screen reader (WCAG 2.4.4).
     */
    public function test_each_photo_link_has_a_distinct_accessible_name(): void
    {
        $job = Job::factory()->inProgress()->create();

        JobAttachment::factory()->photo()->forJob($job)
            ->uploadedBy(User::factory()->mechanic()->create(['name' => 'Dragan Jovanovic']))
            ->create(['created_at' => now()->subDays(3)]);

        JobAttachment::factory()->photo()->forJob($job)
            ->uploadedBy(User::factory()->mechanic()->create(['name' => 'Milica Petrovic']))
            ->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->getContent();

        preg_match_all('/aria-label="(Open full-size[^"]*)"/', $html, $matches);

        $this->assertCount(2, $matches[1], 'Both photo links should carry an open-photo label.');
        $this->assertSame(
            $matches[1],
            array_unique($matches[1]),
            'Two thumbnails on one job must not announce the same accessible name.',
        );
    }

    public function test_the_delete_and_download_labels_are_substrings_of_their_accessible_names(): void
    {
        $document = JobAttachment::factory()->document()->create();

        $this->assertStringContainsString(
            __('jobs.attachments.delete.action'),
            __('jobs.attachments.delete.action_for', ['filename' => $document->original_filename]),
        );

        $this->assertStringContainsString(
            __('jobs.attachments.download.action'),
            __('jobs.attachments.download.action_for', ['filename' => $document->original_filename]),
        );

        $this->assertStringContainsString(
            __('jobs.attachments.upload.action'),
            __('jobs.attachments.upload.action_for', ['reference' => 'JOB-2026-0001']),
        );
    }

    public function test_a_soft_deleted_uploader_is_still_named_and_marked(): void
    {
        $job = Job::factory()->inProgress()->create();
        $uploader = User::factory()->mechanic()->create();
        JobAttachment::factory()->photo()->forJob($job)->uploadedBy($uploader)->create();

        $uploader->delete();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee($uploader->shortName())
            ->assertSee(__('app.deleted'));
    }

    public function test_the_lightbox_dialog_is_present_for_photos_and_absent_without_them(): void
    {
        $job = Job::factory()->inProgress()->create();
        $user = User::factory()->mechanic()->create();

        $this->actingAs($user)
            ->get(route('jobs.show', $job))
            ->assertDontSee('data-lightbox-dialog', false);

        JobAttachment::factory()->photo()->forJob($job)->create();

        $this->actingAs($user)
            ->get(route('jobs.show', $job))
            ->assertSee('data-lightbox-dialog', false)
            ->assertSee('data-lightbox-trigger', false)
            ->assertSee('data-lightbox-image', false)
            ->assertSee('data-lightbox-close', false)
            ->assertSee(__('jobs.attachments.lightbox.close'));
    }

    /**
     * A <dialog> with no accessible name is announced as an unlabelled group.
     * The close button's own label does not serve — it names the button.
     */
    public function test_the_lightbox_dialog_carries_an_accessible_name(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobAttachment::factory()->photo()->forJob($job)->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertSee('aria-label="'.__('jobs.attachments.lightbox.label').'"', false);
    }

    public function test_a_thumbnail_is_an_ordinary_link_to_the_full_variant_without_javascript(): void
    {
        $job = Job::factory()->inProgress()->create();
        $photo = JobAttachment::factory()->photo()->forJob($job)->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.show', $job))
            ->assertSee(
                'href="'.route('attachments.show', ['attachment' => $photo, 'variant' => 'full']).'"',
                false,
            );
    }

    public function test_the_section_costs_the_same_number_of_queries_however_many_attachments_it_has(): void
    {
        $this->assertSame(
            $this->queriesForJobWith(1),
            $this->queriesForJobWith(10),
            'The uploader must be eager loaded, not fetched once per attachment.',
        );
    }

    private function queriesForJobWith(int $attachments): int
    {
        $job = Job::factory()->inProgress()->create();

        JobAttachment::factory()->photo()->forJob($job)->count($attachments)->create();
        JobAttachment::factory()->document()->forJob($job)->count($attachments)->create();

        DB::enableQueryLog();
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }

    private function attachmentsSection(string $html): string
    {
        $start = strpos($html, e(__('jobs.show.attachments')));

        $this->assertNotFalse($start, 'The attachments section did not render.');

        return substr($html, $start);
    }
}
