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
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckInPrintTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('attachments.disk'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function roles(): array
    {
        return ['mechanic' => ['mechanic'], 'receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    #[DataProvider('roles')]
    public function test_an_unsigned_sheet_prints_the_shop_vehicle_condition_and_blank_signature_lines(string $role): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Ilić']);
        $vehicle = Vehicle::factory()->for($customer)->create(['vin' => 'WVWZZZ1JZXW000001', 'make' => 'Volkswagen', 'model' => 'Golf IV']);
        $job = Job::factory()->inProgress()->for($vehicle)->create(['mileage_in' => 184320]);
        JobCheckIn::factory()->forJob($job)->create(['damage_notes' => 'Scratch on rear bumper', 'valuables' => 'Child seat']);
        $photo = JobAttachment::factory()->checkIn()->forJob($job)->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.check-in.print', $job))
            ->assertOk()
            ->assertViewIs('pages.check-ins.print')
            ->assertSee(config('shop.identity.name'))
            ->assertSee(config('shop.identity.address'))
            ->assertSee(__('check_ins.print.title'))
            ->assertSee($job->reference)
            ->assertSee('Volkswagen Golf IV')
            ->assertSee('WVWZZZ1JZXW000001')
            ->assertSee('Milan Ilić')
            ->assertSee('184,320')
            ->assertSee('Scratch on rear bumper')
            ->assertSee('Child seat')
            ->assertSee(route('attachments.show', ['attachment' => $photo, 'variant' => 'thumb']), false)
            ->assertSee(__('check_ins.print.declaration'))
            ->assertSee(__('check_ins.print.signature_line'))
            ->assertSee(__('check_ins.print.name_line'))
            ->assertSee(__('check_ins.print.date_line'))
            ->assertSee('data-print-slot', false)
            ->assertSee(route('jobs.check-in.show', $job), false);
    }

    /**
     * Sentinel identity values, so a view that swapped or dropped a field
     * cannot pass by reading the same config the test reads.
     */
    public function test_the_letterhead_prints_each_identity_field_and_the_print_date(): void
    {
        $this->freezeSecond();
        config([
            'shop.identity.name' => 'Sentinel Garage Name',
            'shop.identity.address' => 'Sentinel Street 99',
            'shop.identity.tax_number' => 'PIB 000000042',
        ]);
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create(['fuel_level' => FuelLevel::Quarter]);

        $html = (string) $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.print', $job))
            ->getContent();

        $this->assertMatchesRegularExpression('/<p class="text-lg font-bold">Sentinel Garage Name<\/p>\s*<p>Sentinel Street 99<\/p>\s*<p class="tabular-nums">PIB 000000042<\/p>/', $html);
        $this->assertStringContainsString(e(__('check_ins.print.printed', ['date' => now()->isoFormat('D MMM YYYY HH:mm')])), $html);
        $this->assertStringContainsString($job->created_at->isoFormat('D MMM YYYY HH:mm'), $html);
        $this->assertStringContainsString('<dd class="tabular-nums">'.e(FuelLevel::Quarter->label()).'</dd>', $html);
    }

    public function test_the_blank_signature_lines_sit_in_the_signature_section(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create();

        $section = $this->signatureSection((string) $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.print', $job))
            ->getContent());

        foreach (['signature_line', 'name_line', 'date_line'] as $line) {
            $this->assertMatchesRegularExpression('/<p class="mt-1 text-xs uppercase tracking-wider text-text-muted">'.preg_quote(e(__('check_ins.print.'.$line)), '/').'<\/p>/', $section);
        }
    }

    public function test_a_drawn_signature_prints_who_signed_and_when(): void
    {
        $job = Job::factory()->inProgress()->create();
        $checkIn = JobCheckIn::factory()->forJob($job)->signedByDrawing()->create(['signed_by_name' => 'Ana Kovač']);

        $section = $this->signatureSection((string) $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.print', $job))
            ->getContent());

        $this->assertStringContainsString(e(__('check_ins.sign.signed', [
            'name' => 'Ana Kovač',
            'date' => $checkIn->signed_at->isoFormat('D MMM YYYY HH:mm'),
        ])), $section);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function closedStates(): array
    {
        return ['completed' => ['completed'], 'cancelled' => ['cancelled'], 'locked' => ['locked']];
    }

    #[DataProvider('closedStates')]
    public function test_a_closed_jobs_sheet_still_prints(string $state): void
    {
        $job = Job::factory()->{$state}()->create();
        JobCheckIn::factory()->forJob($job)->signedOnPaper()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.print', $job))
            ->assertOk();
    }

    public function test_a_drawn_signature_prints_the_image_and_no_blank_lines(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->signedByDrawing()->create(['signed_by_name' => 'Ana Kovač']);

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.print', $job))
            ->assertOk()
            ->assertSee(route('jobs.check-in.signature', $job), false)
            ->assertSee(__('check_ins.sign.image_alt', ['name' => 'Ana Kovač']))
            ->assertDontSee(__('check_ins.print.signature_line'));
    }

    public function test_a_paper_signature_prints_a_record_of_it(): void
    {
        $job = Job::factory()->inProgress()->create();
        $checkIn = JobCheckIn::factory()->forJob($job)->signedOnPaper()->create(['signed_by_name' => 'Ana Kovač']);

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.check-in.print', $job))
            ->assertOk()
            ->assertSee(__('check_ins.print.signed_on_paper', [
                'name' => 'Ana Kovač',
                'date' => $checkIn->signed_at->isoFormat('D MMM YYYY HH:mm'),
            ]))
            ->assertDontSee(route('jobs.check-in.signature', $job), false)
            ->assertDontSee(__('check_ins.print.signature_line'));
    }

    public function test_printing_needs_a_sheet(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('jobs.check-in.print', Job::factory()->inProgress()->create()))
            ->assertNotFound();
    }

    public function test_the_print_view_follows_the_staff_members_language(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create();

        $html = (string) $this->actingAs(User::factory()->mechanic()->create(['locale' => 'de']))
            ->get(route('jobs.check-in.print', $job))
            ->assertOk()
            ->assertSee(trans('check_ins.print.declaration', [], 'de'))
            ->getContent();

        $this->assertStringContainsString('<h1 class="text-2xl font-bold">'.e(trans('check_ins.print.title', [], 'de')).'</h1>', $html, 'The <title> also contains the German word, so only the h1 proves the heading is translated.');
    }

    private function signatureSection(string $html): string
    {
        $heading = e(__('check_ins.sign.heading'));
        $start = strrpos($html, '>'.$heading.'</h2>');

        $this->assertNotFalse($start, 'The signature section did not render.');

        return substr($html, $start);
    }
}
