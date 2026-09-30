<?php

namespace Tests\Feature\Quality;

use App\Enums\Locale;
use Illuminate\Support\Facades\Lang;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Both translation-key sweeps.
 *
 * Laravel renders an unresolved key as the key itself: no error, no warning,
 * no failing test anywhere else. The forward sweep proves every literal key
 * in source resolves; the reverse sweep proves every defined key is still
 * consumed, so a reworded screen cannot leave dead copy behind.
 *
 * Both work by scanning source, so a key built from a variable at runtime
 * defeats both at once. Those sites are allow-listed by prefix below, each
 * naming its consumer and the exact fragment that constructs the key.
 */
class LocalizationCoverageTest extends TestCase
{
    /**
     * A literal key: a quoted string that is the whole first argument. The
     * closing quote must be followed by the argument separator or the closing
     * paren, which is what excludes a concatenated prefix (`'jobs.statuses.'.`)
     * and an interpolated string (`"jobs.flash.{$flashKey}"`) — those are the
     * constructed sites, allow-listed below rather than scanned.
     */
    private const LITERAL_KEY = '/(?:__|trans_choice|pluralTemplates)\(\s*([\'"])([^\'"{$]+)\1\s*[,)]/';

    /**
     * @var array<string, array{0: string, 1: string}> prefix => [consumer file, constructing fragment]
     */
    private const CONSTRUCTED = [
        'jobs.statuses.' => ['app/Enums/JobStatus.php', "__('jobs.statuses.'.\$this->value)"],
        'appointments.statuses.' => ['app/Enums/AppointmentStatus.php', "__('appointments.statuses.'.\$this->value)"],
        'invoices.statuses.' => ['app/Enums/InvoiceStatus.php', "__('invoices.statuses.'.\$this->value)"],
        'invoices.payment_methods.' => ['app/Enums/PaymentMethod.php', "__('invoices.payment_methods.'.\$this->value)"],
        'parts.reasons.' => ['app/Enums/StockMovementReason.php', "__('parts.reasons.'.\$this->value)"],
        'jobs.items.types.' => ['app/Enums/JobItemType.php', "__('jobs.items.types.'.\$this->value)"],
        'jobs.attachments.types.' => ['app/Enums/AttachmentType.php', "__('jobs.attachments.types.'.\$this->value)"],
        'check_ins.fuel_levels.' => ['app/Enums/FuelLevel.php', "__('check_ins.fuel_levels.'.\$this->value)"],
        'check_ins.signature_methods.' => ['app/Enums/SignatureMethod.php', "__('check_ins.signature_methods.'.\$this->value)"],
        'app.roles.' => ['app/Enums/UserRole.php', "__('app.roles.'.\$this->value)"],
        'app.theme.' => ['app/Enums/Theme.php', "__('app.theme.'.\$this->value)"],
        'app.languages.' => ['app/Enums/Locale.php', "__('app.languages.'.\$this->value)"],
        'brands.tiers.' => ['app/Enums/QualityTier.php', "__('brands.tiers.'.\$this->value)"],
        'parts.numbers.types.' => ['app/Enums/PartNumberType.php', "__('parts.numbers.types.'.\$this->value)"],
        'parts.import.columns.' => ['app/Enums/PartImportColumn.php', "__('parts.import.columns.'.\$this->value)"],
        'parts.adjust.help.' => ['app/View/Components/Parts/AdjustForm.php', "__('parts.adjust.help.'.\$reason->value)"],
        'jobs.board.ascending' => ['app/View/Components/Jobs/SortHeader.php', '__("jobs.board.{$target}ending")'],
        'jobs.board.descending' => ['app/View/Components/Jobs/SortHeader.php', '__("jobs.board.{$target}ending")'],
        'appointments.index.day' => ['app/View/Components/Appointments/Toolbar.php', '__("appointments.index.{$mode}")'],
        'appointments.index.week' => ['app/View/Components/Appointments/Toolbar.php', '__("appointments.index.{$mode}")'],
        'appointments.flash.' => ['app/Http/Controllers/Appointments/AppointmentTransitionController.php', '__("appointments.flash.{$flashKey}"'],
        'jobs.flash.' => ['app/Http/Controllers/Jobs/JobTransitionController.php', '__("jobs.flash.{$flashKey}"'],
        'jobs.items.flash.' => ['app/Http/Controllers/Jobs/JobItemController.php', '__("jobs.items.flash.{$flashKey}")'],
        'vehicles.validation.' => ['app/Http/Requests/Vehicles/VehicleRequest.php', "__('vehicles.validation.'.\$key"],
        'app.flash.' => ['resources/views/components/global/flash.blade.php', "__('app.flash.'.\$key)"],
        'vehicles.show.' => ['resources/views/components/vehicles/identity.blade.php', "__('vehicles.show.'.\$key)"],
        'customers.show.' => ['resources/views/components/customers/contact-details.blade.php', "__('customers.show.'.\$key)"],
        'appointments.show.' => ['resources/views/components/appointments/details.blade.php', "__('appointments.show.'.\$key)"],
        'jobs.show.' => ['resources/views/components/jobs/details.blade.php', "__('jobs.show.'.\$key)"],
    ];

    /**
     * Returned by the password broker and resolved through the one fully
     * variable key in the codebase, NewPasswordController's `__($status)`.
     * They appear as a literal `__()` argument nowhere, so without this list
     * "zero unused keys" is unsatisfiable — and they may not be deleted.
     *
     * @var list<string>
     */
    private const FRAMEWORK_RESOLVED = [
        'auth.password',
        'passwords.sent',
        'passwords.reset',
        'passwords.user',
        'passwords.token',
        'passwords.throttled',
    ];

    /**
     * The published paginator's JSON-style keys. English has no lang/en.json,
     * so in English each resolves to itself by design — which is why
     * resources/views/vendor is excluded from the forward sweep. Every other
     * locale translates exactly these in lang/{locale}.json.
     *
     * @var list<string>
     */
    private const PAGINATOR_KEYS = [
        'Pagination Navigation',
        'Showing',
        'to',
        'of',
        'results',
        'Go to page :page',
    ];

    public function test_every_literal_key_in_source_resolves(): void
    {
        $keys = $this->literalKeys();
        $unresolved = [];
        $dotless = [];

        foreach ($keys as $key => $files) {
            if (! str_contains($key, '.')) {
                $dotless[] = $key.' in '.implode(', ', $files);

                continue;
            }

            if (! Lang::has($key) || __($key) === $key) {
                $unresolved[] = $key.' in '.implode(', ', $files);
            }
        }

        $this->assertSame([], $unresolved, "Translation keys that render as themselves:\n".implode("\n", $unresolved));
        $this->assertSame([], $dotless, "JSON-style keys outside the vendor paginator:\n".implode("\n", $dotless));
        $this->assertGreaterThanOrEqual(600, count($keys), 'The scan found far fewer keys than the application uses; the regex or the file walk has broken.');
    }

    public function test_every_defined_key_is_used(): void
    {
        $literal = array_keys($this->literalKeys());
        $unused = [];

        foreach (array_keys($this->definedKeys()) as $key) {
            if ($this->isConsumed($key, $literal)) {
                continue;
            }

            $unused[] = $key;
        }

        $this->assertSame([], $unused, "Defined translation keys nothing references:\n".implode("\n", $unused));
        $this->assertGreaterThanOrEqual(700, count($this->definedKeys()), 'Far fewer keys are defined than the application carries; the lang walk has broken.');
    }

    public function test_an_exact_allow_list_entry_covers_only_itself(): void
    {
        $this->assertTrue(self::isConsumed('jobs.board.ascending', []));
        $this->assertTrue(self::isConsumed('jobs.statuses.anything_new', []));
        $this->assertTrue(self::isConsumed('passwords.token', []));
        $this->assertTrue(self::isConsumed('staff.offboard.consequences.0', ['staff.offboard.consequences']));

        $this->assertFalse(self::isConsumed('jobs.board.ascending_hint', []));
        $this->assertFalse(self::isConsumed('jobs.board', []));
        $this->assertFalse(self::isConsumed('jobs.statuses', []));
        $this->assertFalse(self::isConsumed('staff.offboard.consequences_extra', ['staff.offboard.consequences']));
    }

    public function test_every_allow_listed_prefix_still_has_its_consumer(): void
    {
        $orphaned = [];

        foreach (self::CONSTRUCTED as $prefix => [$consumer, $fragment]) {
            $path = base_path($consumer);

            if (! is_file($path) || ! str_contains((string) file_get_contents($path), $fragment)) {
                $orphaned[] = "{$prefix} — {$consumer} no longer contains {$fragment}";
            }
        }

        $this->assertSame([], $orphaned, "Allow-listed prefixes whose consumer is gone (delete the entry, the keys are now visible to the sweeps):\n".implode("\n", $orphaned));
    }

    /**
     * Read through require(), not Lang::has(): the framework ships its own
     * auth and passwords files and registers them ahead of the application's,
     * so Lang::has() answers yes whether or not lang/en defines the key.
     */
    public function test_the_application_defines_the_keys_the_framework_returns(): void
    {
        $auth = require lang_path('en/auth.php');
        $passwords = require lang_path('en/passwords.php');

        foreach (['failed', 'password', 'throttle'] as $key) {
            $this->assertArrayHasKey($key, $auth, "lang/en/auth.php does not define {$key}.");
            $this->assertNotSame('', $auth[$key]);
        }

        foreach (['sent', 'reset', 'user', 'token', 'throttled'] as $key) {
            $this->assertArrayHasKey($key, $passwords, "lang/en/passwords.php does not define {$key}.");
            $this->assertNotSame('', $passwords[$key]);
        }
    }

    public function test_the_paginator_is_the_only_source_of_json_style_keys(): void
    {
        $vendorKeys = [];

        foreach ($this->sourceFiles(vendorViewsOnly: true) as $file) {
            preg_match_all(self::LITERAL_KEY, (string) file_get_contents($file), $matches);

            foreach ($matches[2] as $key) {
                if (! str_contains($key, '.')) {
                    $vendorKeys[] = $key;
                }
            }
        }

        $vendorKeys = array_values(array_unique($vendorKeys));
        sort($vendorKeys);
        $expected = self::PAGINATOR_KEYS;
        sort($expected);

        $this->assertSame($expected, $vendorKeys, 'The vendor paginator uses different JSON-style keys than this test documents.');

        $jsonFiles = array_map(fn (string $path): string => basename($path), glob(lang_path('*.json')) ?: []);
        sort($jsonFiles);
        $translated = array_map(fn (Locale $locale): string => "{$locale->value}.json", array_filter(Locale::cases(), fn (Locale $locale): bool => $locale !== Locale::En));
        sort($translated);

        $this->assertSame($translated, $jsonFiles, 'lang/*.json exists for exactly the translated locales; English resolves the paginator keys to themselves.');

        foreach ($jsonFiles as $file) {
            $keys = array_keys(json_decode((string) file_get_contents(lang_path($file)), true, flags: JSON_THROW_ON_ERROR));
            sort($keys);

            $this->assertSame($expected, $keys, "lang/{$file} must carry exactly the paginator's keys and nothing else.");
        }
    }

    public function test_the_js_modules_carry_no_user_facing_strings(): void
    {
        $modules = glob(resource_path('js/modules/*.js')) ?: [];

        $this->assertCount(10, $modules, 'The module count changed; extend this test to the new module.');

        foreach ($modules as $module) {
            $source = (string) file_get_contents($module);
            preg_match_all('/([\'"`])([A-Z][a-z]+(?:\s+[A-Za-z:]+)+)\1/', $source, $matches);

            $this->assertSame([], $matches[2], basename($module).' carries user-facing copy: '.implode(', ', $matches[2]));
            $this->assertMatchesRegularExpression('/dataset\.|data-/', $source, basename($module).' reads no data-* hook, so its copy cannot be arriving through one.');
        }
    }

    /**
     * @return array<string, list<string>> key => files that use it
     */
    private function literalKeys(): array
    {
        $keys = [];

        foreach ($this->sourceFiles() as $file) {
            preg_match_all(self::LITERAL_KEY, (string) file_get_contents($file), $matches);

            foreach ($matches[2] as $key) {
                $keys[$key][] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            }
        }

        return $keys;
    }

    /**
     * @return array<string, mixed> dotted leaf key => value
     */
    private function definedKeys(): array
    {
        $defined = [];

        foreach (glob(lang_path('en/*.php')) ?: [] as $file) {
            $group = basename($file, '.php');

            foreach ($this->flatten(require $file) as $key => $value) {
                $defined["{$group}.{$key}"] = $value;
            }
        }

        return $defined;
    }

    /**
     * @param  array<int|string, mixed>  $lines
     * @return array<string, mixed>
     */
    private function flatten(array $lines, string $prefix = ''): array
    {
        $flat = [];

        foreach ($lines as $key => $value) {
            $dotted = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat += $this->flatten($value, $dotted);
            } else {
                $flat[$dotted] = $value;
            }
        }

        return $flat;
    }

    /**
     * An allow-list entry ending in a dot covers the family beneath it; an
     * entry naming one exact key covers that key alone, so a reworded sibling
     * (`jobs.board.ascending_hint`) is still reported as dead copy.
     *
     * @param  list<string>  $literal
     */
    public static function isConsumed(string $key, array $literal): bool
    {
        if (in_array($key, self::FRAMEWORK_RESOLVED, true)) {
            return true;
        }

        foreach (array_keys(self::CONSTRUCTED) as $prefix) {
            if ($key === $prefix || (str_ends_with($prefix, '.') && str_starts_with($key, $prefix))) {
                return true;
            }
        }

        foreach ($literal as $used) {
            if ($key === $used || str_starts_with($key, $used.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(bool $vendorViewsOnly = false): array
    {
        $files = [];
        $vendor = resource_path('views'.DIRECTORY_SEPARATOR.'vendor');

        $roots = $vendorViewsOnly ? [$vendor] : [app_path(), resource_path('views')];

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                if (! $vendorViewsOnly && str_starts_with($file->getPathname(), $vendor)) {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
