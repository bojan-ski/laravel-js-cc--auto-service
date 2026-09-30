<?php

namespace Tests\Feature\Estimates;

use App\Enums\DecisionMethod;
use App\Enums\EstimateStatus;
use App\Exceptions\EstimateNotCreatable;
use App\Exceptions\InvalidEstimateTransition;
use App\Mail\EstimateMail;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CustomerMailer;
use App\Services\EstimateNumberGenerator;
use App\Services\EstimateService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EstimateRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function desk(): User
    {
        return User::factory()->receptionist()->create();
    }

    private function workingJob(): Job
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create();

        return $job;
    }

    /**
     * @return array<string, mixed>
     */
    private function decision(array $overrides = []): array
    {
        return ['decision' => 'approved', 'decision_method' => 'phone', 'decision_note' => null, ...$overrides];
    }

    public function test_front_office_creates_an_estimate_and_lands_on_it(): void
    {
        $job = $this->workingJob();

        $response = $this->actingAs($this->desk())->post(route('jobs.estimates.store', $job));

        $estimate = Estimate::query()->sole();
        $response->assertRedirect(route('estimates.show', $estimate))
            ->assertSessionHas('success', __('estimates.flash.created', ['number' => $estimate->number]));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unestimableJobs(): array
    {
        return ['completed' => ['completed'], 'locked' => ['locked'], 'cancelled' => ['cancelled'], 'lineless' => ['lineless']];
    }

    #[DataProvider('unestimableJobs')]
    public function test_front_office_is_refused_on_a_job_that_cannot_be_estimated(string $state): void
    {
        $job = $state === 'lineless' ? Job::factory()->inProgress()->create() : Job::factory()->{$state}()->create();

        $this->actingAs($this->desk())->post(route('jobs.estimates.store', $job))->assertForbidden();

        $this->assertSame(0, Estimate::query()->count());
    }

    public function test_a_lost_race_on_create_redirects_to_the_job_with_an_error(): void
    {
        $job = $this->workingJob();
        $this->app->instance(EstimateService::class, new class(app(InvoiceService::class), app(EstimateNumberGenerator::class), app(CustomerMailer::class)) extends EstimateService
        {
            public function create(Job $job, User $createdBy): Estimate
            {
                throw new EstimateNotCreatable($job, EstimateNotCreatable::NOT_WORKING);
            }
        });

        $this->actingAs($this->desk())
            ->post(route('jobs.estimates.store', $job))
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('error', __('estimates.flash.not_creatable'));
    }

    public function test_front_office_sees_the_estimate_page(): void
    {
        $estimate = Estimate::factory()->create();

        $this->actingAs($this->desk())
            ->get(route('estimates.show', $estimate))
            ->assertOk()
            ->assertViewIs('pages.estimates.show')
            ->assertViewHas('estimate', fn (Estimate $shown): bool => $shown->is($estimate))
            ->assertSee($estimate->number);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function decisions(): array
    {
        return ['approved' => ['approved'], 'declined' => ['declined']];
    }

    #[DataProvider('decisions')]
    public function test_front_office_records_a_decision(string $decision): void
    {
        $estimate = Estimate::factory()->create();

        $this->actingAs($this->desk())
            ->patch(route('estimates.decision.update', $estimate), $this->decision(['decision' => $decision, 'decision_method' => 'in_person', 'decision_note' => 'At the counter.']))
            ->assertRedirect(route('estimates.show', $estimate))
            ->assertSessionHas('success', __("estimates.flash.{$decision}", ['number' => $estimate->number]));

        $fresh = $estimate->fresh();
        $this->assertSame(EstimateStatus::from($decision), $fresh->status);
        $this->assertSame(DecisionMethod::InPerson, $fresh->decision_method);
        $this->assertSame('At the counter.', $fresh->decision_note);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidDecisions(): array
    {
        return [
            'missing decision' => [['decision' => null], 'decision'],
            'unknown decision' => [['decision' => 'superseded'], 'decision'],
            'missing method' => [['decision_method' => null], 'decision_method'],
            'unknown method' => [['decision_method' => 'carrier_pigeon'], 'decision_method'],
            'note over 500' => [['decision_note' => str_repeat('a', 501)], 'decision_note'],
        ];
    }

    #[DataProvider('invalidDecisions')]
    public function test_an_invalid_decision_is_refused_and_changes_nothing(array $overrides, string $field): void
    {
        $estimate = Estimate::factory()->create();
        $show = route('estimates.show', $estimate);

        $this->actingAs($this->desk())
            ->from($show)
            ->patch(route('estimates.decision.update', $estimate), $this->decision($overrides))
            ->assertRedirect($show)
            ->assertSessionHasErrors($field);

        $this->assertTrue($estimate->fresh()->isPending());
    }

    public function test_a_note_of_exactly_500_characters_is_accepted(): void
    {
        $estimate = Estimate::factory()->create();

        $this->actingAs($this->desk())
            ->patch(route('estimates.decision.update', $estimate), $this->decision(['decision_note' => str_repeat('a', 500)]))
            ->assertSessionHasNoErrors();

        $this->assertSame(500, strlen($estimate->fresh()->decision_note));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function decidedStates(): array
    {
        return ['approved' => ['approved'], 'declined' => ['declined'], 'superseded' => ['superseded']];
    }

    #[DataProvider('decidedStates')]
    public function test_a_decision_on_a_decided_estimate_is_forbidden(string $state): void
    {
        $estimate = Estimate::factory()->{$state}()->create();

        $this->actingAs($this->desk())
            ->patch(route('estimates.decision.update', $estimate), $this->decision())
            ->assertForbidden();
    }

    public function test_a_lost_race_on_decide_redirects_to_the_estimate_with_an_error(): void
    {
        $estimate = Estimate::factory()->create();
        $this->app->instance(EstimateService::class, new class(app(InvoiceService::class), app(EstimateNumberGenerator::class), app(CustomerMailer::class)) extends EstimateService
        {
            public function decide(Estimate $estimate, EstimateStatus $decision, DecisionMethod $method, ?string $note, User $decidedBy): Estimate
            {
                throw new InvalidEstimateTransition($estimate, [], 'raced');
            }
        });

        $this->actingAs($this->desk())
            ->patch(route('estimates.decision.update', $estimate), $this->decision())
            ->assertRedirect(route('estimates.show', $estimate))
            ->assertSessionHas('error', __('estimates.flash.not_decidable'));
    }

    public function test_front_office_emails_an_estimate(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => 'milan@example.com']);
        $job = Job::factory()->inProgress()->for($customer)->for(Vehicle::factory()->for($customer))->create();
        $estimate = Estimate::factory()->forJob($job)->create();

        $this->actingAs($this->desk())
            ->post(route('estimates.email.store', $estimate))
            ->assertRedirect(route('estimates.show', $estimate))
            ->assertSessionHas('success', __('estimates.flash.emailed', ['number' => $estimate->number, 'email' => 'milan@example.com']));

        Mail::assertQueued(EstimateMail::class);
        $this->assertNotNull($estimate->fresh()->emailed_at);
    }

    public function test_an_address_removed_before_the_click_queues_nothing_and_warns(): void
    {
        Mail::fake();
        $customer = Customer::factory()->create(['email' => null]);
        $job = Job::factory()->inProgress()->for($customer)->for(Vehicle::factory()->for($customer))->create();
        $estimate = Estimate::factory()->forJob($job)->create();

        $this->actingAs($this->desk())
            ->post(route('estimates.email.store', $estimate))
            ->assertRedirect(route('estimates.show', $estimate))
            ->assertSessionHas('warning', __('estimates.flash.no_email'));

        Mail::assertNothingOutgoing();
        $this->assertNull($estimate->fresh()->emailed_at);
    }

    #[DataProvider('decidedStates')]
    public function test_emailing_follows_the_estimate_state(string $state): void
    {
        Mail::fake();
        $estimate = Estimate::factory()->{$state}()->create();

        $response = $this->actingAs($this->desk())->post(route('estimates.email.store', $estimate));

        $state === 'approved' ? $response->assertRedirect() : $response->assertForbidden();
    }

    public function test_a_lost_race_on_email_redirects_to_the_estimate_with_an_error(): void
    {
        Mail::fake();
        $estimate = Estimate::factory()->create();
        $this->app->instance(EstimateService::class, new class(app(InvoiceService::class), app(EstimateNumberGenerator::class), app(CustomerMailer::class)) extends EstimateService
        {
            public function email(Estimate $estimate): bool
            {
                throw new InvalidEstimateTransition($estimate, [], 'raced');
            }
        });

        $this->actingAs($this->desk())
            ->post(route('estimates.email.store', $estimate))
            ->assertRedirect(route('estimates.show', $estimate))
            ->assertSessionHas('error', __('estimates.flash.not_emailable'));
    }

    public function test_a_mechanic_is_forbidden_on_all_four_routes_even_for_an_unknown_id(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $estimate = Estimate::factory()->create();
        $job = $this->workingJob();

        $this->actingAs($mechanic);

        $this->post(route('jobs.estimates.store', $job))->assertForbidden();

        foreach ([$estimate->getKey(), 999999] as $id) {
            $this->get(route('estimates.show', $id))->assertForbidden();
            $this->patch(route('estimates.decision.update', $id), $this->decision())->assertForbidden();
            $this->post(route('estimates.email.store', $id))->assertForbidden();
        }

        $this->assertSame(1, Estimate::query()->count());
        $this->assertTrue($estimate->fresh()->isPending());
    }

    public function test_a_mechanic_is_forbidden_before_validation_so_an_invalid_decision_reveals_no_field_errors(): void
    {
        $estimate = Estimate::factory()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('estimates.decision.update', $estimate), [])
            ->assertForbidden()
            ->assertSessionHasNoErrors();

        $this->assertTrue($estimate->fresh()->isPending());
    }

    public function test_front_office_gets_not_found_for_an_unknown_estimate(): void
    {
        $this->actingAs($this->desk());

        $this->get(route('estimates.show', 999999))->assertNotFound();
        $this->patch(route('estimates.decision.update', 999999), $this->decision())->assertNotFound();
        $this->post(route('estimates.email.store', 999999))->assertNotFound();
    }

    public function test_a_non_numeric_id_is_not_found_rather_than_a_server_error(): void
    {
        $this->actingAs($this->desk())->get('/estimates/abc')->assertNotFound();
    }

    public function test_a_guest_is_sent_to_login_from_every_route(): void
    {
        $estimate = Estimate::factory()->create();
        $job = $this->workingJob();

        $this->post(route('jobs.estimates.store', $job))->assertRedirect(route('login'));
        $this->get(route('estimates.show', $estimate))->assertRedirect(route('login'));
        $this->patch(route('estimates.decision.update', $estimate), $this->decision())->assertRedirect(route('login'));
        $this->post(route('estimates.email.store', $estimate))->assertRedirect(route('login'));
    }
}
