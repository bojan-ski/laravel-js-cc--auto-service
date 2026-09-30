<?php

namespace Tests\Feature\CheckIns;

use App\Enums\AttachmentCategory;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobCheckIn;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CheckInSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_table_has_exactly_the_specified_columns(): void
    {
        $this->assertEqualsCanonicalizing([
            'id', 'job_id', 'fuel_level', 'damage_notes', 'valuables', 'signed_by_name',
            'signature_method', 'signature_path', 'signed_at', 'recorded_by',
            'created_at', 'updated_at',
        ], Schema::getColumnListing('job_check_ins'));
    }

    public function test_a_job_has_at_most_one_sheet(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create();

        $this->expectException(QueryException::class);

        JobCheckIn::factory()->forJob($job)->create();
    }

    public function test_the_indexes_cover_the_unique_job_and_both_enum_columns(): void
    {
        $indexes = collect(Schema::getIndexes('job_check_ins'))->keyBy(fn (array $index): string => implode(',', $index['columns']));

        $this->assertTrue($indexes['job_id']['unique']);
        $this->assertArrayHasKey('fuel_level', $indexes->all());
        $this->assertArrayHasKey('signature_method', $indexes->all());
        $this->assertArrayHasKey('recorded_by', $indexes->all());
    }

    public function test_the_foreign_keys_cascade_from_the_job_and_restrict_the_recorder(): void
    {
        $keys = collect(Schema::getForeignKeys('job_check_ins'))->keyBy(fn (array $key): string => implode(',', $key['columns']));

        $this->assertSame('jobs', $keys['job_id']['foreign_table']);
        $this->assertSame('cascade', strtolower($keys['job_id']['on_delete']));
        $this->assertSame('users', $keys['recorded_by']['foreign_table']);
        $this->assertSame('restrict', strtolower($keys['recorded_by']['on_delete']));
    }

    public function test_deleting_the_job_row_outright_takes_its_sheet_with_it(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobCheckIn::factory()->forJob($job)->create();

        DB::table('jobs')->where('id', $job->getKey())->delete();

        $this->assertSame(0, JobCheckIn::query()->count());
    }

    public function test_a_recorder_with_a_sheet_cannot_be_removed_outright(): void
    {
        $recorder = User::factory()->mechanic()->create();
        JobCheckIn::factory()->recordedBy($recorder)->create();

        $this->expectException(QueryException::class);

        $recorder->forceDelete();
    }

    public function test_attachment_category_defaults_to_general_and_is_indexed(): void
    {
        $column = collect(Schema::getColumns('job_attachments'))->firstWhere('name', 'category');
        $indexed = collect(Schema::getIndexes('job_attachments'))->contains(fn (array $index): bool => $index['columns'] === ['category']);

        $this->assertNotNull($column);
        $this->assertStringContainsString('general', (string) $column['default']);
        $this->assertTrue($indexed);
    }

    public function test_a_row_inserted_without_a_category_reads_as_general(): void
    {
        $job = Job::factory()->inProgress()->create();
        $uploader = User::factory()->mechanic()->create();

        $id = DB::table('job_attachments')->insertGetId([
            'job_id' => $job->getKey(),
            'type' => 'photo',
            'path' => 'job-attachments/1/legacy.jpg',
            'original_filename' => 'legacy.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'uploaded_by' => $uploader->getKey(),
        ]);

        $this->assertSame(AttachmentCategory::General, JobAttachment::query()->findOrFail($id)->category);
    }
}
