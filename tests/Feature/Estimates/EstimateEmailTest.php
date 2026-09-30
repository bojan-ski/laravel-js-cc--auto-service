<?php

namespace Tests\Feature\Estimates;

use App\Enums\Locale;
use App\Exceptions\InvalidEstimateTransition;
use App\Mail\EstimateMail;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CustomerMailer;
use App\Services\EstimateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsMailRows;
use Tests\TestCase;

class EstimateEmailTest extends TestCase
{
    use AssertsMailRows;
    use LazilyRefreshDatabase;

    private Customer $customer;

    private Vehicle $vehicle;

    private function service(): EstimateService
    {
        return app(EstimateService::class);
    }

    private function estimate(?string $email = 'milan@example.com', bool $discounted = false): Estimate
    {
        $this->customer = Customer::factory()->create(['name' => 'Milan Petrovic', 'email' => $email]);
        $this->vehicle = Vehicle::factory()->for($this->customer)->create([
            'registration_plate' => 'BG123-ZZ',
            'make' => 'VW',
            'model' => 'Golf IV',
        ]);

        $job = Job::factory()->inProgress()->for($this->customer)->for($this->vehicle)->create();
        JobItem::factory()->forJob($job)->create(['description' => 'Brake service', 'quantity' => '1.50', 'unit_price' => '4000.00']);

        if ($discounted) {
            JobItem::factory()->forJob($job)->discounted('10.00')->create(['description' => 'Diagnostics', 'quantity' => '2.00', 'unit_price' => '1000.00']);
        }

        return $this->service()->create($job, User::factory()->receptionist()->create());
    }

    public function test_emailing_queues_one_message_to_the_customer_in_the_shop_language_and_stamps_the_time(): void
    {
        Mail::fake();
        config(['shop.locale' => 'sr']);
        $estimate = $this->estimate();
        $this->freezeSecond();

        $this->assertTrue($this->service()->email($estimate));

        Mail::assertQueuedCount(1);
        Mail::assertQueued(EstimateMail::class, fn (EstimateMail $mail): bool => $mail->hasTo('milan@example.com')
            && $mail->locale === Locale::shop()->value
            && $mail->estimate->is($estimate));
        $this->assertEquals(now(), $estimate->fresh()->emailed_at);
    }

    public function test_emailing_twice_queues_two_and_records_the_last_send(): void
    {
        Mail::fake();
        $estimate = $this->estimate();

        $this->travelTo(now()->subHour());
        $this->service()->email($estimate);
        $first = $estimate->fresh()->emailed_at;

        $this->travelBack();
        $this->freezeSecond();
        $this->service()->email($estimate);

        Mail::assertQueuedCount(2);
        $this->assertTrue($estimate->fresh()->emailed_at->greaterThan($first));
        $this->assertEquals(now(), $estimate->fresh()->emailed_at);
    }

    public function test_an_approved_estimate_can_be_emailed(): void
    {
        Mail::fake();
        $estimate = $this->estimate();
        DB::table('estimates')->where('id', $estimate->getKey())->update(['status' => 'approved']);

        $this->assertTrue($this->service()->email($estimate->fresh()));

        Mail::assertQueuedCount(1);
    }

    public function test_a_customer_without_email_gets_nothing_and_the_time_stays_empty(): void
    {
        Mail::fake();
        $estimate = $this->estimate(email: null);

        $this->assertFalse($this->service()->email($estimate));

        Mail::assertNothingOutgoing();
        $this->assertNull($estimate->fresh()->emailed_at);
    }

    public function test_a_soft_deleted_customer_with_an_email_is_still_emailed(): void
    {
        Mail::fake();
        $estimate = $this->estimate();
        $this->customer->delete();

        $this->assertTrue($this->service()->email($estimate));

        Mail::assertQueued(EstimateMail::class, fn (EstimateMail $mail): bool => $mail->hasTo('milan@example.com'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function closedStates(): array
    {
        return ['declined' => ['declined'], 'superseded' => ['superseded']];
    }

    #[DataProvider('closedStates')]
    public function test_a_declined_or_superseded_estimate_is_refused(string $state): void
    {
        Mail::fake();
        $estimate = $this->estimate();
        DB::table('estimates')->where('id', $estimate->getKey())->update(['status' => $state]);

        try {
            $this->service()->email($estimate);
            $this->fail('Expected InvalidEstimateTransition.');
        } catch (InvalidEstimateTransition) {
            Mail::assertNothingOutgoing();
            $this->assertNull($estimate->fresh()->emailed_at);
        }
    }

    public function test_the_estimate_row_is_locked_before_the_send_and_the_stamp(): void
    {
        Mail::fake();
        $estimate = $this->estimate();

        DB::enableQueryLog();
        $this->service()->email($estimate);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $lock = array_key_first(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from `estimates`') && str_contains($sql, 'for update')));
        $write = array_key_first(array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'update `estimates`')));

        $this->assertNotNull($lock, "No locking read:\n".implode("\n", $queries));
        $this->assertLessThan($write, $lock);
    }

    /**
     * No fake: Mail::fake() and Queue::fake() record immediately and never
     * consult db.transactions, so under either one this property is invisible.
     * The discriminating assertion is the queue row being absent while the
     * surrounding transaction is still open (see MailAfterCommitTest).
     */
    public function test_the_queued_mail_is_not_written_until_the_surrounding_transaction_commits(): void
    {
        config(['queue.default' => 'database']);
        $estimate = $this->estimate();

        DB::transaction(function () use ($estimate): void {
            $this->service()->email($estimate);

            $this->assertSame(0, DB::table('queue_jobs')->count(), 'The send was not held until commit.');
        });

        $this->assertSame(1, DB::table('queue_jobs')->count());
    }

    public function test_the_mailable_has_the_queued_shape(): void
    {
        $mailable = new EstimateMail($this->estimate(), $this->customer, $this->vehicle);

        $this->assertTrue($mailable->afterCommit);
        $this->assertInstanceOf(ShouldQueue::class, $mailable);
        $this->assertNotContains(Queueable::class, class_uses_recursive($mailable));

        $job = new SendQueuedMailable($mailable);
        $this->assertSame(3, $job->tries);
        $this->assertSame(30, $job->timeout);
        $this->assertSame(60, $job->backoff());
        $this->assertTrue($job->afterCommit);
    }

    public function test_the_subject_names_the_number_and_the_plate(): void
    {
        $estimate = $this->estimate();

        (new EstimateMail($estimate, $this->customer, $this->vehicle))
            ->assertHasSubject(__('mail.estimate.subject', ['number' => $estimate->number, 'plate' => 'BG123-ZZ']));
    }

    public function test_both_bodies_carry_the_lines_and_the_stored_totals(): void
    {
        $estimate = $this->estimate();
        $mailable = new EstimateMail($estimate, $this->customer, $this->vehicle);

        $this->assertSame('6000.00', $estimate->subtotal);
        $this->assertSame('1200.00', $estimate->tax_amount);
        $this->assertSame('7200.00', $estimate->total);

        $line = __('mail.estimate.line', ['description' => 'Brake service', 'quantity' => '1.5']);
        $this->assertHtmlRow($mailable, $line, '6,000.00');
        $this->assertHtmlRow($mailable, __('mail.estimate.number'), $estimate->number);
        $this->assertHtmlRow($mailable, __('mail.totals.subtotal'), '6,000.00');
        $this->assertHtmlRow($mailable, __('mail.totals.vat', ['rate' => '20']), '1,200.00');
        $this->assertHtmlRow($mailable, __('mail.totals.total'), '7,200.00');
        $this->assertNoHtmlRowLabelled($mailable, __('mail.totals.discount'));

        $mailable->assertSeeInText(__('mail.pair', ['label' => $line, 'value' => '6,000.00']), false);
        $mailable->assertSeeInText(__('mail.pair', ['label' => __('mail.totals.total'), 'value' => '7,200.00']), false);
        $mailable->assertSeeInText(__('mail.estimate.greeting', ['name' => 'Milan Petrovic']), false);
        $mailable->assertSeeInText(__('mail.estimate.reply'), false);
        $mailable->assertSeeInHtml(__('mail.estimate.reply'), false);
        $mailable->assertDontSeeInText('1.50');
    }

    public function test_the_mail_reads_the_stored_totals_not_a_recomputation(): void
    {
        $estimate = $this->estimate();
        DB::table('estimates')->where('id', $estimate->getKey())->update(['total' => '7777.77']);

        $mailable = new EstimateMail($estimate->fresh(), $this->customer, $this->vehicle);

        $this->assertHtmlRow($mailable, __('mail.totals.total'), '7,777.77');
    }

    public function test_a_discounted_estimate_shows_the_percentage_per_line_and_the_discount_rows(): void
    {
        $estimate = $this->estimate(discounted: true);
        $mailable = new EstimateMail($estimate, $this->customer, $this->vehicle);

        $this->assertSame('200.00', $estimate->discount_total);

        $line = __('mail.estimate.line_discounted', ['description' => 'Diagnostics', 'quantity' => '2', 'percent' => '10']);
        $this->assertHtmlRow($mailable, $line, '1,800.00');
        $this->assertHtmlRow($mailable, __('mail.totals.before_discount'), '8,000.00');
        $this->assertHtmlRow($mailable, __('mail.totals.discount'), '200.00');
        $this->assertHtmlRow($mailable, __('mail.totals.subtotal'), '7,800.00');
        $mailable->assertSeeInText(__('mail.pair', ['label' => __('mail.totals.discount'), 'value' => '200.00']), false);
    }

    public function test_the_message_carries_no_attachment(): void
    {
        $mailable = new EstimateMail($this->estimate(), $this->customer, $this->vehicle);
        $mailable->render();

        $this->assertSame([], $mailable->attachments);
        $this->assertSame([], $mailable->rawAttachments);
        $this->assertSame([], $mailable->diskAttachments);
    }

    public function test_the_mailer_reports_whether_it_queued(): void
    {
        Mail::fake();
        $estimate = $this->estimate();
        $mailer = app(CustomerMailer::class);

        $this->assertTrue($mailer->send($this->customer, new EstimateMail($estimate, $this->customer, $this->vehicle)));

        $this->customer->email = null;
        $this->assertFalse($mailer->send($this->customer, new EstimateMail($estimate, $this->customer, $this->vehicle)));

        Mail::assertQueuedCount(1);
    }
}
