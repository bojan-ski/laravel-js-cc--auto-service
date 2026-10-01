<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\EstimateStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReminderKind;
use App\Enums\SignatureMethod;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobCheckIn;
use App\Models\JobItem;
use App\Models\JobTemplate;
use App\Models\Part;
use App\Models\PartNumber;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use App\Services\InvoiceService;
use App\Services\ReminderService;
use App\Services\TaxIdChecksum;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Demo\DemoSeeder;
use Database\Seeders\Demo\JobTemplateSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\AssertsStockInvariant;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use AssertsStockInvariant;
    use LazilyRefreshDatabase;

    /** @var array<string, string|null> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('attachments.disk'));

        foreach (['ADMIN_EMAIL', 'ADMIN_PASSWORD'] as $key) {
            $value = Env::getRepository()->get($key);
            $this->originalEnv[$key] = is_string($value) ? $value : null;
        }

        Env::getRepository()->set('ADMIN_EMAIL', 'admin@auto-service.test');
        Env::getRepository()->set('ADMIN_PASSWORD', 'seeded-admin-password');
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            $value === null
                ? Env::getRepository()->clear($key)
                : Env::getRepository()->set($key, $value);
        }

        parent::tearDown();
    }

    private function enterProduction(): void
    {
        $this->assertDatabaseEmpty('users');

        $this->app->detectEnvironment(fn (): string => 'production');
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $this->enterProduction();

        try {
            $this->artisan('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true])->run();
            $this->fail('The demo seeder ran in production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('production', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_the_database_seeder_seeds_only_the_administrator_in_production(): void
    {
        $this->enterProduction();

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('parts', 0);
    }

    public function test_the_database_seeder_adds_demo_data_outside_production(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::query()->where('email', 'admin@auto-service.test')->exists());
        $this->assertTrue(User::withTrashed()->where('email', DemoSeeder::emailFor('milica'))->exists());
        $this->assertGreaterThan(0, Job::query()->count());
    }

    public function test_every_role_has_a_demo_account_that_signs_in_and_one_mechanic_is_offboarded(): void
    {
        $this->seed(DemoDataSeeder::class);

        foreach (UserRole::cases() as $role) {
            $user = User::query()->where('role', $role)->firstOrFail();

            $this->assertTrue(Hash::check(DemoSeeder::PASSWORD, $user->password));
        }

        $offboarded = User::onlyTrashed()->sole();

        $this->assertSame(DemoSeeder::emailFor('zoran'), $offboarded->email);
        $this->assertTrue(DB::table('job_items')->where('user_id', $offboarded->getKey())->exists());
    }

    public function test_every_lifecycle_state_is_represented(): void
    {
        $this->seed(DemoDataSeeder::class);

        foreach (JobStatus::cases() as $status) {
            $this->assertTrue(Job::query()->where('status', $status)->exists(), "No {$status->value} job.");
        }

        foreach (AppointmentStatus::cases() as $status) {
            $this->assertTrue(Appointment::query()->where('status', $status)->exists(), "No {$status->value} appointment.");
        }

        foreach (InvoiceStatus::cases() as $status) {
            $this->assertTrue(Invoice::query()->where('status', $status)->exists(), "No {$status->value} invoice.");
        }

        $this->assertTrue(Job::query()->where('status', JobStatus::Completed)->whereNull('locked_at')->exists());
        $this->assertTrue(Invoice::query()->where('status', InvoiceStatus::Unpaid)->where('due_at', '<', now())->exists());
        $this->assertTrue(Appointment::query()->where('status', AppointmentStatus::Booked)->where('scheduled_at', '>', now())->exists());
    }

    public function test_locks_and_invoices_agree(): void
    {
        $this->seed(DemoDataSeeder::class);

        $jobs = Job::query()->withCount([
            'invoices as active_invoices' => fn ($query) => $query->where('status', '!=', InvoiceStatus::Void),
        ])->get();

        foreach ($jobs as $job) {
            $this->assertSame(
                $job->locked_at === null ? 0 : 1,
                $job->active_invoices,
                "Job {$job->reference} lock state disagrees with its invoices.",
            );
        }

        $this->assertTrue(Job::query()->has('invoices', '>=', 2)->exists(), 'No job was re-invoiced after a void.');
    }

    public function test_every_paid_invoice_records_a_method_and_all_three_occur(): void
    {
        $this->seed(DemoDataSeeder::class);

        $paid = Invoice::query()->whereNotNull('paid_at')->get();

        $this->assertNotEmpty($paid);
        $this->assertSame(0, $paid->whereNull('payment_method')->count(), 'Every paid invoice must record how it was paid.');
        $this->assertEqualsCanonicalizing(
            PaymentMethod::cases(),
            $paid->pluck('payment_method')->unique()->values()->all(),
        );

        $this->assertSame(
            0,
            Invoice::query()->whereNull('paid_at')->whereNotNull('payment_method')->count(),
            'An invoice that was never paid must have no method.',
        );
    }

    public function test_fleet_invoices_are_bank_transfers_and_no_other_invoice_is(): void
    {
        $this->seed(DemoDataSeeder::class);

        $paid = Invoice::query()->whereNotNull('paid_at')->get();
        [$fleet, $private] = $paid->partition(fn (Invoice $invoice): bool => $invoice->hasCompanyBuyer());

        $this->assertNotEmpty($fleet);
        $this->assertTrue($fleet->every(fn (Invoice $invoice): bool => $invoice->payment_method === PaymentMethod::BankTransfer));
        $this->assertTrue($private->every(fn (Invoice $invoice): bool => $invoice->payment_method !== PaymentMethod::BankTransfer));
    }

    public function test_the_fleet_customer_is_a_company_and_its_invoices_carry_its_pib(): void
    {
        $this->seed(DemoDataSeeder::class);

        $fleet = Customer::query()->where('name', 'Kurirska služba Brzi d.o.o.')->sole();

        $this->assertTrue($fleet->isCompany());
        $this->assertTrue(TaxIdChecksum::isValid($fleet->tax_id));
        $this->assertSame(1, Customer::query()->whereNotNull('tax_id')->count(), 'Every other demo customer is private.');

        $companyInvoices = Invoice::query()->whereNotNull('buyer_tax_id')->get();

        $this->assertNotEmpty($companyInvoices);
        foreach ($companyInvoices as $invoice) {
            $this->assertSame($fleet->name, $invoice->buyer_name);
            $this->assertSame($fleet->tax_id, $invoice->buyer_tax_id);
            $this->assertSame($fleet->registration_number, $invoice->buyer_registration_number);
        }
    }

    public function test_discounts_appear_on_invoices_and_on_a_completed_uninvoiced_job(): void
    {
        $this->seed(DemoDataSeeder::class);

        $discounted = Invoice::query()->where('discount_total', '>', 0)->get();

        $this->assertNotEmpty($discounted, 'No demo invoice carries a discount.');
        $this->assertTrue(
            $discounted->contains(fn (Invoice $invoice): bool => $invoice->hasCompanyBuyer()),
            'The fleet customer\'s invoices carry no discount.',
        );

        foreach (Invoice::query()->where('status', '!=', InvoiceStatus::Void)->with('job.items')->get() as $invoice) {
            $this->assertSame($invoice->job->subtotal(), $invoice->subtotal, "Invoice {$invoice->number} subtotal disagrees with its lines.");
            $this->assertSame($invoice->job->discountTotal(), $invoice->discount_total, "Invoice {$invoice->number} discount disagrees with its lines.");
        }

        $this->assertTrue(
            JobItem::query()->where('discount_percent', '100.00')->whereHas('job', fn ($query) => $query->whereNotNull('locked_at'))->exists(),
            'No goodwill line on an invoiced job.',
        );

        $this->assertTrue(
            Job::query()
                ->where('status', JobStatus::Completed)
                ->whereNull('locked_at')
                ->whereDoesntHave('invoices')
                ->whereHas('items', fn ($query) => $query->where('discount_percent', '>', 0))
                ->exists(),
            'No completed, uninvoiced job carries a discount.',
        );

        $this->assertStockMatchesLedger();
    }

    public function test_stock_matches_the_ledger_and_the_catalogue_shows_low_and_negative_stock(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertStockMatchesLedger();
        $this->assertTrue(Part::query()->where('quantity_in_stock', '<', 0)->exists());
        $this->assertGreaterThan(0, Part::query()->whereColumn('quantity_in_stock', '<=', 'reorder_level')->count());
        $this->assertSame(1, Part::onlyTrashed()->count());
    }

    public function test_templates_are_seeded_one_on_the_retired_part_and_two_jobs_are_filled_by_an_apply(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertGreaterThanOrEqual(4, JobTemplate::query()->count());

        $retired = Part::onlyTrashed()->where('sku', 'OF-0999')->sole();
        $budget = JobTemplate::query()->where('name', JobTemplateSeeder::BUDGET_SERVICE)->sole();
        $this->assertTrue($budget->items()->where('part_id', $retired->getKey())->exists());
        $this->assertTrue($budget->created_at->lessThan($retired->deleted_at), 'The budget template must predate the part being retired.');

        $applied = [
            [JobTemplateSeeder::DIESEL_SERVICE, 'NS-221-TT', 'Regular service: oil and filters.', 'zoran', 'ana'],
            [JobTemplateSeeder::FRONT_BRAKE_PADS, 'SM-045-VK', 'Front brakes noisy.', 'stefan', 'stefan'],
        ];

        foreach ($applied as [$name, $plate, $complaint, $mechanic, $actor]) {
            $template = JobTemplate::query()->where('name', $name)->sole();
            $job = Job::query()
                ->whereHas('vehicle', fn ($query) => $query->withTrashed()->where('registration_plate', $plate))
                ->where('complaint', $complaint)
                ->sole();
            $lines = JobItem::query()->where('job_id', $job->getKey())->orderBy('id')->get();

            $this->assertSame(
                $template->items()->orderBy('position')->get()->map(fn ($line): array => [$line->type, $line->part_id, $line->quantity])->all(),
                $lines->map(fn (JobItem $line): array => [$line->type, $line->part_id, $line->quantity])->all(),
                "{$plate}'s lines must be exactly the {$name} template's.",
            );
            $this->assertSame([User::withTrashed()->where('email', DemoSeeder::emailFor($mechanic))->value('id')], $lines->pluck('user_id')->unique()->values()->all());
            $this->assertSame(
                [User::withTrashed()->where('email', DemoSeeder::emailFor($actor))->value('id')],
                DB::table('stock_movements')->whereIn('job_item_id', $lines->pluck('id'))->distinct()->pluck('user_id')->all(),
            );
        }

        $this->assertStockMatchesLedger();
    }

    public function test_references_and_invoice_numbers_follow_the_timeline_and_nothing_is_dated_in_the_future(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(
            Job::query()->orderBy('created_at')->orderBy('id')->pluck('reference')->all(),
            Job::query()->orderBy('reference')->pluck('reference')->all(),
        );

        $this->assertSame(
            Invoice::query()->orderBy('issued_at')->orderBy('id')->pluck('number')->all(),
            Invoice::query()->orderBy('number')->pluck('number')->all(),
        );

        $this->assertTrue(Job::query()->where('created_at', '<', now()->subDays(50))->exists());

        foreach (['jobs', 'job_items', 'invoices', 'stock_movements', 'appointments'] as $table) {
            $this->assertFalse(DB::table($table)->where('created_at', '>', now())->exists(), "A {$table} row is dated in the future.");
        }
    }

    public function test_the_sold_vehicle_keeps_each_owner_on_their_own_jobs(): void
    {
        $this->seed(DemoDataSeeder::class);

        $vehicle = Vehicle::query()->where('registration_plate', 'NS-221-TT')->sole();
        $owners = Job::query()->whereBelongsTo($vehicle)->orderBy('created_at')->pluck('customer_id')->unique()->values();

        $this->assertCount(2, $owners);
        $this->assertSame($vehicle->customer_id, $owners->last());
    }

    public function test_model_events_ran_so_part_numbers_are_normalized(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, PartNumber::query()->normalizedAs('03c-115-561-h')->count());
    }

    public function test_check_ins_are_paper_signed_with_one_unsigned_one_drawn_and_one_missing(): void
    {
        $this->seed(DemoDataSeeder::class);

        $checkIns = JobCheckIn::query()->get();
        $unsigned = $checkIns->reject->isSigned();
        $drawn = $checkIns->filter->hasDrawnSignature();

        $this->assertSame(Job::query()->count() - 1, $checkIns->count(), 'Every demo job but one carries a check-in sheet.');
        $this->assertCount(1, $unsigned);
        $this->assertTrue(Job::query()->findOrFail($unsigned->first()->job_id)->isWorking());
        $this->assertNotNull($unsigned->first()->damage_notes, 'The open sheet must show damage notes.');
        $this->assertNotNull($unsigned->first()->valuables, 'The open sheet must show valuables.');

        foreach ($checkIns->filter->isSigned() as $signed) {
            $this->assertSame(Customer::withTrashed()->whereKey(Job::query()->whereKey($signed->job_id)->value('customer_id'))->value('name'), $signed->signed_by_name);
            $this->assertTrue($signed->signed_at->greaterThanOrEqualTo($signed->created_at), 'A sheet was signed before it was recorded.');
        }

        $this->assertCount(1, $drawn);
        Storage::disk(config('attachments.disk'))->assertExists($drawn->first()->signature_path);
        $this->assertSame(
            $checkIns->count() - 2,
            $checkIns->where('signature_method', SignatureMethod::Paper)->count(),
        );
        $this->assertSame(0, JobAttachment::query()->count(), 'The demo data ships no image files, so no check-in photos.');
    }

    public function test_estimates_cover_every_status_one_exceeded_approval_and_one_email(): void
    {
        $this->seed(DemoDataSeeder::class);

        $estimates = Estimate::query()->orderBy('id')->get();

        $this->assertEqualsCanonicalizing(
            EstimateStatus::cases(),
            $estimates->pluck('status')->unique()->values()->all(),
        );

        $exceeded = $estimates
            ->filter->isApproved()
            ->filter(function (Estimate $estimate): bool {
                $job = Job::query()->with('items')->findOrFail($estimate->job_id);

                return bccomp(app(InvoiceService::class)->totalsFor($job)['total'], $estimate->total, 2) > 0;
            });

        $this->assertCount(1, $exceeded, 'Exactly one approved estimate is outgrown by its job.');
        $this->assertSame(1, $estimates->whereNotNull('emailed_at')->count());
        $this->assertNotNull(
            Customer::withTrashed()->whereKey(Job::query()->whereKey($estimates->whereNotNull('emailed_at')->first()->job_id)->value('customer_id'))->value('email'),
        );

        $this->assertSame(
            Estimate::query()->orderBy('created_at')->orderBy('id')->pluck('number')->all(),
            Estimate::query()->orderBy('number')->pluck('number')->all(),
        );

        $this->assertTrue($estimates->every(fn (Estimate $estimate): bool => $estimate->lines()->exists()));
        $this->assertStockMatchesLedger();
    }

    public function test_customer_mail_is_diverted_and_the_configuration_restored(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'log']);

        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseCount('queue_jobs', 0);
        $this->assertNotEmpty(app('mail.manager')->mailer('array')->getSymfonyTransport()->messages());
        $this->assertSame('database', config('queue.default'));
        $this->assertSame('log', config('mail.default'));
    }

    public function test_due_soon_shows_both_kinds_an_overdue_row_and_every_contact_state(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'log']);

        $this->seed(DemoDataSeeder::class);

        $rows = collect(app(ReminderService::class)->dueSoon(CarbonImmutable::today()));

        $this->assertEqualsCanonicalizing(ReminderKind::cases(), $rows->pluck('kind')->unique()->values()->all());
        $this->assertTrue($rows->contains('overdue', true));
        $this->assertEqualsCanonicalizing(['emailed', 'pending', 'call'], $rows->pluck('contact')->unique()->values()->all());
        $this->assertTrue(Customer::query()->where('receives_reminders', false)->exists());
        $this->assertSame($rows->where('contact', 'emailed')->count(), VehicleReminder::query()->count());
        $this->assertDatabaseCount('queue_jobs', 0);
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $this->seed(DemoDataSeeder::class);

        $counts = fn (): array => [
            User::withTrashed()->count(),
            Customer::withTrashed()->count(),
            Job::query()->count(),
            DB::table('stock_movements')->count(),
        ];

        $before = $counts();

        $this->seed(DemoDataSeeder::class);

        $this->assertSame($before, $counts());
    }
}
