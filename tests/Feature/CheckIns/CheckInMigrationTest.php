<?php

namespace Tests\Feature\CheckIns;

use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * DatabaseMigrations, not LazilyRefreshDatabase: MySQL DDL commits implicitly,
 * so running down() inside a test transaction would leak the rolled-back shape
 * into every later test in the process (InvoiceBuyerBackfillTest's reason).
 */
class CheckInMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private function migration(string $name): Migration
    {
        $paths = glob(database_path("migrations/*_{$name}.php")) ?: [];

        if (count($paths) !== 1) {
            throw new RuntimeException("Expected exactly one {$name} migration.");
        }

        return require $paths[0];
    }

    public function test_rolling_back_the_category_drops_it_and_existing_rows_take_general_on_the_way_up(): void
    {
        $category = $this->migration('add_category_to_job_attachments_table');
        $category->down();

        $this->assertFalse(Schema::hasColumn('job_attachments', 'category'));
        $this->assertFalse(collect(Schema::getIndexes('job_attachments'))->contains(fn (array $index): bool => $index['columns'] === ['category']));

        $job = Job::factory()->inProgress()->create();
        $uploader = User::factory()->mechanic()->create();
        DB::table('job_attachments')->insert([
            'job_id' => $job->getKey(),
            'type' => 'photo',
            'path' => 'job-attachments/1/existing.jpg',
            'original_filename' => 'existing.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'uploaded_by' => $uploader->getKey(),
        ]);

        $category->up();

        $this->assertSame(['general'], DB::table('job_attachments')->pluck('category')->all());
    }

    public function test_rolling_back_the_sheet_table_drops_it_and_up_restores_it(): void
    {
        $sheets = $this->migration('create_job_check_ins_table');

        $sheets->down();
        $this->assertFalse(Schema::hasTable('job_check_ins'));

        $sheets->up();
        $this->assertTrue(Schema::hasTable('job_check_ins'));
    }
}
