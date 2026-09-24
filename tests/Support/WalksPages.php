<?php

namespace Tests\Support;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Http\Requests\Jobs\JobIndexRequest;
use App\Http\Requests\Parts\PartIndexRequest;
use App\Models\User;
use Closure;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Env;
use Illuminate\Testing\TestResponse;

/**
 * The page inventory every Quality sweep walks, and the assertions they share.
 *
 * An entry names a GET route, the roles the authorization matrix permits on
 * it, and how to build its URL from a QaFixture. Variants of one route are
 * separate entries keyed `route:variant`, so a failure names the exact page.
 *
 * The two violation collectors replace the exceptions preventLazyLoading and
 * preventAccessingMissingAttributes would throw, so one walk lists every
 * violation instead of stopping at the first. They are static on Model and
 * survive the test that installed them, which is why tearDown() resets them.
 */
trait WalksPages
{
    protected const ROLES = ['mechanic', 'receptionist', 'admin'];

    protected const ALL_STAFF = ['mechanic', 'receptionist', 'admin'];

    protected const FRONT_OFFICE = ['receptionist', 'admin'];

    protected const ADMIN_ONLY = ['admin'];

    protected const GUEST_ONLY = ['guest'];

    /** @var list<array{page: string, model: string, relation: string}> */
    private array $lazyLoads = [];

    /** @var list<array{page: string, model: string, attribute: string}> */
    private array $missingAttributes = [];

    private string $currentPage = '(no page)';

    /** @var array<string, string|null> */
    private array $originalAdminEnv = [];

    protected function tearDown(): void
    {
        Model::handleLazyLoadingViolationUsing(null);
        Model::handleMissingAttributeViolationUsing(null);

        foreach ($this->originalAdminEnv as $key => $value) {
            $value === null
                ? Env::getRepository()->clear($key)
                : Env::getRepository()->set($key, $value);
        }

        $this->originalAdminEnv = [];

        parent::tearDown();
    }

    /**
     * @return array<string, array{route: string, roles: list<string>, params?: Closure, query?: Closure, trashed?: int, parameterless?: bool, html?: bool}>
     */
    public static function pageInventory(): array
    {
        $entries = [
            'dashboard' => self::plain('dashboard', self::ALL_STAFF),

            'jobs.index' => self::plain('jobs.index', self::ALL_STAFF),
            'jobs.index:search' => self::page('jobs.index', self::ALL_STAFF, query: fn (): array => ['search' => QaFixture::PLATE_FRAGMENT]),
            'jobs.index:search-none' => self::page('jobs.index', self::ALL_STAFF, query: fn (): array => ['search' => 'zz-no-such-plate']),
            'jobs.index:status+search' => self::page('jobs.index', self::ALL_STAFF, query: fn (): array => [
                'status' => JobStatus::InProgress->value,
                'search' => QaFixture::PLATE_FRAGMENT,
            ]),
            'jobs.create' => self::plain('jobs.create', self::FRONT_OFFICE),
            'jobs.create:appointment' => self::page('jobs.create', self::FRONT_OFFICE, query: fn (QaFixture $shop): array => [
                'appointment' => $shop->bookedAppointment->getKey(),
            ]),
            'jobs.show:locked' => self::page('jobs.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->lockedJob]),
            'jobs.show:voided' => self::page('jobs.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->voidedJob]),
            'jobs.show:from-appointment' => self::page('jobs.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->jobFromAppointment]),
            'jobs.show:trashed-graph' => self::page('jobs.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->jobWithTrashedGraph]),
            'jobs.show:edit-own-line' => self::page('jobs.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->workingJob], fn (QaFixture $shop): array => [
                'edit' => $shop->ownLine->getKey(),
            ]),
            'jobs.show:add-part' => self::page('jobs.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->workingJob], fn (): array => ['add' => 'part']),
            'jobs.show:add-labor' => self::page('jobs.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->workingJob], fn (): array => ['add' => 'labor']),
            'jobs.invoice.create' => self::page('jobs.invoice.create', self::FRONT_OFFICE, fn (QaFixture $shop): array => [$shop->completedJob]),

            'invoices.index' => self::plain('invoices.index', self::FRONT_OFFICE),
            'invoices.index:search' => self::page('invoices.index', self::FRONT_OFFICE, query: fn (QaFixture $shop): array => ['search' => $shop->unpaidInvoice->number]),
            'invoices.index:search-none' => self::page('invoices.index', self::FRONT_OFFICE, query: fn (): array => ['search' => 'INV-0000-9999']),
            'invoices.show:unpaid' => self::page('invoices.show', self::FRONT_OFFICE, fn (QaFixture $shop): array => [$shop->unpaidInvoice]),
            'invoices.show:paid' => self::page('invoices.show', self::FRONT_OFFICE, fn (QaFixture $shop): array => [$shop->paidInvoice]),
            'invoices.show:void' => self::page('invoices.show', self::FRONT_OFFICE, fn (QaFixture $shop): array => [$shop->voidedInvoice]),
            'invoices.show:trashed-customer' => self::page('invoices.show', self::FRONT_OFFICE, fn (QaFixture $shop): array => [$shop->invoiceWithTrashedCustomer]),
            'invoices.void.create:unpaid' => self::page('invoices.void.create', self::ADMIN_ONLY, fn (QaFixture $shop): array => [$shop->unpaidInvoice]),
            'invoices.void.create:paid' => self::page('invoices.void.create', self::ADMIN_ONLY, fn (QaFixture $shop): array => [$shop->paidInvoice]),

            'appointments.index' => self::plain('appointments.index', self::ALL_STAFF),
            'appointments.index:week' => self::page('appointments.index', self::ALL_STAFF, query: fn (): array => ['view' => 'week']),
            'appointments.index:busy-day' => self::page('appointments.index', self::ALL_STAFF, query: fn (QaFixture $shop): array => ['date' => $shop->busyDay->toDateString()]),
            'appointments.index:empty-day' => self::page('appointments.index', self::ALL_STAFF, query: fn (QaFixture $shop): array => ['date' => $shop->emptyDay->toDateString()]),
            'appointments.show:booked' => self::page('appointments.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->bookedAppointment]),
            'appointments.show:arrived' => self::page('appointments.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->arrivedAppointment]),
            'appointments.show:no-show' => self::page('appointments.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->noShowAppointment]),
            'appointments.show:cancelled' => self::page('appointments.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->cancelledAppointment]),
            'appointments.edit' => self::page('appointments.edit', self::FRONT_OFFICE, fn (QaFixture $shop): array => [$shop->bookedAppointment]),
            'appointments.create' => self::plain('appointments.create', self::FRONT_OFFICE),
            'appointments.overlaps' => self::plain('appointments.overlaps', self::FRONT_OFFICE, [
                'query' => fn (): array => [
                    'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
                    'duration_minutes' => 60,
                ],
                'html' => false,
            ]),

            'customers.index' => self::plain('customers.index', self::ALL_STAFF),
            'customers.lookup' => self::plain('customers.lookup', self::FRONT_OFFICE, [
                'query' => fn (): array => ['q' => 'Ma'],
                'html' => false,
            ]),
            'customers.index:search' => self::page('customers.index', self::ALL_STAFF, query: fn (QaFixture $shop): array => ['search' => $shop->customer->name]),
            'customers.index:search-none' => self::page('customers.index', self::ALL_STAFF, query: fn (): array => ['search' => 'zz-nobody']),
            'customers.show' => self::page('customers.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->customer], trashed: 404),
            'customers.show:minimal' => self::page('customers.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->minimalCustomer], trashed: 404),
            'customers.create' => self::plain('customers.create', self::FRONT_OFFICE),
            'customers.edit' => self::page('customers.edit', self::FRONT_OFFICE, fn (QaFixture $shop): array => [$shop->customer], trashed: 404),
            'customers.vehicles.create' => self::page('customers.vehicles.create', self::FRONT_OFFICE, fn (QaFixture $shop): array => [$shop->customer], trashed: 404),

            'vehicles.show' => self::page('vehicles.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->vehicle], trashed: 404),
            'vehicles.show:reassigned' => self::page('vehicles.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->reassignedVehicle], trashed: 404),
            'vehicles.show:owner-trashed' => self::page('vehicles.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->vehicleWithTrashedOwner], trashed: 404),
            'vehicles.edit' => self::page('vehicles.edit', self::FRONT_OFFICE, fn (QaFixture $shop): array => [$shop->vehicle], trashed: 404),
            'vehicles.owner.edit' => self::page('vehicles.owner.edit', self::ADMIN_ONLY, fn (QaFixture $shop): array => [$shop->vehicle], trashed: 404),

            'parts.index' => self::plain('parts.index', self::ALL_STAFF),
            'parts.index:low' => self::page('parts.index', self::ALL_STAFF, query: fn (): array => ['filter' => PartIndexRequest::LOW_STOCK]),
            'parts.index:search-name' => self::page('parts.index', self::ALL_STAFF, query: fn (): array => ['search' => 'Oil filter']),
            'parts.index:search-identifier' => self::page('parts.index', self::ALL_STAFF, query: fn (): array => ['search' => '1k0-615-301-aa']),
            'parts.index:search-none' => self::page('parts.index', self::ALL_STAFF, query: fn (): array => ['search' => 'zz-no-such-part']),
            'parts.index:trashed' => self::page('parts.index', self::ADMIN_ONLY, query: fn (): array => ['trashed' => PartIndexRequest::TRASHED_WITH]),
            'parts.show' => self::page('parts.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->part]),
            'parts.show:low' => self::page('parts.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->lowPart]),
            'parts.show:negative' => self::page('parts.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->negativePart]),
            'parts.show:trashed-brand' => self::page('parts.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->partWithTrashedBrand]),
            'parts.show:trashed' => self::page('parts.show', self::ADMIN_ONLY, fn (QaFixture $shop): array => [$shop->trashedPart]),
            'parts.create' => self::plain('parts.create', self::FRONT_OFFICE),
            'parts.edit' => self::page('parts.edit', self::FRONT_OFFICE, fn (QaFixture $shop): array => [$shop->part]),
            'parts.adjust.create' => self::page('parts.adjust.create', self::FRONT_OFFICE, fn (QaFixture $shop): array => [$shop->part]),
            'parts.import.create' => self::plain('parts.import.create', self::FRONT_OFFICE),
            'parts.import.template' => self::plain('parts.import.template', self::FRONT_OFFICE, ['html' => false]),

            'attachments.show:photo-original' => self::page('attachments.show', self::ALL_STAFF, fn (QaFixture $shop): array => ['attachment' => $shop->photo], html: false),
            'attachments.show:photo-thumb' => self::page('attachments.show', self::ALL_STAFF, fn (QaFixture $shop): array => ['attachment' => $shop->photo, 'variant' => 'thumb'], html: false),
            'attachments.show:photo-full' => self::page('attachments.show', self::ALL_STAFF, fn (QaFixture $shop): array => ['attachment' => $shop->photo, 'variant' => 'full'], html: false),
            'attachments.show:document' => self::page('attachments.show', self::ALL_STAFF, fn (QaFixture $shop): array => ['attachment' => $shop->document], html: false),

            'admin.users.index' => self::plain('admin.users.index', self::ADMIN_ONLY),
            'admin.users.index:search' => self::page('admin.users.index', self::ADMIN_ONLY, query: fn (): array => ['search' => 'Bench']),
            'admin.users.create' => self::plain('admin.users.create', self::ADMIN_ONLY),
            'admin.users.edit' => self::page('admin.users.edit', self::ADMIN_ONLY, fn (QaFixture $shop): array => [$shop->technician], trashed: 404),
            'admin.users.offboard' => self::page('admin.users.offboard', self::ADMIN_ONLY, fn (QaFixture $shop): array => [$shop->technician], trashed: 404),

            'admin.brands.index' => self::plain('admin.brands.index', self::ADMIN_ONLY),
            'admin.brands.index:search' => self::page('admin.brands.index', self::ADMIN_ONLY, query: fn (): array => ['search' => 'Bosch']),
            'admin.brands.create' => self::plain('admin.brands.create', self::ADMIN_ONLY),
            'admin.brands.edit' => self::page('admin.brands.edit', self::ADMIN_ONLY, fn (QaFixture $shop): array => [$shop->brand]),

            'admin.appointment-hours.edit' => self::plain('admin.appointment-hours.edit', self::ADMIN_ONLY),

            'login' => self::plain('login', self::GUEST_ONLY),
            'password.request' => self::plain('password.request', self::GUEST_ONLY),
            'password.reset' => self::page('password.reset', self::GUEST_ONLY, fn (QaFixture $shop): array => ['token' => $shop->resetToken]),
        ];

        foreach (JobStatus::cases() as $status) {
            $entries["jobs.index:status={$status->value}"] = self::page('jobs.index', self::ALL_STAFF, query: fn (): array => ['status' => $status->value]);
            $entries["jobs.show:{$status->value}"] = self::page('jobs.show', self::ALL_STAFF, fn (QaFixture $shop): array => [$shop->jobsByStatus[$status->value]]);
        }

        foreach (JobIndexRequest::SORTS as $sort) {
            $entries["jobs.index:sort={$sort}"] = self::page('jobs.index', self::ALL_STAFF, query: fn (): array => ['sort' => $sort, 'direction' => 'asc']);
        }

        foreach (InvoiceStatus::cases() as $status) {
            $entries["invoices.index:status={$status->value}"] = self::page('invoices.index', self::FRONT_OFFICE, query: fn (): array => ['status' => $status->value]);
        }

        foreach (AppointmentStatus::cases() as $status) {
            $entries["appointments.index:status={$status->value}"] = self::page('appointments.index', self::ALL_STAFF, query: fn (): array => ['status' => $status->value]);
        }

        foreach (UserRole::cases() as $role) {
            $entries["admin.users.index:role={$role->value}"] = self::page('admin.users.index', self::ADMIN_ONLY, query: fn (): array => ['role' => $role->value]);
        }

        return $entries;
    }

    /**
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function plain(string $route, array $roles, array $extra = []): array
    {
        return ['route' => $route, 'roles' => $roles, 'parameterless' => true] + $extra;
    }

    /**
     * @param  list<string>  $roles
     * @return array<string, mixed>
     */
    private static function page(string $route, array $roles, ?Closure $params = null, ?Closure $query = null, int $trashed = 200, bool $html = true): array
    {
        return array_filter([
            'route' => $route,
            'roles' => $roles,
            'params' => $params,
            'query' => $query,
            'trashed' => $trashed,
            'html' => $html,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Send every inventory entry the role may reach and hand each response to
     * the callback. Returns the number walked so the caller can assert a
     * literal floor — never a count of the inventory it just walked.
     *
     * @param  callable(string, array<string, mixed>, TestResponse): void  $onResponse
     */
    protected function walk(QaFixture $shop, ?User $actor, string $role, callable $onResponse): int
    {
        $walked = 0;

        if ($actor !== null) {
            $this->actingAs($actor);
        }

        foreach (static::pageInventory() as $label => $entry) {
            if (! in_array($role, $entry['roles'], true)) {
                continue;
            }

            $this->currentPage = $label;
            $response = $this->get($this->pageUrl($entry, $shop));
            $onResponse($label, $entry, $response);
            $walked++;
        }

        $this->currentPage = '(no page)';

        return $walked;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function pageUrl(array $entry, ?QaFixture $shop): string
    {
        $params = isset($entry['params']) ? ($entry['params'])($shop) : [];
        $query = isset($entry['query']) ? ($entry['query'])($shop) : [];

        return route($entry['route'], array_merge($params, $query));
    }

    protected function actorFor(QaFixture $shop, string $role): ?User
    {
        return match ($role) {
            'mechanic' => $shop->mechanic,
            'receptionist' => $shop->receptionist,
            'admin' => $shop->admin,
            default => null,
        };
    }

    protected function assertPageRendered(TestResponse $response, string $label, int $expected = 200): void
    {
        $actual = $response->getStatusCode();
        $detail = '';
        $base = $response->baseResponse;
        $exception = property_exists($base, 'exception') ? $base->exception : $response->exceptions->first();

        if ($exception !== null) {
            $detail .= ' ('.$exception::class.': '.$exception->getMessage().')';
        }

        if ($response->isRedirect()) {
            $detail .= ' → '.$response->headers->get('Location');
        }

        $this->assertSame($expected, $actual, "{$label} — expected {$expected}, got {$actual}{$detail}");
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function assertNoBareKey(TestResponse $response, string $label, array $entry): void
    {
        if (($entry['html'] ?? true) === false) {
            return;
        }

        $bare = self::bareKeysIn((string) $response->getContent());

        $this->assertSame([], $bare, "{$label} renders bare translation keys: ".implode(', ', $bare));
    }

    /**
     * Translation keys rendered as themselves: `group.segment(.segment)*` in
     * TEXT, where group is one of the application's lang files. Attributes,
     * scripts and styles are stripped first, and the group anchoring is what
     * keeps `inspection.pdf` and email addresses out without a suppression
     * list.
     *
     * @return list<string>
     */
    public static function bareKeysIn(string $html): array
    {
        $text = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $groups = implode('|', array_map(preg_quote(...), self::langGroups()));

        preg_match_all("/(?<![\\w@\\/.\\-])(?:{$groups})\\.[a-z0-9_]+(?:\\.[a-z0-9_]+)*(?![\\w@\\/\\-])/", $text, $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * @return list<string>
     */
    public static function langGroups(): array
    {
        return array_map(fn (string $file): string => basename($file, '.php'), glob(lang_path('en/*.php')) ?: []);
    }

    protected function installViolationCollectors(): void
    {
        $this->lazyLoads = [];
        $this->missingAttributes = [];

        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            $this->lazyLoads[] = ['page' => $this->currentPage, 'model' => $model::class, 'relation' => $relation];
        });

        Model::handleMissingAttributeViolationUsing(function (Model $model, string $attribute): void {
            $this->missingAttributes[] = ['page' => $this->currentPage, 'model' => $model::class, 'attribute' => $attribute];
        });
    }

    protected function assertNoViolations(string $context): void
    {
        $this->assertSame([], $this->lazyLoads, "{$context} lazy-loaded: ".self::describeViolations($this->lazyLoads));
        $this->assertSame([], $this->missingAttributes, "{$context} read missing attributes: ".self::describeViolations($this->missingAttributes));
    }

    /**
     * @param  list<array<string, string>>  $violations
     */
    private static function describeViolations(array $violations): string
    {
        return implode('; ', array_map(
            fn (array $violation): string => implode(' ', $violation),
            $violations,
        ));
    }

    protected function seedAdmin(string $email = 'admin@auto-service.test', string $password = 'seeded-admin-password'): User
    {
        foreach (['ADMIN_EMAIL', 'ADMIN_PASSWORD'] as $key) {
            $value = Env::getRepository()->get($key);
            $this->originalAdminEnv[$key] = is_string($value) ? $value : null;
        }

        Env::getRepository()->set('ADMIN_EMAIL', $email);
        Env::getRepository()->set('ADMIN_PASSWORD', $password);

        $this->seed(AdminUserSeeder::class);

        return User::query()->where('email', $email)->firstOrFail();
    }
}
