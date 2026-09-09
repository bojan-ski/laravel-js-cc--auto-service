<?php

namespace Tests\Feature\Quality;

use App\Models\Job;
use App\Models\Part;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\QaFixture;
use Tests\Support\WalksPages;
use Tests\TestCase;

/**
 * No page lazy-loads a relation or reads an attribute its query did not
 * select. Both guards are on outside production, and each spec proved them
 * silent within its own scope; this renders every page over the realistic
 * fixture with the collectors installed, so one run lists every violation.
 */
class LazyLoadingSweepTest extends TestCase
{
    use LazilyRefreshDatabase;
    use WalksPages;

    #[DataProvider('roles')]
    public function test_no_page_lazy_loads_or_reads_a_missing_attribute(string $role, int $floor): void
    {
        $shop = QaFixture::build();
        $this->installViolationCollectors();

        $walked = $this->walk($shop, $this->actorFor($shop, $role), $role, function (string $label, array $entry, TestResponse $response): void {
            $this->assertPageRendered($response, $label);
        });

        $this->assertNoViolations("The {$role} walk");
        $this->assertGreaterThanOrEqual($floor, $walked, "The {$role} walk rendered only {$walked} pages; the inventory has shrunk.");
    }

    /**
     * The collectors replace the exceptions, so an empty list must be proven
     * to mean "no violation" rather than "nothing was listening".
     *
     * The lazy probe reads from a two-row result on purpose: Builder::hydrate()
     * marks a model for lazy-loading prevention only when the query returned
     * more than one row, so a findOrFail() result never trips the guard.
     */
    public function test_the_collectors_are_live(): void
    {
        Job::factory()->count(2)->received()->create();
        $partId = Part::factory()->create()->getKey();

        $this->installViolationCollectors();

        Job::query()->orderBy('id')->get()->first()->customer;
        Part::query()->select('id')->findOrFail($partId)->unit_price;

        $this->assertSame([['page' => '(no page)', 'model' => Job::class, 'relation' => 'customer']], $this->lazyLoads);
        $this->assertSame([['page' => '(no page)', 'model' => Part::class, 'attribute' => 'unit_price']], $this->missingAttributes);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function roles(): array
    {
        return [
            'mechanic' => ['mechanic', 50],
            'receptionist' => ['receptionist', 70],
            'admin' => ['admin', 90],
        ];
    }
}
