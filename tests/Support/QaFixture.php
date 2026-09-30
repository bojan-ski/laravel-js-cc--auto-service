<?php

namespace Tests\Support;

use App\Enums\JobStatus;
use App\Models\Appointment;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobItem;
use App\Models\JobTemplate;
use App\Models\JobTemplateItem;
use App\Models\Part;
use App\Models\PartNumber;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;

/**
 * The realistic shop every Quality sweep renders — spec 26's fixture table,
 * built from factories so each page has every branch to reach.
 *
 * The users a page attributes work to (creator, technician, issuer, voider,
 * the offboarded mechanic) are deliberately distinct from the three acting
 * users, so trashGraph() can soft-delete every attributed row while the walker
 * stays signed in as a live account of each role.
 *
 * Stock movements are factory rows and do not move parts.quantity_in_stock:
 * the ledger invariant is spec 24's business, not this fixture's.
 */
final class QaFixture
{
    public const OE_NUMBER = '1K0 615 301 AA';

    public const CATALOG_NUMBER = 'BP-9911';

    public const PLATE_FRAGMENT = 'QA1';

    private const PDF_BYTES = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    private static ?string $jpegBytes = null;

    private static ?string $webpBytes = null;

    public readonly User $admin;

    public readonly User $receptionist;

    public readonly User $mechanic;

    public readonly User $creator;

    public readonly User $technician;

    public readonly User $issuer;

    public readonly User $voider;

    public readonly User $offboardedMechanic;

    public readonly string $resetToken;

    /** @var Collection<int, Customer> */
    public readonly Collection $customers;

    public readonly Customer $customer;

    public readonly Customer $minimalCustomer;

    public readonly Customer $trashedCustomer;

    /** @var Collection<int, Vehicle> */
    public readonly Collection $vehicles;

    public readonly Vehicle $vehicle;

    public readonly Vehicle $reassignedVehicle;

    public readonly Vehicle $vehicleWithTrashedOwner;

    public readonly Vehicle $trashedVehicle;

    /** @var Collection<int, Appointment> */
    public readonly Collection $appointments;

    public readonly Appointment $bookedAppointment;

    public readonly Appointment $arrivedAppointment;

    public readonly Appointment $noShowAppointment;

    public readonly Appointment $cancelledAppointment;

    public readonly CarbonImmutable $busyDay;

    public readonly CarbonImmutable $emptyDay;

    public readonly Brand $brand;

    public readonly Brand $trashedBrand;

    public readonly Supplier $supplier;

    /** @var Collection<int, Part> */
    public readonly Collection $parts;

    public readonly Part $part;

    public readonly Part $lowPart;

    public readonly Part $negativePart;

    public readonly Part $trashedPart;

    public readonly Part $partWithTrashedBrand;

    public readonly PartNumber $partNumber;

    public readonly JobTemplate $template;

    public readonly JobTemplateItem $templateLine;

    public readonly JobTemplate $templateWithTrashedPart;

    public readonly JobTemplate $trashedTemplate;

    /** @var Collection<int, Job> */
    public readonly Collection $jobs;

    /** @var array<string, Job> */
    public readonly array $jobsByStatus;

    public readonly Job $workingJob;

    public readonly JobItem $ownLine;

    public readonly JobItem $partLine;

    public readonly Job $completedJob;

    public readonly JobItem $discountedLine;

    public readonly Job $lockedJob;

    public readonly Job $cancelledJob;

    public readonly Job $voidedJob;

    public readonly Job $jobFromAppointment;

    public readonly Job $jobWithTrashedGraph;

    /** @var Collection<int, Invoice> */
    public readonly Collection $invoices;

    public readonly Invoice $unpaidInvoice;

    public readonly Invoice $paidInvoice;

    public readonly Invoice $voidedInvoice;

    public readonly Invoice $invoiceWithTrashedCustomer;

    public readonly JobAttachment $photo;

    public readonly JobAttachment $document;

    public readonly JobAttachment $orphanedUploaderPhoto;

    /** @var Collection<int, Customer> */
    private Collection $customersToTrash;

    private Vehicle $graphVehicle;

    private Customer $newOwner;

    /** @var list<Job> */
    private array $lockedJobs = [];

    /** @var list<Job> */
    private array $voidedJobs = [];

    private function __construct() {}

    public static function build(bool $fakeDisk = true): self
    {
        if ($fakeDisk) {
            Storage::fake(config('attachments.disk'));
        }

        $fixture = new self;
        $fixture->buildStaff();
        $fixture->buildCustomersAndVehicles();
        $fixture->buildParts();
        $fixture->buildTemplates();
        $fixture->buildAppointments();
        $fixture->buildJobs();
        $fixture->buildInvoices();
        $fixture->buildAttachments();
        $fixture->applyHistory();

        return $fixture;
    }

    /**
     * Soft-delete every customer, every vehicle, and every user a page can
     * attribute work to — leaving only the three acting accounts live.
     * Model deletes throughout: `users` is on the query-builder-write
     * prohibition and SoftDeletes must stamp deleted_at, not remove rows.
     */
    public function trashGraph(): void
    {
        Customer::query()->each(fn (Customer $customer) => $customer->delete());
        Vehicle::query()->each(fn (Vehicle $vehicle) => $vehicle->delete());

        User::query()
            ->whereKeyNot([$this->admin->getKey(), $this->receptionist->getKey(), $this->mechanic->getKey()])
            ->each(fn (User $user) => $user->delete());
    }

    private function buildStaff(): void
    {
        $this->admin = User::factory()->admin()->create(['name' => 'QA Admin', 'email' => 'qa.admin@example.test']);
        $this->receptionist = User::factory()->receptionist()->create(['name' => 'QA Receptionist', 'email' => 'qa.receptionist@example.test']);
        $this->mechanic = User::factory()->mechanic()->create(['name' => 'QA Mechanic', 'email' => 'qa.mechanic@example.test']);
        $this->creator = User::factory()->receptionist()->create(['name' => 'Front Desk']);
        $this->technician = User::factory()->mechanic()->create(['name' => 'Bench Mechanic']);
        $this->issuer = User::factory()->receptionist()->create(['name' => 'Billing Desk']);
        $this->voider = User::factory()->admin()->create(['name' => 'Shop Owner']);
        $this->offboardedMechanic = User::factory()->mechanic()->create(['name' => 'Former Mechanic']);
        $this->resetToken = Password::broker()->createToken($this->receptionist);
    }

    private function buildCustomersAndVehicles(): void
    {
        $regular = collect([Customer::factory()->company()->create()])
            ->merge(Customer::factory()->count(25)->create());
        $this->minimalCustomer = Customer::factory()->minimal()->create(['name' => 'Walk-in Customer']);
        $this->customersToTrash = Customer::factory()->count(3)->create();

        $this->customer = $regular->first();
        $this->newOwner = $regular->get(2);
        $this->trashedCustomer = $this->customersToTrash->first();

        $vehicles = collect();

        foreach ($regular as $index => $customer) {
            $vehicles = $vehicles->merge(
                Vehicle::factory()->count($index < 9 ? 2 : 1)->for($customer)->create()
            );
        }

        $this->vehicle = $vehicles->first();
        $this->vehicle->forceFill(['registration_plate' => 'BG-'.self::PLATE_FRAGMENT.'-23'])->save();

        $this->reassignedVehicle = Vehicle::factory()->for($regular->get(1))->create();
        $this->trashedVehicle = Vehicle::factory()->for($this->customer)->create();
        $this->graphVehicle = Vehicle::factory()->for($this->trashedCustomer)->create();
        $this->vehicleWithTrashedOwner = Vehicle::factory()->for($this->customersToTrash->get(1))->create();
        $thirdTrashedOwnerVehicle = Vehicle::factory()->for($this->customersToTrash->get(2))->create();

        $this->vehicles = $vehicles
            ->push($this->reassignedVehicle, $this->trashedVehicle, $this->graphVehicle, $this->vehicleWithTrashedOwner, $thirdTrashedOwnerVehicle)
            ->values();

        $this->customers = $regular
            ->push($this->minimalCustomer)
            ->merge($this->customersToTrash)
            ->values();
    }

    private function buildParts(): void
    {
        $this->brand = Brand::factory()->genuine()->create(['name' => 'Bosch']);
        $this->trashedBrand = Brand::factory()->aftermarket()->trashed()->create(['name' => 'Discontinued Brand']);
        $otherBrand = Brand::factory()->original()->create(['name' => 'Mann-Filter']);
        $this->supplier = Supplier::factory()->named('Febi Bilstein')->create();

        $this->part = Part::factory()->inStock(12)->forBrand($this->brand)->inCategory('Filters')->create(['name' => 'Oil filter']);
        $this->partNumber = PartNumber::factory()->for($this->part)->oe()->number(self::OE_NUMBER)->create();
        PartNumber::factory()->for($this->part)->catalog()->number(self::CATALOG_NUMBER)->create();
        PartNumber::factory()->for($this->part)->ean()->create();
        PartNumber::factory()->for($this->part)->supplier()->create();

        $this->lowPart = Part::factory()->lowStock()->forBrand($otherBrand)->inCategory('Brakes')->create();
        $moreLow = Part::factory()->count(3)->lowStock()->create();
        $this->partWithTrashedBrand = Part::factory()->inStock()->forBrand($this->trashedBrand)->create();
        $this->negativePart = Part::factory()->negativeStock()->create();
        $this->trashedPart = Part::factory()->inStock()->create();
        $minimal = Part::factory()->count(4)->minimal()->inStock()->create();
        $branded = Part::factory()->count(8)->inStock()->forBrand($otherBrand)->inCategory('Service')->create();

        $this->parts = collect([$this->part, $this->lowPart])
            ->merge($moreLow)
            ->push($this->partWithTrashedBrand, $this->negativePart, $this->trashedPart)
            ->merge($minimal)
            ->merge($branded)
            ->values();

        StockMovement::factory()->forPart($this->part)->restock(12)->fromSupplier($this->supplier)->causedBy($this->creator)->create();
        StockMovement::factory()->forPart($this->part)->manualAdjustment(-1)->causedBy($this->voider)->create();
        StockMovement::factory()->forPart($this->lowPart)->restock(5)->fromSupplier($this->supplier)->causedBy($this->creator)->create();
        StockMovement::factory()->forPart($this->negativePart)->manualAdjustment(-2)->causedBy($this->creator)->create();
    }

    /**
     * The template on the part that applyHistory() trashes is built while the
     * part is still active, as a real shop's would have been.
     */
    private function buildTemplates(): void
    {
        $this->template = JobTemplate::factory()->create(['name' => 'Small service', 'description' => 'Oil, oil filter and a check.']);
        JobTemplateItem::factory()->forTemplate($this->template)->labor()->create(['position' => 1, 'description' => 'Oil and filter service']);
        $this->templateLine = JobTemplateItem::factory()->forTemplate($this->template)->forPart($this->part, 1)->create(['position' => 2]);

        $this->templateWithTrashedPart = JobTemplate::factory()->create(['name' => 'Budget service']);
        JobTemplateItem::factory()->forTemplate($this->templateWithTrashedPart)->labor()->create(['position' => 1]);
        JobTemplateItem::factory()->forTemplate($this->templateWithTrashedPart)->forPart($this->trashedPart, 1)->create(['position' => 2]);

        $this->trashedTemplate = JobTemplate::factory()->trashed()->create(['name' => 'Retired template']);
        JobTemplateItem::factory()->forTemplate($this->trashedTemplate)->labor()->create(['position' => 1]);
    }

    private function buildAppointments(): void
    {
        $today = CarbonImmutable::today();
        $this->busyDay = $today->subDays(3);
        $this->emptyDay = $today->addDays(40);

        $plan = [
            ['booked', 0, 14, 'bookedAppointment'],
            ['arrived', 0, 8, 'arrivedAppointment'],
            ['noShow', 0, 10, 'noShowAppointment'],
            ['arrived', -3, 9, null],
            ['arrived', -3, 11, null],
            ['cancelled', -3, 15, 'cancelledAppointment'],
            ['arrived', -1, 9, null],
            ['arrived', -2, 9, null],
            ['arrived', -4, 9, null],
            ['noShow', -5, 9, null],
            ['noShow', -6, 9, null],
            ['noShow', -7, 9, null],
            ['cancelled', -8, 9, null],
            ['cancelled', 2, 9, null],
        ];

        for ($offset = 1; $offset <= 11; $offset++) {
            $plan[] = ['booked', $offset, 8 + ($offset % 4), null];
        }

        $appointments = collect();

        foreach ($plan as $index => [$state, $offset, $hour, $handle]) {
            $vehicle = $handle === 'bookedAppointment' ? $this->vehicle : $this->vehicles->get($index % $this->vehicles->count());

            $appointment = Appointment::factory()->{$state}()->for($vehicle)->create([
                'scheduled_at' => $today->addDays($offset)->setTime($hour, 0),
                'created_by' => $this->creator->getKey(),
            ]);

            if ($handle !== null) {
                $this->{$handle} = $appointment;
            }

            $appointments->push($appointment);
        }

        $this->appointments = $appointments;
    }

    private function buildJobs(): void
    {
        $jobs = collect();
        $vehicleAt = fn (int $index): Vehicle => $this->vehicles->get(($index * 7) % $this->vehicles->count());

        $receivedJobs = [];
        for ($i = 0; $i < 3; $i++) {
            $job = $this->job('received', $vehicleAt($i));
            if ($i > 0) {
                $this->laborLine($job, $this->technician);
            }
            $receivedJobs[] = $job;
        }

        $this->workingJob = $this->job('inProgress', $this->vehicle);
        $this->ownLine = $this->laborLine($this->workingJob, $this->mechanic);
        $this->partLine = $this->partLine($this->workingJob, $this->part, 2);

        $formerMechanicJob = $this->job('inProgress', $vehicleAt(4));
        $this->laborLine($formerMechanicJob, $this->offboardedMechanic);
        $this->partLine($formerMechanicJob, $this->lowPart, 1);

        $this->jobFromAppointment = Job::factory()->inProgress()->forAppointment($this->arrivedAppointment)->create([
            'created_by' => $this->creator->getKey(),
        ]);
        $this->laborLine($this->jobFromAppointment, $this->technician);

        $fourthWorking = $this->job('inProgress', $vehicleAt(6));
        $this->laborLine($fourthWorking, $this->technician);

        $awaitingJobs = [];
        for ($i = 0; $i < 3; $i++) {
            $job = $this->job('awaitingParts', $vehicleAt(8 + $i));
            $this->laborLine($job, $this->technician);
            $this->partLine($job, $this->parts->get(5 + $i), 1);
            $awaitingJobs[] = $job;
        }

        $cancelledJobs = [];
        for ($i = 0; $i < 3; $i++) {
            $job = $this->job('cancelled', $vehicleAt(12 + $i));
            $part = $this->parts->get(9 + $i);
            $item = $this->partLine($job, $part, 2);
            StockMovement::factory()->forPart($part)->forJobItem($item)->jobReturn(2)->causedBy($this->creator)->create();
            $cancelledJobs[] = $job;
        }
        $this->cancelledJob = $cancelledJobs[0];

        $this->completedJob = $this->job('completed', $this->vehicle);
        $this->discountedLine = $this->laborLine($this->completedJob, $this->technician, '10.00');
        $this->partLine($this->completedJob, $this->part, 1);

        for ($i = 0; $i < 3; $i++) {
            $job = $this->job('completed', $vehicleAt(16 + $i));
            $this->laborLine($job, $this->technician);
            $this->voidedJobs[] = $job;
        }
        $this->voidedJob = $this->voidedJobs[0];

        $this->jobWithTrashedGraph = $this->job('completed', $this->graphVehicle);
        $this->laborLine($this->jobWithTrashedGraph, $this->technician);
        $this->partLine($this->jobWithTrashedGraph, $this->parts->get(12), 1);

        $lockedVehicles = [
            $this->vehicle,
            $this->vehicleWithTrashedOwner,
            $this->reassignedVehicle,
        ];
        for ($i = 0; $i < 12; $i++) {
            $vehicle = $lockedVehicles[$i] ?? $vehicleAt(20 + $i);
            $job = $this->job('completed', $vehicle);
            $this->laborLine($job, $i % 3 === 0 ? $this->offboardedMechanic : $this->technician, $i === 0 ? '12.50' : '0.00');
            $this->partLine($job, $this->parts->get(13 + ($i % 6)), 1);
            $job->locked_at = now()->subMinutes(30);
            $job->save();
            $this->lockedJobs[] = $job;
        }
        $this->lockedJob = $this->lockedJobs[0];

        $this->jobs = $jobs
            ->merge($receivedJobs)
            ->push($this->workingJob, $formerMechanicJob, $this->jobFromAppointment, $fourthWorking)
            ->merge($awaitingJobs)
            ->merge($cancelledJobs)
            ->push($this->completedJob)
            ->merge($this->voidedJobs)
            ->push($this->jobWithTrashedGraph)
            ->merge($this->lockedJobs)
            ->values();

        $this->jobsByStatus = [
            JobStatus::Received->value => $receivedJobs[0],
            JobStatus::InProgress->value => $this->workingJob,
            JobStatus::AwaitingParts->value => $awaitingJobs[0],
            JobStatus::Completed->value => $this->completedJob,
            JobStatus::Cancelled->value => $this->cancelledJob,
        ];
    }

    private function buildInvoices(): void
    {
        $invoices = collect();

        foreach ($this->lockedJobs as $index => $job) {
            $state = $index < 7 ? 'unpaid' : 'paid';

            $invoices->push(Invoice::factory()->{$state}()->forJob($job)->create([
                'issued_by' => $this->issuer->getKey(),
                ...$this->totalsFromLines($job),
            ]));
        }

        $this->unpaidInvoice = $invoices->get(0);
        $this->invoiceWithTrashedCustomer = $invoices->get(1);
        $this->paidInvoice = $invoices->get(7);

        foreach ($this->voidedJobs as $job) {
            $invoices->push(Invoice::factory()->void()->forJob($job)->create([
                'issued_by' => $this->issuer->getKey(),
                'voided_by' => $this->voider->getKey(),
                ...$this->totalsFromLines($job),
            ]));
        }

        $this->voidedInvoice = $invoices->get(12);
        $this->laborLine($this->voidedJob, $this->technician);

        $this->invoices = $invoices;
    }

    private function buildAttachments(): void
    {
        $this->photo = $this->attach(JobAttachment::factory()->photo()->forJob($this->lockedJob)->uploadedBy($this->technician));
        $this->document = $this->attach(JobAttachment::factory()->document()->forJob($this->lockedJob)->uploadedBy($this->technician));
        $this->orphanedUploaderPhoto = $this->attach(JobAttachment::factory()->photo()->forJob($this->workingJob)->uploadedBy($this->offboardedMechanic));
        $this->attach(JobAttachment::factory()->document()->forJob($this->workingJob)->uploadedBy($this->mechanic));
        $this->attach(JobAttachment::factory()->photo()->forJob($this->completedJob)->uploadedBy($this->technician));
    }

    private function applyHistory(): void
    {
        $this->reassignedVehicle->forceFill(['customer_id' => $this->newOwner->getKey()])->save();
        $this->trashedVehicle->delete();
        $this->graphVehicle->delete();
        $this->customersToTrash->each(fn (Customer $customer) => $customer->delete());
        $this->trashedPart->delete();
        $this->offboardedMechanic->delete();
    }

    /**
     * @param  'received'|'inProgress'|'awaitingParts'|'completed'|'cancelled'  $state
     */
    private function job(string $state, Vehicle $vehicle): Job
    {
        return Job::factory()->{$state}()->for($vehicle)->create([
            'created_by' => $this->creator->getKey(),
        ]);
    }

    /**
     * A stored invoice must agree with the lines it was issued from, as a real
     * issue would; the factory's random subtotal contradicts the page's own
     * invariant on a locked, non-void invoice.
     *
     * @return array{subtotal: string, discount_total: string, tax_amount: string, total: string}
     */
    private function totalsFromLines(Job $job): array
    {
        $issued = Job::query()->with('items')->findOrFail($job->getKey());

        $subtotal = $issued->subtotal();
        $rate = (string) config('shop.vat_rate');
        $tax = Money::round(bcdiv(bcmul($subtotal, $rate, 4), '100', 4));

        return [
            'subtotal' => $subtotal,
            'discount_total' => $issued->discountTotal(),
            'tax_amount' => $tax,
            'total' => bcadd($subtotal, $tax, 2),
        ];
    }

    private function laborLine(Job $job, User $mechanic, string $discount = '0.00'): JobItem
    {
        return JobItem::factory()->forJob($job)->performedBy($mechanic)->discounted($discount)->create();
    }

    private function partLine(Job $job, Part $part, int $quantity): JobItem
    {
        $item = JobItem::factory()->forJob($job)->part()->forPart($part)->create(['quantity' => $quantity]);

        StockMovement::factory()->forPart($part)->forJobItem($item)->jobUse($quantity)->causedBy($this->technician)->create();

        return $item;
    }

    /**
     * @param  Factory<JobAttachment>  $factory
     */
    private function attach(Factory $factory): JobAttachment
    {
        $attachment = $factory->create();
        $disk = Storage::disk(config('attachments.disk'));

        if ($attachment->isPhoto()) {
            $disk->put($attachment->path, self::jpegBytes());
            $disk->put($attachment->variant_full_path, self::webpBytes());
            $disk->put($attachment->variant_thumb_path, self::webpBytes());
        } else {
            $disk->put($attachment->path, self::PDF_BYTES);
        }

        return $attachment;
    }

    private static function jpegBytes(): string
    {
        return self::$jpegBytes ??= self::imageBytes('jpeg');
    }

    private static function webpBytes(): string
    {
        return self::$webpBytes ??= self::imageBytes('webp');
    }

    private static function imageBytes(string $format): string
    {
        $image = imagecreatetruecolor(40, 30);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 90, 180));

        ob_start();
        $format === 'webp' ? imagewebp($image) : imagejpeg($image, null, 80);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
