<?php

namespace Tests\Feature\Parts;

use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PartCataloguePerformanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function roleProvider(): array
    {
        return [
            'mechanic' => ['mechanic'],
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('roleProvider')]
    public function test_a_full_page_issues_a_bounded_number_of_queries(string $role): void
    {
        Part::factory()->count(20)->inStock()->create();
        Part::factory()->count(6)->lowStock()->create();

        $user = User::factory()->{$role}()->create();

        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('parts.index'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk()->assertViewHas('parts', fn ($page): bool => $page->count() === 25);

        $this->assertCount(5, $queries, implode("\n", array_column($queries, 'query')));
    }

    public function test_the_row_count_does_not_change_the_query_count(): void
    {
        Part::factory()->count(2)->create();

        $user = User::factory()->admin()->create();

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('parts.index'))->assertOk();
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();

        Part::factory()->count(23)->create();
        DB::flushQueryLog();

        $this->actingAs($user)->get(route('parts.index'))->assertOk();
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($small, $large, 'The catalogue issues a query per row.');
    }
}
