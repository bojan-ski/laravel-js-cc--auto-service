<?php

namespace Tests\Feature\Quality;

use App\Enums\Locale;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Every locale must say everything English says, and say it with the same
 * moving parts.
 *
 * A key missing from a locale does not fail loudly: Laravel falls back to
 * English, so a German screen silently grows an English sentence. A dropped
 * `:placeholder` is worse — the value vanishes from the sentence. Both are
 * checked here per file, per key.
 *
 * Files are read directly rather than through Lang::has(): the framework
 * registers its own lang directory, so a lookup can succeed on a key the
 * application never defined (see .ai/rules/tests.md).
 */
class LocaleParityTest extends TestCase
{
    /**
     * Counts that land in every plural form of every supported locale,
     * including Serbian's mod-10 cases (21 singular, 22 few, 25 many).
     */
    private const PLURAL_SAMPLES = [0, 1, 2, 3, 5, 11, 12, 20, 21, 22, 25, 101, 111];

    /**
     * The bare strings the published paginator passes to __().
     */
    private const PAGINATOR_JSON_KEYS = ['Pagination Navigation', 'Showing', 'to', 'of', 'results', 'Go to page :page', 'Previous page', 'Next page'];

    /**
     * @return array<string, array{0: Locale}>
     */
    public static function translatedLocaleProvider(): array
    {
        $cases = [];

        foreach (Locale::cases() as $locale) {
            if ($locale !== Locale::En) {
                $cases[$locale->value] = [$locale];
            }
        }

        return $cases;
    }

    /**
     * @return array<string, array{0: Locale, 1: string}>
     */
    public static function localeAndFileProvider(): array
    {
        $cases = [];

        foreach (self::translatedLocaleProvider() as [$locale]) {
            foreach (glob(dirname(__DIR__, 3).'/lang/en/*.php') as $path) {
                $file = basename($path, '.php');
                $cases["{$locale->value}/{$file}"] = [$locale, $file];
            }
        }

        return $cases;
    }

    #[DataProvider('localeAndFileProvider')]
    public function test_each_application_file_has_exactly_the_english_keys(Locale $locale, string $file): void
    {
        $english = $this->flatten(require lang_path("en/{$file}.php"));
        $translated = $this->flatten($this->load($locale, $file));

        $this->assertSame(
            [],
            array_values(array_diff(array_keys($english), array_keys($translated))),
            "lang/{$locale->value}/{$file}.php is missing keys English defines.",
        );
        $this->assertSame(
            [],
            array_values(array_diff(array_keys($translated), array_keys($english))),
            "lang/{$locale->value}/{$file}.php defines keys English does not.",
        );
    }

    #[DataProvider('localeAndFileProvider')]
    public function test_each_translation_keeps_every_placeholder(Locale $locale, string $file): void
    {
        $english = $this->flatten(require lang_path("en/{$file}.php"));
        $translated = $this->flatten($this->load($locale, $file));

        foreach ($english as $key => $value) {
            $this->assertIsString($translated[$key] ?? null, "{$locale->value}: {$file}.{$key} is not a string.");
            $this->assertNotSame('', trim($translated[$key]), "{$locale->value}: {$file}.{$key} is empty.");
            $this->assertSame(
                $this->placeholders($value),
                $this->placeholders($translated[$key]),
                "{$locale->value}: {$file}.{$key} changes the placeholders.",
            );
        }
    }

    /**
     * The framework serves English validation and pagination messages from its
     * own lang directory, so lang/en never needed them. Every other locale does.
     *
     * @return array<string, array{0: Locale, 1: string}>
     */
    public static function localeAndFrameworkFileProvider(): array
    {
        $cases = [];

        foreach (self::translatedLocaleProvider() as [$locale]) {
            foreach (['validation', 'pagination'] as $file) {
                $cases["{$locale->value}/{$file}"] = [$locale, $file];
            }
        }

        return $cases;
    }

    #[DataProvider('localeAndFrameworkFileProvider')]
    public function test_each_locale_translates_every_framework_message(Locale $locale, string $file): void
    {
        $framework = $this->flatten(require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$file}.php"));
        $translated = $this->flatten($this->load($locale, $file));

        unset($framework['custom.attribute-name.rule-name']);

        foreach ($framework as $key => $value) {
            $this->assertArrayHasKey($key, $translated, "lang/{$locale->value}/{$file}.php has no {$key}; it would render in English.");
            $this->assertSame(
                $this->placeholders($value),
                $this->placeholders($translated[$key]),
                "{$locale->value}: {$file}.{$key} changes the placeholders.",
            );
        }
    }

    /**
     * Without an `attributes` map a validation message names the raw field:
     * "Das Feld registration plate ist erforderlich."
     */
    #[DataProvider('translatedLocaleProvider')]
    public function test_every_locale_names_the_same_validation_fields(Locale $locale): void
    {
        $reference = array_keys($this->load(Locale::De, 'validation')['attributes'] ?? []);
        $attributes = $this->load($locale, 'validation')['attributes'] ?? [];

        $this->assertGreaterThanOrEqual(30, count($reference), 'The German attribute map is the reference and looks empty.');

        sort($reference);
        $keys = array_keys($attributes);
        sort($keys);

        $this->assertSame($reference, $keys, "lang/{$locale->value}/validation.php names a different set of fields.");

        foreach ($attributes as $field => $name) {
            $this->assertNotSame('', trim((string) $name), "{$locale->value}: attribute {$field} is empty.");
        }
    }

    #[DataProvider('translatedLocaleProvider')]
    public function test_every_locale_translates_the_paginator_strings(Locale $locale): void
    {
        $path = lang_path("{$locale->value}.json");

        $this->assertFileExists($path);

        $strings = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $keys = array_keys($strings);
        sort($keys);
        $expected = self::PAGINATOR_JSON_KEYS;
        sort($expected);

        $this->assertSame($expected, $keys);
    }

    /**
     * A language is always named in itself, so the switcher reads the same
     * in every locale.
     */
    #[DataProvider('translatedLocaleProvider')]
    public function test_language_names_are_identical_in_every_locale(Locale $locale): void
    {
        $this->assertSame(
            (require lang_path('en/app.php'))['languages'],
            $this->load($locale, 'app')['languages'],
        );
    }

    /**
     * @return array<string, array{0: Locale, 1: string}>
     */
    public static function pluralKeyProvider(): array
    {
        $root = dirname(__DIR__, 3);
        $keys = [];

        foreach (['app', 'resources'] as $directory) {
            $files = new RecursiveIteratorIterator(new \RecursiveDirectoryIterator("{$root}/{$directory}", \FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                preg_match_all("/(?:trans_choice|pluralTemplates)\('([a-z_.]+)'/", (string) file_get_contents($file->getPathname()), $matches);
                array_push($keys, ...$matches[1]);
            }
        }

        $keys = array_unique($keys);
        sort($keys);

        $cases = [];

        foreach (self::translatedLocaleProvider() as [$locale]) {
            foreach ($keys as $key) {
                $cases["{$locale->value} {$key}"] = [$locale, $key];
            }
        }

        return $cases;
    }

    /**
     * English writes its plurals with explicit ranges, which cannot express
     * Serbian's mod-10 rule. So this checks the rendered result, not the
     * syntax: every count yields a real sentence, and wherever English prints
     * the number, the translation prints it too.
     */
    #[DataProvider('pluralKeyProvider')]
    public function test_every_plural_renders_for_every_count(Locale $locale, string $key): void
    {
        [$file, $path] = explode('.', $key, 2);

        $this->assertArrayHasKey(
            $path,
            $this->flatten($this->load($locale, $file)),
            "{$locale->value} does not define {$key}; trans_choice would fall back to English and this check would pass on English text.",
        );

        foreach (self::PLURAL_SAMPLES as $count) {
            $replace = ['count' => $count, 'reference' => 'JOB-2026-0001', 'plate' => 'BG-123-AB'];

            $english = trans_choice($key, $count, $replace, 'en');
            $translated = trans_choice($key, $count, $replace, $locale->value);

            $this->assertNotSame($key, $translated, "{$locale->value}: {$key} does not resolve.");
            $this->assertStringNotContainsString('|', $translated, "{$locale->value}: {$key} for {$count} leaked a plural separator.");
            $this->assertDoesNotMatchRegularExpression('/[{\[]\d/', $translated, "{$locale->value}: {$key} for {$count} leaked a range marker.");

            if (preg_match('/\b'.$count.'\b/', $english)) {
                $this->assertMatchesRegularExpression(
                    '/\b'.$count.'\b/',
                    $translated,
                    "{$locale->value}: {$key} for {$count} drops the number English shows (\"{$english}\" → \"{$translated}\").",
                );
            }
        }
    }

    /**
     * MessageSelector strips a segment's `{0}` / `[2,*]` condition but keeps
     * the segment in the list it then indexes by the plural rule. So
     * `{0}None|:count one|:count few|:count many` gives 2 the "one" form:
     * index 1 is the first counted form, not the second. The number is still
     * printed, so the rendered check above cannot see it. Explicit segments
     * must therefore trail the counted forms, unless every segment is
     * explicit (English and German, whose plural rule ranges can express).
     */
    #[DataProvider('pluralKeyProvider')]
    public function test_explicit_plural_segments_come_after_the_counted_forms(Locale $locale, string $key): void
    {
        [$file, $path] = explode('.', $key, 2);
        $line = (string) ($this->flatten($this->load($locale, $file))[$path] ?? '');

        $explicit = array_map(
            fn (string $segment): bool => (bool) preg_match('/^\s*[{\[]/', $segment),
            explode('|', $line),
        );

        if (! in_array(false, $explicit, true)) {
            $this->addToAssertionCount(1);

            return;
        }

        $firstExplicit = array_search(true, $explicit, true);

        if ($firstExplicit === false) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertNotContains(
            false,
            array_slice($explicit, $firstExplicit),
            "{$locale->value}: {$key} has a counted form after an explicit segment, so the plural index is off by one: {$line}",
        );
    }

    /**
     * Guards the plural check: a Serbian rule that silently fell back to
     * English (as `sr_Latn` does) would render 2 and 5 identically.
     */
    public function test_serbian_uses_three_plural_forms(): void
    {
        $two = trans_choice('jobs.board.count', 2, ['count' => 2], 'sr');
        $five = trans_choice('jobs.board.count', 5, ['count' => 5], 'sr');
        $twentyOne = trans_choice('jobs.board.count', 21, ['count' => 21], 'sr');
        $one = trans_choice('jobs.board.count', 1, ['count' => 1], 'sr');

        $this->assertNotSame(str_replace('2', '5', $two), $five, 'Serbian 2 and 5 take different forms.');
        $this->assertSame(str_replace('1', '21', $one), $twentyOne, 'Serbian 21 takes the singular form.');
    }

    /**
     * @return array<string, mixed>
     */
    private function load(Locale $locale, string $file): array
    {
        $path = lang_path("{$locale->value}/{$file}.php");

        $this->assertFileExists($path);

        return require $path;
    }

    /**
     * @param  array<string, mixed>  $lines
     * @return array<string, mixed>
     */
    private function flatten(array $lines): array
    {
        $flat = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($lines));

        foreach ($iterator as $value) {
            $path = [];

            foreach (range(0, $iterator->getDepth()) as $depth) {
                $path[] = $iterator->getSubIterator($depth)->key();
            }

            $flat[implode('.', $path)] = $value;
        }

        return $flat;
    }

    /**
     * Compared case-insensitively: Laravel's `:Name` and `:NAME` forms are the
     * same placeholder capitalised, which a translation may need where English
     * did not (a value that opens the sentence).
     *
     * @return list<string>
     */
    private function placeholders(mixed $value): array
    {
        preg_match_all('/:([a-zA-Z_]+)/', (string) $value, $matches);

        $names = array_unique(array_map(strtolower(...), $matches[1]));
        sort($names);

        return $names;
    }
}
