<?php

namespace Tests\Feature\CheckIns;

use App\Enums\JobStatus;
use App\Enums\SignatureMethod;
use App\Exceptions\CheckInNotOpen;
use App\Exceptions\UnreadableSignature;
use App\Models\Job;
use App\Models\JobCheckIn;
use App\Models\JobItem;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\InvoiceService;
use App\Services\JobService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreatesSignatures;
use Tests\TestCase;

class CheckInSigningTest extends TestCase
{
    use CreatesSignatures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('attachments.disk'));
    }

    public function test_a_paper_signature_records_the_name_and_time_and_stores_no_file(): void
    {
        $this->freezeSecond();
        $job = $this->openSheet();

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('jobs.check-in.sign', $job), ['signature_method' => 'paper', 'signed_by_name' => 'Marko Petrović'])
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHas('success', __('check_ins.flash.signed', ['name' => 'Marko Petrović']));

        $checkIn = JobCheckIn::query()->sole();

        $this->assertSame('Marko Petrović', $checkIn->signed_by_name);
        $this->assertSame(SignatureMethod::Paper, $checkIn->signature_method);
        $this->assertNull($checkIn->signature_path);
        $this->assertTrue($checkIn->signed_at->equalTo(now()));
        $this->assertSame([], Storage::disk(config('attachments.disk'))->allFiles());
    }

    /**
     * Posts exactly what the rendered form carries with JavaScript off: every
     * named field in the signing form, with the paper button as submitter. The
     * hidden `signature` input is always sent — empty — which a hand-built
     * payload without that key never exercised, and which the `string` rule
     * refused until `nullable` was added.
     */
    public function test_the_rendered_form_signs_on_paper_without_javascript(): void
    {
        $job = $this->openSheet();
        $user = User::factory()->receptionist()->create();

        $html = (string) $this->actingAs($user)->get(route('jobs.check-in.show', $job))->getContent();

        $this->assertSame(1, preg_match('/<form[^>]*action="'.preg_quote(route('jobs.check-in.sign', $job), '/').'"[^>]*>(.*?)<\/form>/s', $html, $form));

        preg_match_all('/<input[^>]*\bname="([^"]+)"[^>]*\bvalue="([^"]*)"/', $form[1], $inputs, PREG_SET_ORDER);
        $payload = [];

        foreach ($inputs as [, $name, $value]) {
            $payload[$name] = html_entity_decode($value);
        }

        $this->assertArrayHasKey('signature', $payload, 'The form no longer carries the hidden signature field this test exists for.');
        $this->assertSame('', $payload['signature']);

        $payload['signature_method'] = 'paper';

        $this->actingAs($user)
            ->post(route('jobs.check-in.sign', $job), $payload)
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHasNoErrors();

        $this->assertSame(SignatureMethod::Paper, JobCheckIn::query()->sole()->signature_method);
    }

    public function test_an_empty_drawing_is_still_refused_as_missing(): void
    {
        $job = $this->openSheet();

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.check-in.sign', $job), ['signature_method' => 'drawn', 'signed_by_name' => 'Marko', 'signature' => ''])
            ->assertSessionHasErrors('signature');

        $this->assertNull(JobCheckIn::query()->sole()->signed_at);
    }

    public function test_a_drawn_signature_is_stored_flattened_onto_white_and_served_back(): void
    {
        $job = $this->openSheet();
        $user = User::factory()->mechanic()->create();

        $this->actingAs($user)
            ->patch(route('jobs.check-in.sign', $job), [
                'signature_method' => 'drawn',
                'signed_by_name' => 'Driver for Fleet d.o.o.',
                'signature' => $this->signatureDataUrl(600, 200, transparent: true),
            ])
            ->assertRedirect(route('jobs.check-in.show', $job));

        $checkIn = JobCheckIn::query()->sole();
        $disk = Storage::disk(config('attachments.disk'));

        $this->assertSame(SignatureMethod::Drawn, $checkIn->signature_method);
        $this->assertStringStartsWith(config('attachments.directory')."/{$job->getKey()}/signature_", $checkIn->signature_path);
        $this->assertStringEndsWith('.png', $checkIn->signature_path);
        $disk->assertExists($checkIn->signature_path);

        $stored = imagecreatefromstring($disk->get($checkIn->signature_path));
        $corner = imagecolorsforindex($stored, imagecolorat($stored, 0, 0));

        $this->assertSame(['red' => 255, 'green' => 255, 'blue' => 255, 'alpha' => 0], $corner, 'The transparent background was not flattened onto opaque white.');

        $served = $this->actingAs($user)->get(route('jobs.check-in.signature', $job));
        $served->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame($disk->get($checkIn->signature_path), $served->streamedContent());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unreadableSignatures(): array
    {
        return [
            'not base64' => ['data:image/png;base64,***not base64***'],
            'not a PNG' => ['data:image/png;base64,'.base64_encode('GIF89a this is not a png')],
            'empty payload' => ['data:image/png;base64,'],
        ];
    }

    #[DataProvider('unreadableSignatures')]
    public function test_an_unreadable_drawing_is_refused_on_the_signature_field_and_leaves_no_file(string $dataUrl): void
    {
        $job = $this->openSheet();

        $this->actingAs(User::factory()->mechanic()->create())
            ->from(route('jobs.check-in.show', $job))
            ->patch(route('jobs.check-in.sign', $job), [
                'signature_method' => 'drawn',
                'signed_by_name' => 'Marko',
                'signature' => $dataUrl,
            ])
            ->assertRedirect(route('jobs.check-in.show', $job))
            ->assertSessionHasErrors('signature');

        $this->assertNull(JobCheckIn::query()->sole()->signed_at);
        $this->assertSame([], Storage::disk(config('attachments.disk'))->allFiles());
    }

    public function test_the_unreadable_message_is_the_one_the_spec_names(): void
    {
        $job = $this->openSheet();

        $this->actingAs(User::factory()->mechanic()->create())
            ->from(route('jobs.check-in.show', $job))
            ->patch(route('jobs.check-in.sign', $job), [
                'signature_method' => 'drawn',
                'signed_by_name' => 'Marko',
                'signature' => 'data:image/png;base64,'.base64_encode('not a png'),
            ]);

        $this->assertSame(
            [__('check_ins.sign.errors.unreadable')],
            app('session.store')->get('errors')['default']['messages']['signature'] ?? [],
        );
    }

    public function test_a_drawing_larger_than_2000_by_1000_is_refused_by_its_header(): void
    {
        $this->assertSignatureRefused($this->signatureDataUrl(2001, 200));
        $this->assertSignatureRefused($this->signatureDataUrl(600, 1001));
    }

    public function test_a_drawing_at_exactly_2000_by_1000_is_accepted(): void
    {
        $job = $this->openSheet();

        app(CheckInService::class)->sign($job, User::factory()->mechanic()->create(), SignatureMethod::Drawn, 'Marko', $this->signatureDataUrl(2000, 1000));

        $this->assertTrue(JobCheckIn::query()->sole()->hasDrawnSignature());
    }

    public function test_a_decoded_png_over_512_kb_is_refused(): void
    {
        $bytes = $this->signaturePng(600, 200)."\0".str_repeat("\0", CheckInService::MAX_SIGNATURE_BYTES);

        $this->assertSignatureRefused(CheckInService::DATA_URL_PREFIX.base64_encode($bytes));
    }

    /**
     * The spec's own rule text, unquoted, lets this through: str_getcsv splits
     * its trailing comma off as an empty needle, which starts_with skips. The
     * service's strict prefix check would still refuse it as unreadable, so
     * the assertion is on WHICH layer refused: validation, with the
     * starts_with message — not the service's unreadable one.
     */
    public function test_a_prefix_missing_its_comma_fails_validation(): void
    {
        $job = $this->openSheet();

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.check-in.sign', $job), [
                'signature_method' => 'drawn',
                'signed_by_name' => 'Marko',
                'signature' => 'data:image/png;base64'.base64_encode($this->signaturePng()),
            ]);

        $messages = app('session.store')->get('errors')['default']['messages']['signature'] ?? [];

        $this->assertCount(1, $messages);
        $this->assertNotSame(__('check_ins.sign.errors.unreadable'), $messages[0], 'Validation let the comma-less prefix through; only the service caught it.');
        $this->assertStringContainsString(CheckInService::DATA_URL_PREFIX, $messages[0]);
    }

    /**
     * 700,000 'A's decode past the service's own 512 KB limit, which would
     * also refuse it on the `signature` field — so the assertion is on WHICH
     * layer refused: validation's max message, not the service's unreadable.
     */
    public function test_a_signature_over_700000_characters_fails_validation(): void
    {
        $job = $this->openSheet();

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.check-in.sign', $job), [
                'signature_method' => 'drawn',
                'signed_by_name' => 'Marko',
                'signature' => CheckInService::DATA_URL_PREFIX.str_repeat('A', 700_000),
            ]);

        $messages = app('session.store')->get('errors')['default']['messages']['signature'] ?? [];

        $this->assertCount(1, $messages);
        $this->assertNotSame(__('check_ins.sign.errors.unreadable'), $messages[0], 'Validation let the oversized payload through; only the service caught it.');
        $this->assertStringContainsString('700000', $messages[0]);
    }

    public function test_the_stored_ink_stays_black(): void
    {
        $job = $this->openSheet();

        app(CheckInService::class)->sign($job, User::factory()->mechanic()->create(), SignatureMethod::Drawn, 'Marko', $this->signatureDataUrl(600, 200, transparent: true));

        $stored = imagecreatefromstring(Storage::disk(config('attachments.disk'))->get(JobCheckIn::query()->sole()->signature_path));
        $ink = imagecolorsforindex($stored, imagecolorat($stored, 300, 100));

        $this->assertLessThan(64, max($ink['red'], $ink['green'], $ink['blue']), 'The stroke through the middle of the pad is not dark: '.json_encode($ink));
    }

    public function test_voiding_the_invoice_changes_nothing_on_the_sheet(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();
        $checkIn = JobCheckIn::factory()->forJob($job)->signedOnPaper()->create();
        $before = $checkIn->fresh()->getAttributes();

        $invoice = app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());
        app(InvoiceService::class)->void($invoice, User::factory()->admin()->create());

        $this->assertSame($before, $checkIn->fresh()->getAttributes());
        $this->assertFalse($job->fresh()->acceptsCheckIn(), 'A signed sheet must stay read-only after the void reopens the job.');
    }

    public function test_a_signature_sent_with_the_paper_method_is_refused(): void
    {
        $job = $this->openSheet();

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.check-in.sign', $job), [
                'signature_method' => 'paper',
                'signed_by_name' => 'Marko',
                'signature' => $this->signatureDataUrl(),
            ])
            ->assertSessionHasErrors('signature');

        $this->assertNull(JobCheckIn::query()->sole()->signed_at);
    }

    public function test_a_drawn_method_without_a_signature_is_refused(): void
    {
        $job = $this->openSheet();

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.check-in.sign', $job), ['signature_method' => 'drawn', 'signed_by_name' => 'Marko'])
            ->assertSessionHasErrors('signature');
    }

    public function test_a_name_and_a_known_method_are_required(): void
    {
        $job = $this->openSheet();

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.check-in.sign', $job), ['signature_method' => 'thumbprint'])
            ->assertSessionHasErrors(['signature_method', 'signed_by_name']);
    }

    public function test_the_stored_file_is_removed_when_the_signing_save_fails(): void
    {
        $job = $this->openSheet();

        Event::listen('eloquent.saving: '.JobCheckIn::class, function (JobCheckIn $checkIn): void {
            if ($checkIn->signed_at !== null) {
                throw new RuntimeException('Simulated failure after the file was written.');
            }
        });

        try {
            app(CheckInService::class)->sign($job, User::factory()->mechanic()->create(), SignatureMethod::Drawn, 'Marko', $this->signatureDataUrl());
            $this->fail('The simulated failure did not surface.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated failure after the file was written.', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk(config('attachments.disk'))->allFiles());
        $this->assertNull(JobCheckIn::query()->sole()->signed_at);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function roles(): array
    {
        return ['mechanic' => ['mechanic'], 'receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    #[DataProvider('roles')]
    public function test_signing_is_one_way_for_every_role(string $role): void
    {
        $job = $this->openSheet();
        $user = User::factory()->{$role}()->create();
        $sign = fn (string $name) => $this->actingAs($user)->patch(route('jobs.check-in.sign', $job), [
            'signature_method' => 'paper',
            'signed_by_name' => $name,
        ]);

        $sign('First Signer')->assertRedirect(route('jobs.check-in.show', $job));
        $sign('Second Signer')->assertForbidden();

        $this->actingAs($user)->put(route('jobs.check-in.update', $job), ['fuel_level' => 'full'])->assertForbidden();

        $this->assertSame('First Signer', JobCheckIn::query()->sole()->signed_by_name);
    }

    public function test_the_service_refuses_a_second_signature_under_the_lock(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->signedOnPaper()->create();

        $this->expectException(CheckInNotOpen::class);

        app(CheckInService::class)->sign($job, User::factory()->mechanic()->create(), SignatureMethod::Paper, 'Again', null);
    }

    public function test_signing_without_a_sheet_is_not_found(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.check-in.sign', $job), ['signature_method' => 'paper', 'signed_by_name' => 'Marko'])
            ->assertNotFound();
    }

    public function test_c2_an_unsigned_sheet_on_a_completed_unlocked_job_can_still_be_signed(): void
    {
        $job = Job::factory()->completed()->create();
        JobCheckIn::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('jobs.check-in.sign', $job), ['signature_method' => 'paper', 'signed_by_name' => 'Late Signer'])
            ->assertRedirect(route('jobs.check-in.show', $job));

        $this->assertTrue(JobCheckIn::query()->sole()->isSigned());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsignableStates(): array
    {
        return ['cancelled' => ['cancelled'], 'locked' => ['locked']];
    }

    #[DataProvider('unsignableStates')]
    public function test_a_cancelled_or_invoiced_jobs_sheet_cannot_be_signed(string $state): void
    {
        $job = Job::factory()->{$state}()->create();
        JobCheckIn::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('jobs.check-in.sign', $job), ['signature_method' => 'paper', 'signed_by_name' => 'Too Late'])
            ->assertForbidden();

        $this->assertNull(JobCheckIn::query()->sole()->signed_at);
    }

    public function test_reopening_a_job_makes_its_unsigned_sheet_editable_again_and_leaves_a_signed_one_read_only(): void
    {
        $receptionist = User::factory()->receptionist()->create();

        $unsigned = Job::factory()->completed()->create();
        JobCheckIn::factory()->forJob($unsigned)->create();
        $signed = Job::factory()->completed()->create();
        JobCheckIn::factory()->forJob($signed)->signedOnPaper()->create();

        foreach ([$unsigned, $signed] as $job) {
            app(JobService::class)->reopen($job);
        }

        $this->assertSame(JobStatus::InProgress, $unsigned->fresh()->status);
        $this->actingAs($receptionist)->put(route('jobs.check-in.update', $unsigned), ['fuel_level' => 'full'])->assertRedirect();
        $this->actingAs($receptionist)->put(route('jobs.check-in.update', $signed), ['fuel_level' => 'full'])->assertForbidden();
    }

    public function test_cancelling_a_job_leaves_its_sheet_as_it_was(): void
    {
        $job = Job::factory()->inProgress()->create();
        $checkIn = JobCheckIn::factory()->forJob($job)->create(['damage_notes' => 'Dent in the door']);
        $before = $checkIn->fresh()->getAttributes();

        app(JobService::class)->cancel($job, User::factory()->receptionist()->create());

        $this->assertSame($before, $checkIn->fresh()->getAttributes());
    }

    private function openSheet(): Job
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create();

        return $job;
    }

    private function assertSignatureRefused(string $dataUrl): void
    {
        $job = $this->openSheet();

        try {
            app(CheckInService::class)->sign($job, User::factory()->mechanic()->create(), SignatureMethod::Drawn, 'Marko', $dataUrl);
            $this->fail('An oversized signature was accepted.');
        } catch (UnreadableSignature) {
            $this->assertNull($job->checkIn()->first()->signed_at);
        }

        $this->assertSame([], Storage::disk(config('attachments.disk'))->allFiles());
    }
}
