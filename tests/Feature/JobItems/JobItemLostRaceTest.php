<?php

namespace Tests\Feature\JobItems;

use App\Exceptions\JobLockedException;
use App\Exceptions\JobNotAcceptingLineItems;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\JobTemplate;
use App\Models\JobTemplateItem;
use App\Models\Part;
use App\Models\User;
use App\Services\JobItemService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

/**
 * Every line write is authorized against the job as the page saw it, then the
 * service re-checks under the job-row lock. If the job was invoiced or left a
 * working state in between, the service refuses with JobLockedException or
 * JobNotAcceptingLineItems; the controller must turn that into the job page
 * with an error flash, not a 500 (spec 36 review: specs 13, 16, 30, 31).
 *
 * The window cannot be opened in a sequential test, because the policy
 * refuses a locked or closed job first, so the service is replaced by one that
 * loses the race — the pattern EstimateRouteTest uses for its own lost races.
 */
class JobItemLostRaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, class-string<Throwable>}>
     */
    public static function writes(): array
    {
        $cells = [];

        foreach (['add labor', 'add part', 'edit line', 'delete line', 'set discount', 'apply template'] as $write) {
            $cells["{$write}, locked"] = [$write, JobLockedException::class];
            $cells["{$write}, closed"] = [$write, JobNotAcceptingLineItems::class];
        }

        return $cells;
    }

    /**
     * @param  class-string<Throwable>  $exception
     */
    #[DataProvider('writes')]
    public function test_a_lost_race_redirects_to_the_job_with_an_error(string $write, string $exception): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $line = JobItem::factory()->forJob($job)->performedBy($mechanic)->create();
        $this->bindLosingService($job, $exception);

        [$actor, $method, $url, $payload] = match ($write) {
            'add labor' => [$mechanic, 'post', route('jobs.items.store', $job), ['description' => 'Brake service', 'quantity' => '1.50']],
            'add part' => [$mechanic, 'post', route('jobs.parts.store', $job), ['part_id' => Part::factory()->create()->getKey(), 'quantity' => '2']],
            'edit line' => [$mechanic, 'put', route('job-items.update', $line), ['description' => 'Brake service', 'quantity' => '2.00']],
            'delete line' => [$mechanic, 'delete', route('job-items.destroy', $line), []],
            'set discount' => [User::factory()->receptionist()->create(), 'patch', route('job-items.discount.update', $line), ['discount_percent' => '10']],
            'apply template' => [$mechanic, 'post', route('jobs.templates.apply', $job), ['job_template_id' => $this->template()->getKey()]],
        };

        $this->actingAs($actor)
            ->{$method}($url, $payload)
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('error', __('jobs.items.flash.not_accepting'))
            ->assertSessionMissing('success');
    }

    /**
     * @param  class-string<Throwable>  $exception
     */
    private function bindLosingService(Job $job, string $exception): void
    {
        $this->app->instance(JobItemService::class, new class(app(StockService::class), $job, $exception) extends JobItemService
        {
            /**
             * @param  class-string<Throwable>  $exception
             */
            public function __construct(StockService $stock, private Job $job, private string $exception)
            {
                parent::__construct($stock);
            }

            public function addLabor(Job $job, User $actor, array $attributes): JobItem
            {
                $this->lose();
            }

            public function addPart(Job $job, Part $part, User $actor, array $attributes): JobItem
            {
                $this->lose();
            }

            public function applyTemplate(Job $job, JobTemplate $template, User $actor, ?int $mechanicId): Collection
            {
                $this->lose();
            }

            public function update(JobItem $item, User $actor, array $attributes): JobItem
            {
                $this->lose();
            }

            public function delete(JobItem $item, User $actor): void
            {
                $this->lose();
            }

            public function setDiscount(JobItem $item, string $percent): JobItem
            {
                $this->lose();
            }

            private function lose(): never
            {
                throw $this->exception === JobLockedException::class
                    ? new JobLockedException($this->job, [])
                    : new JobNotAcceptingLineItems($this->job);
            }
        });
    }

    private function template(): JobTemplate
    {
        $template = JobTemplate::factory()->create();
        JobTemplateItem::factory()->forTemplate($template)->labor()->create(['position' => 1]);

        return $template;
    }
}
