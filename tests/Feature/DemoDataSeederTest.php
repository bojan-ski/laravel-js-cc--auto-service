<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Part;
use App\Models\PartNumber;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TaxIdChecksum;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Demo\DemoSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    public function test_stock_matches_the_ledger_and_the_catalogue_shows_low_and_negative_stock(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertStockMatchesLedger();
        $this->assertTrue(Part::query()->where('quantity_in_stock', '<', 0)->exists());
        $this->assertGreaterThan(0, Part::query()->whereColumn('quantity_in_stock', '<=', 'reorder_level')->count());
        $this->assertSame(1, Part::onlyTrashed()->count());
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

    public function test_customer_mail_is_diverted_and_the_configuration_restored(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'log']);

        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseCount('queue_jobs', 0);
        $this->assertNotEmpty(app('mail.manager')->mailer('array')->getSymfonyTransport()->messages());
        $this->assertSame('database', config('queue.default'));
        $this->assertSame('log', config('mail.default'));
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
