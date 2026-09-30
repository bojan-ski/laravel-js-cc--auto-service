<?php

namespace Tests\Support;

use App\Models\Appointment;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobCheckIn;
use App\Models\JobItem;
use App\Models\JobTemplate;
use App\Models\JobTemplateItem;
use App\Models\Part;
use App\Models\PartNumber;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AttachmentService;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;

/**
 * The one authorization matrix: every capability in project-overview.md's
 * Authorization Summary, its route(s), and the outcome each role must get.
 *
 * Shared by RoleMatrixTest (which walks every row, one method per role) and
 * AdminOnlyTest (which reads the same table filtered to the admin-only rows).
 * Two copies of this table would drift, which is the failure this spec was
 * rewritten to prevent.
 *
 * Reading a row: 'requests' is what the capability actually is over HTTP, and
 * each role key holds the exact expected outcome — a status code, or LOGIN for
 * the guest redirect. Exact statuses are deliberate: 403 and 404 are different
 * refusals here and mean different things (see row 29).
 */
trait AuthorizationMatrix
{
    use CreatesRealUploads;

    /**
     * The guest's expected outcome on an authenticated route.
     */
    protected const LOGIN = 'redirect-to-login';

    /**
     * Permitted. Exact, not vague: 200 for a GET, 302 for a write, and in
     * both cases no validation errors and no bounce to login. The verb
     * decides the success status, so a cell never has to restate it — while
     * 403 and 404 stay written out, because they are different refusals and
     * row 29 turns on telling them apart.
     */
    protected const ALLOW = 'allow';

    /**
     * Refused by being sent somewhere else: an authenticated user on a guest
     * page. A 302 to the dashboard, never a 403.
     */
    protected const AWAY = 'redirect-to-dashboard';

    /**
     * Fixtures already built for the row under assertion.
     *
     * @var array<string, mixed>
     */
    private array $fixtures = [];

    /**
     * Fixtures not built until a row asks for them. Attachments encode real
     * images through GD; building them for all 38 rows would pay that cost
     * roughly 150 times for the two rows that need it.
     *
     * @var array<string, callable>
     */
    private array $deferred = [];

    /**
     * Every capability, in the order project-overview.md lists them.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function matrix(): array
    {
        // Union, never spread: PHP casts a numeric-string key like '3' to the
        // integer 3, and the spread operator RE-INDEXES integer keys — which
        // silently renumbered every row and left the lettered ones ('4a', '20a')
        // in place, so the admin-only set came back as rows 10, 11, 12…
        return self::jobRows()
            + self::customerAndVehicleRows()
            + self::appointmentRows()
            + self::partRows()
            + self::templateRows()
            + self::invoiceRows()
            + self::adminRows()
            + self::sessionRows();
    }

    /**
     * Rows asserted by a dedicated method rather than by a walker, because
     * running them mid-walk would change who the walker is signed in as, or
     * because the capability is not expressed as a route at all.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function walkableMatrix(): array
    {
        return array_filter(self::matrix(), fn (array $row): bool => ($row['walkable'] ?? true));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function adminOnlyMatrix(): array
    {
        return array_filter(self::matrix(), fn (array $row): bool => ($row['admin_only'] ?? false));
    }

    /**
     * Build one coherent shop for the role about to be asserted.
     *
     * Seeded per row, not per walk: allowed cells write — a cancel, a mark
     * paid, a restore — and a shared scenario would let row order decide
     * whether the next row finds the state it assumes.
     *
     * The actor is threaded in because two rows are about ownership rather
     * than role: a mechanic may edit their own line and delete their own
     * upload, so those fixtures belong to the acting user when the actor is a
     * mechanic. A colleague's line is OwnershipTest's business, not the
     * matrix's.
     */
    protected function seedScenario(?User $actor): void
    {
        $this->fixtures = [];
        $this->deferred = [];

        Storage::fake(config('attachments.disk'));

        $owner = $actor !== null && $actor->isMechanic()
            ? $actor
            : User::factory()->mechanic()->create();

        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();

        $trashedVehicle = Vehicle::factory()->for($customer)->create();
        $trashedVehicle->delete();

        $part = Part::factory()->inStock()->create();
        $trashedPart = Part::factory()->inStock()->create();
        $trashedPart->delete();

        $trashedBrand = Brand::factory()->create();
        $trashedBrand->delete();

        $offboardedStaff = User::factory()->mechanic()->create();
        $offboardedStaff->delete();

        $this->fixtures = [
            'owner' => $owner,
            'customer' => $customer,
            'otherCustomer' => Customer::factory()->create(),
            'vehicle' => $vehicle,
            'trashedVehicle' => $trashedVehicle,
            'bookedAppointment' => Appointment::factory()->booked()->for($vehicle)->create(),
            'arrivalAppointment' => Appointment::factory()->booked()->for($vehicle)->create(),
            'receivedJob' => Job::factory()->received()->for($vehicle)->create(),
            'pausableJob' => Job::factory()->inProgress()->for($vehicle)->create(),
            'awaitingPartsJob' => Job::factory()->awaitingParts()->for($vehicle)->create(),
            'part' => $part,
            'trashedPart' => $trashedPart,
            'brand' => Brand::factory()->create(),
            'trashedBrand' => $trashedBrand,
            'staffMember' => User::factory()->mechanic()->create(),
            'offboardedStaff' => $offboardedStaff,
        ];

        // Row 31 posts a real reset, so its success is a redirect to login
        // rather than a redirect back carrying "this token is invalid".
        $resetUser = User::factory()->receptionist()->create();
        $this->fixtures['resetUser'] = $resetUser;
        $this->fixtures['resetToken'] = Password::broker()->createToken($resetUser);

        $this->fixtures['partNumber'] = PartNumber::factory()->for($part)->create();

        $template = JobTemplate::factory()->create();
        $this->fixtures['template'] = $template;
        $this->fixtures['templateLine'] = JobTemplateItem::factory()->forTemplate($template)->labor()->create(['position' => 1]);
        JobTemplateItem::factory()->forTemplate($template)->forPart($part, 1)->create(['position' => 2]);
        $this->fixtures['trashedTemplate'] = JobTemplate::factory()->trashed()->create();
        $this->fixtures['workingJob'] = $this->jobWithLine('inProgress', $owner, $vehicle);
        $this->fixtures['completedJob'] = $this->jobWithLine('completed', $owner, $vehicle);

        $lockedJob = $this->jobWithLine('completed', $owner, $vehicle);
        $lockedJob->forceFill(['locked_at' => now()->subMinutes(30)])->saveQuietly();

        $this->fixtures['lockedJob'] = $lockedJob;
        $this->fixtures['unpaidInvoice'] = Invoice::factory()->unpaid()->forJob($lockedJob)->create();
        $this->fixtures['ownLine'] = $this->fixtures['workingJob']->items->first();
        $this->fixtures['completedLine'] = $this->fixtures['completedJob']->items->first();
        $this->fixtures['pendingEstimate'] = Estimate::factory()->pending()->forJob($this->fixtures['completedJob'])->create();

        $checkInJob = Job::factory()->inProgress()->for($vehicle)->create();
        JobCheckIn::factory()->forJob($checkInJob)->recordedBy($owner)->create();
        $this->fixtures['checkInJob'] = $checkInJob;

        // A drawn signature encodes a real PNG through GD; only row 48 needs one.
        $this->deferred['signedCheckInJob'] = function () use ($vehicle, $owner): Job {
            $job = Job::factory()->inProgress()->for($vehicle)->create();
            JobCheckIn::factory()->forJob($job)->recordedBy($owner)->signedByDrawing()->create();

            return $job;
        };

        $this->deferred['ownAttachment'] = fn () => app(AttachmentService::class)->upload(
            $this->fixtures['pausableJob'],
            $this->realImageUpload('matrix.jpg', 240, 180),
            $owner,
        );
    }

    /**
     * @param  'completed'|'inProgress'  $state
     */
    private function jobWithLine(string $state, User $owner, Vehicle $vehicle): Job
    {
        $job = Job::factory()->{$state}()->for($vehicle)->create();

        JobItem::factory()->forJob($job)->performedBy($owner)->create();

        return $job->load('items');
    }

    protected function fixture(string $key): mixed
    {
        if (array_key_exists($key, $this->fixtures)) {
            return $this->fixtures[$key];
        }

        if (! array_key_exists($key, $this->deferred)) {
            throw new InvalidArgumentException("Unknown matrix fixture [{$key}].");
        }

        return $this->fixtures[$key] = ($this->deferred[$key])();
    }

    /**
     * Assert one cell: one request, one role, one verdict.
     *
     * @param  array<string, mixed>  $request
     */
    protected function assertCell(?User $actor, array $request, int|string $verdict, string $context): void
    {
        $response = $this->sendMatrixRequest($actor, $request);

        if ($verdict === self::LOGIN) {
            $this->assertTrue(
                $response->isRedirect(route('login')),
                $this->cellFailure($context, 'a redirect to login', $response),
            );

            return;
        }

        if ($verdict === self::AWAY) {
            $this->assertTrue(
                $response->isRedirect(route('dashboard')),
                $this->cellFailure($context, 'a redirect to the dashboard', $response),
            );

            return;
        }

        $expected = $verdict === self::ALLOW
            ? ($request['method'] === 'get' ? 200 : 302)
            : $verdict;

        // TestResponse::assertStatus() takes no message, and a bare "302 is not
        // 403" across 38 rows is undebuggable — the cell must name itself.
        $this->assertSame(
            $expected,
            $response->getStatusCode(),
            $this->cellFailure($context, (string) $expected, $response),
        );

        if ($verdict !== self::ALLOW) {
            return;
        }

        // A permitted cell must not be a refusal wearing a 302: an authenticated
        // user bounced to login, or a write rejected by validation. Every cell
        // sends a valid payload precisely so this holds.
        //
        // The bounce check is for authenticated actors only — for a guest, being
        // sent to login IS the refusal, and row 31's password reset legitimately
        // succeeds by redirecting there.
        if ($actor !== null) {
            $this->assertFalse(
                $response->isRedirect(route('login')),
                "{$context} — a permitted request was redirected to login.",
            );
        }

        $response->assertSessionHasNoErrors();
    }

    /**
     * Name the cell, then say what the application actually did. A 500 carries
     * its exception message, which is what makes a broken fixture legible.
     */
    private function cellFailure(string $context, string $expected, TestResponse $response): string
    {
        $actual = (string) $response->getStatusCode();

        if ($response->getStatusCode() === 500 && $response->exception !== null) {
            $actual .= ' ('.$response->exception->getMessage().')';
        }

        if ($response->isRedirect()) {
            $actual .= ' → '.$response->headers->get('Location');
        }

        return "{$context} — expected {$expected}, got {$actual}";
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function sendMatrixRequest(?User $actor, array $request): TestResponse
    {
        $method = $request['method'];
        $url = $this->matrixUrl($request);
        $payload = isset($request['payload']) ? ($request['payload'])($this) : [];

        $test = $actor === null ? $this : $this->actingAs($actor);

        return $method === 'get'
            ? $test->get($url)
            : $test->{$method}($url, $payload);
    }

    /**
     * @param  array<string, mixed>  $request
     */
    protected function matrixUrl(array $request): string
    {
        $params = array_map(
            fn (string $key): mixed => $this->fixture($key),
            $request['params'] ?? [],
        );

        $query = isset($request['query']) ? ($request['query'])($this) : [];

        return route($request['route'], array_merge($params, $query));
    }

    /**
     * Describe a cell well enough that a failure needs no debugging to place.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $request
     */
    protected function cellContext(string $key, array $row, array $request, string $role): string
    {
        return sprintf(
            'row %s (%s) — %s %s as %s',
            $key,
            $row['capability'],
            strtoupper($request['method']),
            $request['route'],
            $role,
        );
    }

    /**
     * The verdict for one role on one request.
     *
     * Every request in a row shares the row's verdict — the capability is the
     * unit of authorization, not the individual route. Where a row's requests
     * would need different verdicts, that is the signal to split it into two
     * rows, as 4/4a and 10/10a/10b already are.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $request
     */
    protected function verdictFor(array $row, array $request, string $role): int|string
    {
        return $row[$role];
    }

    /**
     * Rows 3–10b and 48: the jobs board, the job page, its transitions, its
     * lines, its check-in sheet and its attachments.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function jobRows(): array
    {
        return [
            '3' => [
                'capability' => 'View jobs board and detail',
                'requests' => [
                    ['method' => 'get', 'route' => 'jobs.index'],
                    ['method' => 'get', 'route' => 'jobs.show', 'params' => ['receivedJob']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '43' => [
                'capability' => 'Filter the board to my jobs (Mine)',
                'requests' => [
                    ['method' => 'get', 'route' => 'jobs.index', 'query' => fn (): array => ['mine' => 1]],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '4' => [
                'capability' => 'Pause for parts or complete a job',
                'requests' => [
                    ['method' => 'patch', 'route' => 'jobs.pause', 'params' => ['pausableJob']],
                    ['method' => 'patch', 'route' => 'jobs.complete', 'params' => ['workingJob']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '4b' => [
                'capability' => 'Start or resume work',
                'requests' => [
                    ['method' => 'patch', 'route' => 'jobs.start', 'params' => ['receivedJob']],
                    ['method' => 'patch', 'route' => 'jobs.resume', 'params' => ['awaitingPartsJob']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => 403,
                'admin' => self::ALLOW,
            ],
            '4a' => [
                'capability' => 'Reopen a completed job',
                'requests' => [
                    ['method' => 'patch', 'route' => 'jobs.reopen', 'params' => ['completedJob']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '5' => [
                'capability' => 'Create a job (walk-in)',
                'requests' => [
                    ['method' => 'get', 'route' => 'jobs.create'],
                    [
                        'method' => 'post',
                        'route' => 'jobs.store',
                        'payload' => fn ($t): array => [
                            'vehicle_id' => $t->fixture('vehicle')->getKey(),
                            'complaint' => 'Pulls right under braking',
                        ],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '6' => [
                'capability' => 'Cancel a job',
                'requests' => [
                    ['method' => 'patch', 'route' => 'jobs.cancel', 'params' => ['receivedJob']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '7' => [
                'capability' => 'Add a job line item',
                'requests' => [
                    [
                        'method' => 'post',
                        'route' => 'jobs.items.store',
                        'params' => ['workingJob'],
                        'payload' => fn ($t): array => [
                            'description' => 'Brake service',
                            'quantity' => '1.50',
                            'user_id' => $t->fixture('owner')->getKey(),
                        ],
                    ],
                    [
                        'method' => 'post',
                        'route' => 'jobs.parts.store',
                        'params' => ['workingJob'],
                        'payload' => fn ($t): array => [
                            'part_id' => $t->fixture('part')->getKey(),
                            'quantity' => '2',
                        ],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '8' => [
                'capability' => 'Edit / delete a job line item',
                'requests' => [
                    [
                        'method' => 'put',
                        'route' => 'job-items.update',
                        'params' => ['ownLine'],
                        'payload' => fn ($t): array => [
                            'description' => 'Brake service, revised',
                            'quantity' => '2.00',
                            'user_id' => $t->fixture('owner')->getKey(),
                        ],
                    ],
                    ['method' => 'delete', 'route' => 'job-items.destroy', 'params' => ['ownLine']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '9' => [
                'capability' => 'See prices, line totals, job totals',
                'requests' => [],
                'walkable' => false,
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '44' => [
                'capability' => 'Set a line discount',
                'requests' => [
                    [
                        'method' => 'patch',
                        'route' => 'job-items.discount.update',
                        'params' => ['completedLine'],
                        'payload' => fn (): array => ['discount_percent' => '10'],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '45' => [
                'capability' => 'Apply a job template to a job',
                'requests' => [
                    [
                        'method' => 'post',
                        'route' => 'jobs.templates.apply',
                        'params' => ['workingJob'],
                        'payload' => fn ($t): array => [
                            'job_template_id' => $t->fixture('template')->getKey(),
                            'user_id' => $t->fixture('owner')->getKey(),
                        ],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '49' => [
                'capability' => 'View estimates',
                'requests' => [
                    ['method' => 'get', 'route' => 'estimates.show', 'params' => ['pendingEstimate']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '50' => [
                'capability' => 'Create an estimate, record its decision, email it',
                'requests' => [
                    [
                        'method' => 'post',
                        'route' => 'jobs.estimates.store',
                        'params' => ['workingJob'],
                        'payload' => fn (): array => [],
                    ],
                    [
                        'method' => 'patch',
                        'route' => 'estimates.decision.update',
                        'params' => ['pendingEstimate'],
                        'payload' => fn (): array => ['decision' => 'approved', 'decision_method' => 'phone'],
                    ],
                    [
                        'method' => 'post',
                        'route' => 'estimates.email.store',
                        'params' => ['pendingEstimate'],
                        'payload' => fn (): array => [],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '48' => [
                'capability' => 'Record, sign, view, or print a check-in sheet',
                'requests' => [
                    ['method' => 'get', 'route' => 'jobs.check-in.show', 'params' => ['checkInJob']],
                    [
                        'method' => 'put',
                        'route' => 'jobs.check-in.update',
                        'params' => ['checkInJob'],
                        'payload' => fn (): array => ['fuel_level' => 'half', 'damage_notes' => 'Scratch on rear bumper'],
                    ],
                    [
                        'method' => 'post',
                        'route' => 'jobs.check-in.photos.store',
                        'params' => ['checkInJob'],
                        'payload' => fn ($t): array => ['photo' => $t->realImageUpload('matrix-check-in.jpg', 200, 150)],
                    ],
                    ['method' => 'get', 'route' => 'jobs.check-in.print', 'params' => ['checkInJob']],
                    [
                        'method' => 'patch',
                        'route' => 'jobs.check-in.sign',
                        'params' => ['checkInJob'],
                        'payload' => fn (): array => ['signature_method' => 'paper', 'signed_by_name' => 'Matrix Customer'],
                    ],
                    ['method' => 'get', 'route' => 'jobs.check-in.signature', 'params' => ['signedCheckInJob']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '10' => [
                'capability' => 'Upload job photos and documents',
                'requests' => [
                    [
                        'method' => 'post',
                        'route' => 'jobs.attachments.store',
                        'params' => ['pausableJob'],
                        'payload' => fn ($t): array => [
                            'file' => $t->realImageUpload('matrix-upload.jpg', 200, 150),
                        ],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '10a' => [
                'capability' => 'View / download a job attachment',
                'requests' => [
                    ['method' => 'get', 'route' => 'attachments.show', 'params' => ['ownAttachment']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '10b' => [
                'capability' => 'Delete a job attachment',
                'requests' => [
                    ['method' => 'delete', 'route' => 'attachments.destroy', 'params' => ['ownAttachment']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
        ];
    }

    /**
     * Rows 11–14 and 27: customers, vehicles, and the two admin-only powers
     * over them.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function customerAndVehicleRows(): array
    {
        return [
            '11' => [
                'capability' => 'View customers and vehicles',
                'requests' => [
                    ['method' => 'get', 'route' => 'customers.index'],
                    ['method' => 'get', 'route' => 'customers.show', 'params' => ['customer']],
                    ['method' => 'get', 'route' => 'vehicles.show', 'params' => ['vehicle']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '12' => [
                'capability' => 'Create / edit customers and vehicles',
                'requests' => [
                    ['method' => 'get', 'route' => 'customers.create'],
                    ['method' => 'get', 'route' => 'customers.lookup', 'query' => fn (): array => ['q' => 'Ma']],
                    [
                        'method' => 'post',
                        'route' => 'customers.store',
                        'payload' => fn (): array => ['name' => 'Matrix Customer', 'phone' => '060 000 111'],
                    ],
                    ['method' => 'get', 'route' => 'customers.edit', 'params' => ['customer']],
                    [
                        'method' => 'put',
                        'route' => 'customers.update',
                        'params' => ['customer'],
                        'payload' => fn (): array => ['name' => 'Matrix Customer', 'phone' => '060 000 222'],
                    ],
                    ['method' => 'get', 'route' => 'customers.vehicles.create', 'params' => ['customer']],
                    [
                        'method' => 'post',
                        'route' => 'customers.vehicles.store',
                        'params' => ['customer'],
                        'payload' => fn (): array => [
                            'registration_plate' => 'MTX-001',
                            'make' => 'Volkswagen',
                            'model' => 'Golf IV',
                        ],
                    ],
                    ['method' => 'get', 'route' => 'vehicles.edit', 'params' => ['vehicle']],
                    [
                        'method' => 'put',
                        'route' => 'vehicles.update',
                        'params' => ['vehicle'],
                        'payload' => fn ($t): array => [
                            'registration_plate' => $t->fixture('vehicle')->registration_plate,
                            'make' => 'Opel',
                            'model' => 'Astra H',
                        ],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '13' => [
                'capability' => 'Delete a customer or vehicle',
                'admin_only' => true,
                'requests' => [
                    ['method' => 'delete', 'route' => 'vehicles.destroy', 'params' => ['vehicle']],
                    ['method' => 'delete', 'route' => 'customers.destroy', 'params' => ['customer']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => 403,
                'admin' => self::ALLOW,
            ],
            '14' => [
                'capability' => 'Reassign a vehicle to another customer',
                'admin_only' => true,
                'requests' => [
                    ['method' => 'get', 'route' => 'vehicles.owner.edit', 'params' => ['vehicle']],
                    [
                        'method' => 'patch',
                        'route' => 'vehicles.owner.update',
                        'params' => ['vehicle'],
                        'payload' => fn ($t): array => [
                            'customer_id' => $t->fixture('otherCustomer')->getKey(),
                        ],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => 403,
                'admin' => self::ALLOW,
            ],
            '27' => [
                'capability' => 'Restore a soft-deleted vehicle',
                'admin_only' => true,
                'requests' => [
                    ['method' => 'patch', 'route' => 'vehicles.restore', 'params' => ['trashedVehicle']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => 403,
                'admin' => self::ALLOW,
            ],
        ];
    }

    /**
     * Rows 15–17: appointments. A mechanic may only look: the overlap endpoint,
     * the no-show action and the arrival path are all front-office.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function appointmentRows(): array
    {
        return [
            '15' => [
                'capability' => 'View appointments',
                'requests' => [
                    ['method' => 'get', 'route' => 'appointments.index'],
                    ['method' => 'get', 'route' => 'appointments.show', 'params' => ['bookedAppointment']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '16' => [
                'capability' => 'Create / edit / cancel appointments',
                'requests' => [
                    ['method' => 'get', 'route' => 'appointments.create'],
                    [
                        'method' => 'post',
                        'route' => 'appointments.store',
                        'payload' => fn ($t): array => [
                            'vehicle_id' => $t->fixture('vehicle')->getKey(),
                            'scheduled_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i'),
                            'duration_minutes' => 60,
                        ],
                    ],
                    ['method' => 'get', 'route' => 'appointments.edit', 'params' => ['bookedAppointment']],
                    [
                        'method' => 'put',
                        'route' => 'appointments.update',
                        'params' => ['bookedAppointment'],
                        'payload' => fn (): array => [
                            'scheduled_at' => now()->addDays(2)->setTime(10, 0)->format('Y-m-d\TH:i'),
                            'duration_minutes' => 90,
                        ],
                    ],
                    ['method' => 'patch', 'route' => 'appointments.cancel', 'params' => ['bookedAppointment']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '16a' => [
                'capability' => 'Query the overlap endpoint',
                'requests' => [
                    [
                        'method' => 'get',
                        'route' => 'appointments.overlaps',
                        'query' => fn (): array => [
                            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
                            'duration_minutes' => 60,
                        ],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '17' => [
                'capability' => 'Mark an appointment arrived / no-show',
                'requests' => [
                    ['method' => 'patch', 'route' => 'appointments.no-show', 'params' => ['bookedAppointment']],
                    [
                        'method' => 'get',
                        'route' => 'jobs.create',
                        'query' => fn ($t): array => [
                            'appointment' => $t->fixture('arrivalAppointment')->getKey(),
                        ],
                    ],
                    [
                        'method' => 'post',
                        'route' => 'jobs.store',
                        'payload' => fn ($t): array => [
                            'appointment_id' => $t->fixture('arrivalAppointment')->getKey(),
                            'complaint' => 'Service due',
                        ],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
        ];
    }

    /**
     * Rows 18–21, 28, 29 and 34–38: the parts catalogue, its stock, its
     * identifiers and its importer.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function partRows(): array
    {
        return [
            '18' => [
                'capability' => 'View parts catalogue and stock levels',
                'requests' => [
                    ['method' => 'get', 'route' => 'parts.index'],
                    ['method' => 'get', 'route' => 'parts.show', 'params' => ['part']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '19' => [
                'capability' => 'Create / edit parts',
                'requests' => [
                    ['method' => 'get', 'route' => 'parts.create'],
                    [
                        'method' => 'post',
                        'route' => 'parts.store',
                        'payload' => fn (): array => [
                            'sku' => 'MTX-9001',
                            'name' => 'Matrix part',
                            'unit_price' => '1200.00',
                            'reorder_level' => 2,
                        ],
                    ],
                    ['method' => 'get', 'route' => 'parts.edit', 'params' => ['part']],
                    [
                        'method' => 'put',
                        'route' => 'parts.update',
                        'params' => ['part'],
                        'payload' => fn ($t): array => [
                            'sku' => $t->fixture('part')->sku,
                            'name' => 'Matrix part, renamed',
                            'unit_price' => '1300.00',
                            'reorder_level' => 3,
                        ],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '20' => [
                'capability' => 'Adjust stock manually / restock',
                'requests' => [
                    ['method' => 'get', 'route' => 'parts.adjust.create', 'params' => ['part']],
                    [
                        'method' => 'post',
                        'route' => 'parts.adjust.store',
                        'params' => ['part'],
                        'payload' => fn (): array => ['reason' => 'restock', 'quantity_change' => '5'],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '20a' => [
                'capability' => 'Delete a part',
                'admin_only' => true,
                'requests' => [
                    ['method' => 'delete', 'route' => 'parts.destroy', 'params' => ['part']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => 403,
                'admin' => self::ALLOW,
            ],
            '21' => [
                'capability' => 'View part cost price',
                'admin_only' => true,
                'requests' => [],
                'walkable' => false,
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => 403,
                'admin' => self::ALLOW,
            ],
            '28' => [
                'capability' => 'Restore a soft-deleted part',
                'admin_only' => true,
                'requests' => [
                    ['method' => 'patch', 'route' => 'parts.restore', 'params' => ['trashedPart']],
                ],
                'guest' => self::LOGIN,
                // 404, where the twin row 27 (restore a vehicle) is 403 — and
                // the asymmetry is real, not an oversight. PartController
                // gates withTrashed() on `viewDeleted`, so a non-admin's query
                // never contains the row and findOrFail refuses before the
                // policy runs. VehicleController takes a route-bound model
                // declared withTrashed(), so there the policy answers. Both
                // refuse; the parts shape additionally declines to confirm
                // that the record exists.
                'mechanic' => 404,
                'receptionist' => 404,
                'admin' => self::ALLOW,
            ],
            '29' => [
                'capability' => 'See soft-deleted parts at all',
                'admin_only' => true,
                'requests' => [
                    ['method' => 'get', 'route' => 'parts.show', 'params' => ['trashedPart']],
                ],
                'guest' => self::LOGIN,
                // 404, not 403: the part is resolved through visibleTo(), so a
                // non-admin is told the record does not exist rather than that
                // they may not see it. A 403 here would mean the refusal had
                // moved to a policy branch that leaks its existence.
                'mechanic' => 404,
                'receptionist' => 404,
                'admin' => self::ALLOW,
            ],
            '34' => [
                'capability' => "View a part's OE and catalogue numbers",
                'requests' => [],
                'walkable' => false,
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '35' => [
                'capability' => 'Add / remove a part number',
                'requests' => [
                    [
                        'method' => 'post',
                        'route' => 'parts.numbers.store',
                        'params' => ['part'],
                        'payload' => fn (): array => ['type' => 'oe', 'number' => '1K0 615 301 AA'],
                    ],
                    [
                        'method' => 'delete',
                        'route' => 'parts.numbers.destroy',
                        'params' => ['part', 'partNumber'],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '36' => [
                'capability' => 'Import parts from a CSV',
                'requests' => [
                    ['method' => 'get', 'route' => 'parts.import.create'],
                    ['method' => 'get', 'route' => 'parts.import.template'],
                    [
                        'method' => 'post',
                        'route' => 'parts.import.store',
                        'payload' => fn ($t): array => [
                            // A row that creates an unknown SKU must carry a
                            // price; without it the import is refused and the
                            // cell would report a validation failure as a
                            // refusal.
                            'file' => $t->realUpload(
                                'matrix-import.csv',
                                "sku,name,quantity,unit_price\nMTX-7001,Matrix imported part,4,1500.00\n",
                                'text/csv',
                            ),
                        ],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '37' => [
                'capability' => 'Import a cost_price column',
                'admin_only' => true,
                'requests' => [],
                'walkable' => false,
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => 403,
                'admin' => self::ALLOW,
            ],
            '38' => [
                'capability' => 'Record the supplier on a restock',
                'requests' => [
                    [
                        'method' => 'post',
                        'route' => 'parts.adjust.store',
                        'params' => ['part'],
                        'payload' => fn (): array => [
                            'reason' => 'restock',
                            'quantity_change' => '6',
                            'supplier_name' => 'Febi Bilstein',
                        ],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
        ];
    }

    /**
     * Rows 46 and 47: managing job templates. Applying one is row 45, with the
     * other line-item rows, because JobPolicy::createItem authorizes it.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function templateRows(): array
    {
        return [
            '46' => [
                'capability' => 'Create / edit job templates',
                'requests' => [
                    ['method' => 'get', 'route' => 'job-templates.index'],
                    ['method' => 'get', 'route' => 'job-templates.create'],
                    [
                        'method' => 'post',
                        'route' => 'job-templates.store',
                        'payload' => fn (): array => ['name' => 'Matrix template'],
                    ],
                    ['method' => 'get', 'route' => 'job-templates.edit', 'params' => ['template']],
                    [
                        'method' => 'put',
                        'route' => 'job-templates.update',
                        'params' => ['template'],
                        'payload' => fn (): array => ['name' => 'Matrix template, renamed'],
                    ],
                    [
                        'method' => 'post',
                        'route' => 'job-templates.items.store',
                        'params' => ['template'],
                        'payload' => fn (): array => ['type' => 'labor', 'line_description' => 'Test drive', 'quantity' => '0.50'],
                    ],
                    [
                        'method' => 'put',
                        'route' => 'job-templates.items.update',
                        'params' => ['template', 'templateLine'],
                        'payload' => fn (): array => ['line_description' => 'Oil service, revised', 'quantity' => '1.25'],
                    ],
                    [
                        'method' => 'delete',
                        'route' => 'job-templates.items.destroy',
                        'params' => ['template', 'templateLine'],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '47' => [
                'capability' => 'Delete / restore a job template',
                'admin_only' => true,
                'requests' => [
                    ['method' => 'delete', 'route' => 'job-templates.destroy', 'params' => ['template']],
                    ['method' => 'patch', 'route' => 'job-templates.restore', 'params' => ['trashedTemplate']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => 403,
                'admin' => self::ALLOW,
            ],
        ];
    }

    /**
     * Rows 22–25: the invoice area, which a mechanic cannot reach at all.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function invoiceRows(): array
    {
        return [
            '22' => [
                'capability' => 'View invoices',
                'requests' => [
                    ['method' => 'get', 'route' => 'invoices.index'],
                    ['method' => 'get', 'route' => 'invoices.show', 'params' => ['unpaidInvoice']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '23' => [
                'capability' => 'Issue an invoice',
                'requests' => [
                    ['method' => 'get', 'route' => 'jobs.invoice.create', 'params' => ['completedJob']],
                    [
                        'method' => 'post',
                        'route' => 'jobs.invoice.store',
                        'params' => ['completedJob'],
                        'payload' => fn (): array => [],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '24' => [
                'capability' => 'Mark an invoice paid (with a payment method)',
                'requests' => [
                    [
                        'method' => 'patch',
                        'route' => 'invoices.paid',
                        'params' => ['unpaidInvoice'],
                        'payload' => fn ($t): array => ['payment_method' => 'cash'],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '25' => [
                'capability' => 'Void an invoice',
                'admin_only' => true,
                'requests' => [
                    ['method' => 'get', 'route' => 'invoices.void.create', 'params' => ['unpaidInvoice']],
                    ['method' => 'patch', 'route' => 'invoices.void', 'params' => ['unpaidInvoice']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => 403,
                'admin' => self::ALLOW,
            ],
        ];
    }

    /**
     * Rows 26, 33 and 39: the three sections behind role:admin.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function adminRows(): array
    {
        return [
            '26' => [
                'capability' => 'Manage staff accounts',
                'admin_only' => true,
                'requests' => [
                    ['method' => 'get', 'route' => 'admin.users.index'],
                    ['method' => 'get', 'route' => 'admin.users.create'],
                    [
                        'method' => 'post',
                        'route' => 'admin.users.store',
                        'payload' => fn (): array => [
                            'name' => 'Matrix Staff',
                            'email' => 'matrix.staff@shop.rs',
                            'role' => 'receptionist',
                            'password' => 'correct-horse-battery',
                            'password_confirmation' => 'correct-horse-battery',
                        ],
                    ],
                    ['method' => 'get', 'route' => 'admin.users.edit', 'params' => ['staffMember']],
                    ['method' => 'get', 'route' => 'admin.users.offboard', 'params' => ['staffMember']],
                    [
                        'method' => 'put',
                        'route' => 'admin.users.update',
                        'params' => ['staffMember'],
                        'payload' => fn ($t): array => [
                            'name' => 'Matrix Staff, renamed',
                            'email' => $t->fixture('staffMember')->email,
                            'role' => 'mechanic',
                        ],
                    ],
                    ['method' => 'delete', 'route' => 'admin.users.destroy', 'params' => ['staffMember']],
                    ['method' => 'patch', 'route' => 'admin.users.restore', 'params' => ['offboardedStaff']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => 403,
                'admin' => self::ALLOW,
            ],
            '33' => [
                'capability' => 'Manage brands',
                'admin_only' => true,
                'requests' => [
                    ['method' => 'get', 'route' => 'admin.brands.index'],
                    ['method' => 'get', 'route' => 'admin.brands.create'],
                    [
                        'method' => 'post',
                        'route' => 'admin.brands.store',
                        'payload' => fn (): array => ['name' => 'Matrix Brand', 'quality_tier' => 'aftermarket'],
                    ],
                    ['method' => 'get', 'route' => 'admin.brands.edit', 'params' => ['brand']],
                    [
                        'method' => 'put',
                        'route' => 'admin.brands.update',
                        'params' => ['brand'],
                        'payload' => fn ($t): array => [
                            'name' => $t->fixture('brand')->name,
                            'quality_tier' => 'original',
                        ],
                    ],
                    ['method' => 'delete', 'route' => 'admin.brands.destroy', 'params' => ['brand']],
                    ['method' => 'patch', 'route' => 'admin.brands.restore', 'params' => ['trashedBrand']],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => 403,
                'admin' => self::ALLOW,
            ],
            '39' => [
                'capability' => 'Set appointment hours',
                'admin_only' => true,
                'requests' => [
                    ['method' => 'get', 'route' => 'admin.appointment-hours.edit'],
                    [
                        'method' => 'put',
                        'route' => 'admin.appointment-hours.update',
                        'payload' => fn (): array => ['open_at' => '07:30', 'close_at' => '19:00'],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => 403,
                'receptionist' => 403,
                'admin' => self::ALLOW,
            ],
        ];
    }

    /**
     * Rows 1, 2, 30–32: the session boundary itself.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function sessionRows(): array
    {
        return [
            '1' => [
                'capability' => 'Log in',
                // Walked by nobody: posting credentials mid-walk would change
                // who the walker is signed in as. RoleMatrixTest asserts it
                // directly, where authentication itself can be checked.
                'walkable' => false,
                'requests' => [['method' => 'post', 'route' => 'login.store']],
                'guest' => self::ALLOW,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '2' => [
                'capability' => 'Reach any authenticated route at all',
                // GuestAccessTest enumerates the router for this row; it cannot
                // be expressed as a fixed request list without becoming the
                // hand-written subset the spec forbids.
                'walkable' => false,
                'requests' => [],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '30' => [
                'capability' => 'Reach the dashboard',
                'requests' => [['method' => 'get', 'route' => 'dashboard']],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '31' => [
                'capability' => 'Reach the guest pages while unauthenticated',
                'requests' => [
                    ['method' => 'get', 'route' => 'login'],
                    ['method' => 'get', 'route' => 'password.request'],
                    ['method' => 'get', 'route' => 'password.reset', 'params' => ['resetToken']],
                    [
                        'method' => 'post',
                        'route' => 'password.email',
                        'payload' => fn ($t): array => ['email' => $t->fixture('resetUser')->email],
                    ],
                    [
                        'method' => 'post',
                        'route' => 'password.store',
                        'payload' => fn ($t): array => [
                            'token' => $t->fixture('resetToken'),
                            'email' => $t->fixture('resetUser')->email,
                            'password' => 'correct-horse-battery',
                            'password_confirmation' => 'correct-horse-battery',
                        ],
                    ],
                ],
                'guest' => self::ALLOW,
                'mechanic' => self::AWAY,
                'receptionist' => self::AWAY,
                'admin' => self::AWAY,
            ],
            '32' => [
                'capability' => 'Log out',
                // Walked by nobody: it ends the walker's session.
                'walkable' => false,
                'requests' => [['method' => 'post', 'route' => 'logout']],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '41' => [
                'capability' => 'Change own language',
                'requests' => [
                    [
                        'method' => 'patch',
                        'route' => 'preferences.locale.update',
                        'payload' => fn (): array => ['locale' => 'de'],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
            '42' => [
                'capability' => 'Choose a language on the login page',
                'requests' => [
                    [
                        'method' => 'post',
                        'route' => 'locale.update',
                        'payload' => fn (): array => ['locale' => 'de'],
                    ],
                ],
                'guest' => self::ALLOW,
                'mechanic' => self::AWAY,
                'receptionist' => self::AWAY,
                'admin' => self::AWAY,
            ],
            '40' => [
                'capability' => 'Change own theme',
                'requests' => [
                    [
                        'method' => 'patch',
                        'route' => 'preferences.theme.update',
                        'payload' => fn (): array => ['theme' => 'dark'],
                    ],
                ],
                'guest' => self::LOGIN,
                'mechanic' => self::ALLOW,
                'receptionist' => self::ALLOW,
                'admin' => self::ALLOW,
            ],
        ];
    }
}
