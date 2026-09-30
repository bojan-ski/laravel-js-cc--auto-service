<?php

namespace Tests\Feature\CheckIns;

use App\Enums\FuelLevel;
use App\Enums\SignatureMethod;
use App\Exceptions\CheckInSignedException;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobCheckIn;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckInModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_exactly_the_three_condition_fields_are_fillable(): void
    {
        $this->assertSame(['fuel_level', 'damage_notes', 'valuables'], (new JobCheckIn)->getFillable());
        $this->assertSame([], (new JobAttachment)->getFillable());
    }

    public function test_the_enum_and_date_columns_are_cast(): void
    {
        $checkIn = JobCheckIn::factory()->signedOnPaper()->create(['fuel_level' => FuelLevel::ThreeQuarters])->fresh();

        $this->assertSame(FuelLevel::ThreeQuarters, $checkIn->fuel_level);
        $this->assertSame(SignatureMethod::Paper, $checkIn->signature_method);
        $this->assertNotNull($checkIn->signed_at);
        $this->assertTrue($checkIn->isSigned());
        $this->assertFalse($checkIn->hasDrawnSignature());
    }

    public function test_the_recorder_resolves_even_after_being_offboarded(): void
    {
        $recorder = User::factory()->mechanic()->create();
        $checkIn = JobCheckIn::factory()->recordedBy($recorder)->create();
        $recorder->delete();

        $this->assertTrue($checkIn->fresh()->recordedBy->is($recorder));
    }

    public function test_every_fuel_level_and_signature_method_resolves_a_label(): void
    {
        foreach (FuelLevel::cases() as $level) {
            $this->assertNotSame('check_ins.fuel_levels.'.$level->value, $level->label());
        }

        foreach (SignatureMethod::cases() as $method) {
            $this->assertNotSame('check_ins.signature_methods.'.$method->value, $method->label());
            $this->assertStringNotContainsString('check_ins.', $method->summaryLabel());
        }
    }

    public function test_an_unsigned_sheet_can_be_edited_and_then_signed(): void
    {
        $checkIn = JobCheckIn::factory()->create();

        $checkIn->damage_notes = 'Scratch on the rear bumper';
        $checkIn->save();

        $checkIn->signed_by_name = 'Marko Petrović';
        $checkIn->signature_method = SignatureMethod::Paper;
        $checkIn->signed_at = now();
        $checkIn->save();

        $this->assertTrue($checkIn->fresh()->isSigned());
    }

    /**
     * The mutation target: a guard reading the LIVE signed_at would refuse the
     * very save that signs, and a guard reading nothing would accept this one.
     */
    public function test_a_signed_sheet_refuses_a_second_signature(): void
    {
        $checkIn = JobCheckIn::factory()->signedOnPaper()->create()->fresh();

        $checkIn->signed_by_name = 'Someone else';
        $checkIn->signed_at = now()->addHour();

        $this->expectException(CheckInSignedException::class);

        $checkIn->save();
    }

    public function test_a_signed_sheet_refuses_an_edit_to_its_condition(): void
    {
        $checkIn = JobCheckIn::factory()->signedOnPaper()->create()->fresh();
        $checkIn->damage_notes = 'Added after signing';

        try {
            $checkIn->save();
            $this->fail('A signed sheet accepted an edit.');
        } catch (CheckInSignedException) {
            $this->assertNull($checkIn->fresh()->damage_notes);
        }
    }

    public function test_a_signed_sheet_refuses_to_be_deleted(): void
    {
        $checkIn = JobCheckIn::factory()->signedOnPaper()->create()->fresh();

        try {
            $checkIn->delete();
            $this->fail('A signed sheet was deleted.');
        } catch (CheckInSignedException) {
            $this->assertTrue(JobCheckIn::query()->whereKey($checkIn->getKey())->exists());
        }
    }

    public function test_a_sheet_loaded_without_signed_at_refuses_to_save(): void
    {
        JobCheckIn::factory()->create();

        $partial = JobCheckIn::query()->select(['id', 'job_id', 'damage_notes'])->firstOrFail();
        $partial->damage_notes = 'Written through a partial select';

        $this->expectException(CheckInSignedException::class);

        $partial->save();
    }

    public function test_a_check_in_photo_of_a_signed_sheet_refuses_save_and_delete(): void
    {
        $job = Job::factory()->inProgress()->create();
        $photo = JobAttachment::factory()->checkIn()->forJob($job)->create();
        JobCheckIn::factory()->forJob($job)->signedOnPaper()->create();

        try {
            $photo->original_filename = 'renamed.jpg';
            $photo->save();
            $this->fail('A signed sheet\'s photo accepted an edit.');
        } catch (CheckInSignedException) {
            $this->assertTrue(true);
        }

        try {
            $photo->refresh()->delete();
            $this->fail('A signed sheet\'s photo was deleted.');
        } catch (CheckInSignedException) {
            $this->assertTrue(JobAttachment::query()->whereKey($photo->getKey())->exists());
        }

        $this->expectException(CheckInSignedException::class);

        JobAttachment::factory()->checkIn()->forJob($job)->create();
    }

    public function test_a_check_in_photo_of_an_unsigned_sheet_can_be_deleted(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create();
        $photo = JobAttachment::factory()->checkIn()->forJob($job)->create();

        $photo->delete();

        $this->assertFalse(JobAttachment::query()->whereKey($photo->getKey())->exists());
    }

    /**
     * The guard is scoped: a general photo on the same job, signed sheet and
     * locked job both, never runs the signed_at query at all.
     */
    public function test_a_general_photo_on_a_signed_and_locked_job_saves_and_deletes_without_the_query(): void
    {
        $job = Job::factory()->locked()->create();
        JobCheckIn::factory()->forJob($job)->signedOnPaper()->create();

        DB::enableQueryLog();
        $photo = JobAttachment::factory()->photo()->forJob($job)->create();
        $photo->delete();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertFalse(JobAttachment::query()->whereKey($photo->getKey())->exists());
        $this->assertSame([], array_values(array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'job_check_ins'),
        )), implode("\n", $queries));
    }
}
