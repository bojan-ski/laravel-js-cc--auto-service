<?php

namespace Tests\Feature\CheckIns;

use App\Enums\FuelLevel;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobCheckIn;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckInPageViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('attachments.disk'));
    }

    public function test_a_working_job_with_no_sheet_shows_the_empty_form_and_no_photo_or_signature_section(): void
    {
        $job = Job::factory()->received()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.show', $job))
            ->assertOk()
            ->assertViewIs('pages.check-ins.show')
            ->assertSee('action="'.route('jobs.check-in.update', $job).'"', false)
            ->assertSee(__('check_ins.form.submit'))
            ->assertDontSee(__('check_ins.photos.heading'))
            ->assertDontSee(__('check_ins.sign.heading'))
            ->assertDontSee(__('check_ins.show.print'));
    }

    public function test_an_open_sheet_prefills_the_form_and_offers_photos_and_both_ways_to_sign(): void
    {
        $customer = Customer::factory()->create(['name' => 'Jovana Ilić']);
        $job = Job::factory()->inProgress()->for(Vehicle::factory()->for($customer))->create();
        JobCheckIn::factory()->forJob($job)->create([
            'fuel_level' => FuelLevel::ThreeQuarters,
            'damage_notes' => 'Scratch on rear bumper',
            'valuables' => 'Child seat',
        ]);

        $html = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.show', $job))
            ->assertOk()
            ->assertSee('Scratch on rear bumper')
            ->assertSee('Child seat')
            ->assertSee('value="three_quarters"', false)
            ->assertSee(__('check_ins.photos.heading'))
            ->assertSee('action="'.route('jobs.check-in.photos.store', $job).'"', false)
            ->assertSee(__('check_ins.show.print'))
            ->getContent();

        $this->assertMatchesRegularExpression('/value="three_quarters"\s+checked/', $html);
        $this->assertStringContainsString('value="Jovana Ilić"', $html, 'The customer name is prefilled for signing.');
        $this->assertStringContainsString('value="paper"', $html);
        $this->assertStringContainsString(e(__('check_ins.sign.paper_submit')), $html);
    }

    /**
     * With JavaScript off only the paper path may be visible or reachable: the
     * pad and the Sign button ship hidden, and Sign is also disabled, so the
     * Enter key's implicit submission cannot send an empty drawing.
     */
    public function test_the_drawn_path_ships_hidden_and_the_sign_button_disabled(): void
    {
        $job = $this->openSheet();

        $html = (string) $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.show', $job))
            ->getContent();

        $this->assertMatchesRegularExpression('/<div data-signature-drawn hidden/', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*value="drawn"[^>]*data-signature-drawn[^>]*hidden[^>]*disabled/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*value="paper"[^>]*(hidden|disabled)/s', $html);
    }

    public function test_the_pad_has_an_accessible_name_and_description(): void
    {
        $job = $this->openSheet();

        $html = (string) $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.show', $job))
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<canvas[^>]*role="img"[^>]*aria-label="'.preg_quote(e(__('check_ins.sign.pad_label')), '/').'"[^>]*aria-describedby="signature-pad-hint"/s',
            $html,
        );
        $this->assertStringContainsString('id="signature-pad-hint"', $html);
    }

    public function test_the_heading_outline_is_one_h1_followed_by_h2s(): void
    {
        $job = $this->openSheet();

        $html = (string) $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.show', $job))
            ->getContent();

        preg_match_all('/<h([1-6])\b/', $html, $matches);

        $this->assertSame(['1', '2', '2', '2'], $matches[1]);
    }

    public function test_the_upload_and_back_links_accessible_names_start_with_their_visible_text(): void
    {
        $job = $this->openSheet();

        $html = (string) $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.show', $job))
            ->getContent();

        $this->assertStringStartsWith(__('check_ins.photos.upload'), __('check_ins.photos.upload_for', ['reference' => $job->reference]));
        $this->assertStringContainsString('aria-label="'.e(__('check_ins.photos.upload_for', ['reference' => $job->reference])).'"', $html);
        $this->assertStringContainsString(e(__('check_ins.show.back', ['reference' => $job->reference])), $html);
        $this->assertStringContainsString('aria-describedby="check-in-photo-hint"', $html);
    }

    public function test_a_failed_save_ties_the_error_to_its_field(): void
    {
        $job = $this->openSheet();
        $user = User::factory()->mechanic()->create();

        $this->actingAs($user)
            ->from(route('jobs.check-in.show', $job))
            ->put(route('jobs.check-in.update', $job), ['fuel_level' => 'half', 'damage_notes' => str_repeat('a', 2001)])
            ->assertRedirect(route('jobs.check-in.show', $job));

        $this->actingAs($user)
            ->get(route('jobs.check-in.show', $job))
            ->assertSee('aria-describedby="damage_notes-error"', false)
            ->assertSee('id="damage_notes-error"', false);
    }

    public function test_a_signed_sheet_is_read_only_with_its_signature_note_and_no_controls(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $photo = JobAttachment::factory()->checkIn()->forJob($job)->uploadedBy($mechanic)->create();
        JobCheckIn::factory()->forJob($job)->signedByDrawing()->create([
            'signed_by_name' => 'Petar Petrović',
            'damage_notes' => "Dent in door\nCracked mirror",
        ]);

        $html = (string) $this->actingAs($mechanic)
            ->get(route('jobs.check-in.show', $job))
            ->assertOk()
            ->assertSee(__('check_ins.sign.read_only'))
            ->assertSee(__('check_ins.signature_methods.drawn'))
            ->assertSee(route('jobs.check-in.signature', $job), false)
            ->assertSee(__('check_ins.sign.image_alt', ['name' => 'Petar Petrović']))
            ->assertSee(route('attachments.show', ['attachment' => $photo, 'variant' => 'thumb']), false)
            ->assertDontSee('action="'.route('jobs.check-in.update', $job).'"', false)
            ->assertDontSee('action="'.route('jobs.check-in.photos.store', $job).'"', false)
            ->assertDontSee('action="'.route('jobs.check-in.sign', $job).'"', false)
            ->assertDontSee('data-lightbox-delete-action', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/role="note"/', $html);
        $this->assertMatchesRegularExpression('/<dd class="whitespace-pre-line[^"]*">Dent in door\nCracked mirror\s*<\/dd>/', $html);
    }

    public function test_an_unsigned_sheet_on_a_completed_job_is_read_only_but_can_still_be_signed(): void
    {
        $job = Job::factory()->completed()->create();
        JobCheckIn::factory()->forJob($job)->create(['damage_notes' => 'Scuffed sill']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.check-in.show', $job))
            ->assertOk()
            ->assertSee('Scuffed sill')
            ->assertDontSee('action="'.route('jobs.check-in.update', $job).'"', false)
            ->assertDontSee('action="'.route('jobs.check-in.photos.store', $job).'"', false)
            ->assertSee('action="'.route('jobs.check-in.sign', $job).'"', false);
    }

    public function test_a_cancelled_jobs_unsigned_sheet_says_it_was_never_signed(): void
    {
        $job = Job::factory()->cancelled()->create();
        JobCheckIn::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('jobs.check-in.show', $job))
            ->assertOk()
            ->assertSee(__('check_ins.sign.empty'))
            ->assertDontSee('action="'.route('jobs.check-in.sign', $job).'"', false);
    }

    public function test_a_closed_job_with_no_sheet_says_none_was_recorded(): void
    {
        $job = Job::factory()->cancelled()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.show', $job))
            ->assertOk()
            ->assertSee(__('check_ins.show.none'))
            ->assertDontSee('action="'.route('jobs.check-in.update', $job).'"', false);
    }

    public function test_the_recorder_is_named_and_marked_when_offboarded(): void
    {
        $recorder = User::factory()->mechanic()->create(['name' => 'Dragan Jovanović']);
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->recordedBy($recorder)->create();
        $recorder->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('jobs.check-in.show', $job))
            ->assertSee(__('check_ins.show.recorded_by', ['name' => 'Dragan Jovanović']))
            ->assertSee('('.__('app.deleted').')');
    }

    /**
     * The per-photo @can('delete') reads the job and its sheet through the
     * relations the controller sets, so the page's cost must not grow with the
     * number of photos. Lazy-loading prevention cannot see this (single-row
     * reads never arm it), so it is pinned by count.
     */
    public function test_the_page_costs_the_same_however_many_photos_it_shows(): void
    {
        foreach (['mechanic', 'receptionist'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->assertSame(
                $this->queriesForSheetWith(1, $user),
                $this->queriesForSheetWith(10, $user),
                "The {$role}'s sheet page issues a query per photo.",
            );
        }
    }

    public function test_the_fuel_radios_render_every_full_label(): void
    {
        $html = (string) $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.show', $this->openSheet()))
            ->getContent();

        foreach (FuelLevel::cases() as $level) {
            $this->assertMatchesRegularExpression(
                '/<label for="fuel_level-'.$level->value.'"[^>]*>'.preg_quote(e($level->label()), '/').'<\/label>/',
                $html,
            );
        }
    }

    public function test_the_header_states_the_mileage_the_received_time_and_links_the_print_view(): void
    {
        $job = Job::factory()->inProgress()->create(['mileage_in' => 184320]);
        JobCheckIn::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.show', $job))
            ->assertSee(__('jobs.show.mileage_value', ['mileage' => '184,320']))
            ->assertSee($job->created_at->isoFormat('D MMM HH:mm'))
            ->assertSee('href="'.route('jobs.check-in.print', $job).'"', false)
            ->assertSee('href="'.route('jobs.show', $job).'"', false);
    }

    public function test_a_signed_sheet_names_who_signed_and_when_and_its_fuel_level(): void
    {
        $job = Job::factory()->inProgress()->create();
        $checkIn = JobCheckIn::factory()->forJob($job)->signedOnPaper()->create([
            'signed_by_name' => 'Petar Petrović',
            'fuel_level' => FuelLevel::ThreeQuarters,
        ]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.check-in.show', $job))
            ->assertSee(__('check_ins.sign.signed', [
                'name' => 'Petar Petrović',
                'date' => $checkIn->signed_at->isoFormat('D MMM YYYY HH:mm'),
            ]))
            ->assertSee(__('check_ins.signature_methods.paper'))
            ->assertSee('<dd class="tabular-nums">'.e(FuelLevel::ThreeQuarters->label()).'</dd>', false);
    }

    public function test_a_refused_drawing_ties_its_error_to_the_pad_and_keeps_the_name(): void
    {
        $job = $this->openSheet();
        $user = User::factory()->mechanic()->create();

        $this->actingAs($user)
            ->from(route('jobs.check-in.show', $job))
            ->patch(route('jobs.check-in.sign', $job), [
                'signature_method' => 'drawn',
                'signed_by_name' => 'Name Typed By Staff',
                'signature' => 'data:image/png;base64,'.base64_encode('not a png'),
            ])
            ->assertRedirect(route('jobs.check-in.show', $job));

        $html = (string) $this->actingAs($user)->get(route('jobs.check-in.show', $job))->getContent();

        $this->assertStringContainsString('aria-describedby="signature-pad-hint signature-error"', $html);
        $this->assertStringContainsString('id="signature-error"', $html);
        $this->assertStringContainsString(e(__('check_ins.sign.errors.unreadable')), $html);
        $this->assertStringContainsString('value="Name Typed By Staff"', $html);
    }

    public function test_the_empty_pad_message_is_a_correction_not_the_status_sentence(): void
    {
        $html = (string) $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.show', $this->openSheet()))
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<p data-signature-empty hidden role="alert"[^>]*>'.preg_quote(e(__('check_ins.sign.errors.empty')), '/').'<\/p>/',
            $html,
        );
        $this->assertNotSame(__('check_ins.sign.errors.empty'), __('check_ins.sign.empty'));
    }

    private function queriesForSheetWith(int $photos, User $user): int
    {
        $job = $this->openSheet();
        JobAttachment::factory()->checkIn()->forJob($job)->uploadedBy($user)->count($photos)->create();

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('jobs.check-in.show', $job))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }

    private function openSheet(): Job
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create();

        return $job;
    }
}
