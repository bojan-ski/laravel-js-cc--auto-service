<?php

namespace Tests\Feature\CheckIns;

use App\Enums\AttachmentCategory;
use App\Enums\FuelLevel;
use App\Enums\SignatureMethod;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobCheckIn;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

/**
 * Each check-in form submitted exactly as a browser would build it from the
 * rendered page — every named field, the checked radio, textarea contents,
 * and the submitter button's own name/value — rather than from a payload the
 * test writes by hand. A hand-built payload hid a real defect in this feature:
 * the always-present empty `signature` input the paper path sends.
 */
class CheckInRenderedFormsTest extends TestCase
{
    use CreatesRealUploads;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('attachments.disk'));
    }

    public function test_the_sheet_form_submits_its_own_fields(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create([
            'fuel_level' => FuelLevel::Quarter,
            'damage_notes' => "Dent in door\nCracked mirror",
            'valuables' => 'Child seat',
        ]);
        $user = User::factory()->mechanic()->create();

        $form = $this->form($this->page($user, $job), route('jobs.check-in.update', $job));

        $this->assertSame('quarter', $form['fields']['fuel_level'] ?? null, 'The checked radio is not the stored fuel level.');
        $this->assertSame("Dent in door\nCracked mirror", $form['fields']['damage_notes'] ?? null);
        $this->assertSame('Child seat', $form['fields']['valuables'] ?? null);
        $this->assertSame('PUT', $form['fields']['_method'] ?? null);

        $this->actingAs($user)
            ->post(route('jobs.check-in.update', $job), [...$form['fields'], 'fuel_level' => 'full'])
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHasNoErrors();

        $checkIn = JobCheckIn::query()->sole();

        $this->assertSame(FuelLevel::Full, $checkIn->fuel_level);
        $this->assertSame("Dent in door\nCracked mirror", $checkIn->damage_notes);
    }

    public function test_the_photo_form_is_multipart_and_uploads_under_its_own_field_name(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create();
        $user = User::factory()->mechanic()->create();

        $form = $this->form($this->page($user, $job), route('jobs.check-in.photos.store', $job));

        $this->assertSame('multipart/form-data', $form['enctype'], 'Without multipart encoding a browser sends no file at all.');
        $this->assertCount(1, $form['files']);

        $this->actingAs($user)
            ->post(route('jobs.check-in.photos.store', $job), [
                ...$form['fields'],
                $form['files'][0] => $this->realImageUpload('bumper.jpg', 300, 200),
            ])
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHasNoErrors();

        $this->assertSame(AttachmentCategory::CheckIn, JobAttachment::query()->sole()->category);
    }

    public function test_the_signature_form_signs_on_paper_through_its_own_button(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create();
        $user = User::factory()->receptionist()->create();

        $form = $this->form($this->page($user, $job), route('jobs.check-in.sign', $job));
        $paper = collect($form['buttons'])->firstWhere('value', 'paper');

        $this->assertNotNull($paper, 'The form has no paper submit button.');
        $this->assertSame('', $form['fields']['signature'] ?? null, 'The hidden signature field is no longer sent empty.');

        $this->actingAs($user)
            ->post(route('jobs.check-in.sign', $job), [...$form['fields'], $paper['name'] => $paper['value']])
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHasNoErrors();

        $this->assertSame(SignatureMethod::Paper, JobCheckIn::query()->sole()->signature_method);
    }

    private function page(User $user, Job $job): string
    {
        return (string) $this->actingAs($user)->get(route('jobs.check-in.show', $job))->assertOk()->getContent();
    }

    /**
     * @return array{enctype: ?string, fields: array<string, string>, files: list<string>, buttons: list<array{name: string, value: string}>}
     */
    private function form(string $html, string $action): array
    {
        $pattern = '/<form\b([^>]*)action="'.preg_quote($action, '/').'"([^>]*)>(.*?)<\/form>/s';

        $this->assertSame(1, preg_match($pattern, $html, $match), "No form posts to {$action}.");

        $attributes = $match[1].$match[2];
        $body = $match[3];
        $fields = [];
        $files = [];
        $buttons = [];

        preg_match_all('/<input\b([^>]*)>/s', $body, $inputs);

        foreach ($inputs[1] as $input) {
            $name = $this->attribute($input, 'name');
            $type = $this->attribute($input, 'type') ?? 'text';

            if ($name === null) {
                continue;
            }

            if ($type === 'file') {
                $files[] = $name;

                continue;
            }

            if ($type === 'radio' && ! preg_match('/\schecked\b/', $input)) {
                continue;
            }

            $fields[$name] = html_entity_decode((string) $this->attribute($input, 'value'), ENT_QUOTES);
        }

        preg_match_all('/<textarea\b([^>]*)>(.*?)<\/textarea>/s', $body, $textareas, PREG_SET_ORDER);

        foreach ($textareas as [, $textareaAttributes, $content]) {
            $fields[(string) $this->attribute($textareaAttributes, 'name')] = html_entity_decode($content, ENT_QUOTES);
        }

        preg_match_all('/<button\b([^>]*)>/s', $body, $buttonTags);

        foreach ($buttonTags[1] as $button) {
            $name = $this->attribute($button, 'name');

            if ($name !== null) {
                $buttons[] = ['name' => $name, 'value' => (string) $this->attribute($button, 'value')];
            }
        }

        return ['enctype' => $this->attribute($attributes, 'enctype'), 'fields' => $fields, 'files' => $files, 'buttons' => $buttons];
    }

    private function attribute(string $tag, string $name): ?string
    {
        return preg_match('/\s'.$name.'="([^"]*)"/', ' '.$tag, $match) === 1 ? $match[1] : null;
    }
}
