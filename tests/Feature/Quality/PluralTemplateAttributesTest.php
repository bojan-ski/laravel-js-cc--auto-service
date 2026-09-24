<?php

namespace Tests\Feature\Quality;

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Translation\MessageSelector;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Two JavaScript modules count things live — the booking overlap warning and
 * the customer picker's result count — so the server hands them one template
 * per plural category and `modules/plural.js` picks one with Intl.PluralRules.
 *
 * Before this, the overlap template went through __(), which never selects a
 * plural form, so the live warning printed the raw
 * "{1}1 other car …|[2,*]:count other cars …" string in every language. No
 * test saw it because PHPUnit does not run JavaScript; this pins the markup
 * the script reads.
 */
class PluralTemplateAttributesTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: Locale, 1: string}>
     */
    public static function localeAndPageProvider(): array
    {
        $cases = [];

        foreach (Locale::cases() as $locale) {
            foreach (['appointments.create', 'jobs.create'] as $route) {
                $cases["{$locale->value} {$route}"] = [$locale, $route];
            }
        }

        return $cases;
    }

    /**
     * The browser's "one" category is not "exactly 1" everywhere: in Serbian
     * it also covers 21, 31, 101. A template built from a count of 1 could be
     * a special wording ("Jedan…") that is wrong for 21, so Serbian samples
     * its "one" template at 21.
     */
    public function test_serbian_builds_its_singular_template_from_21(): void
    {
        $this->assertSame(21, Locale::Sr->pluralSamples()['one']);

        foreach ([Locale::En, Locale::De, Locale::Ro] as $locale) {
            $this->assertSame(1, $locale->pluralSamples()['one'], "{$locale->value}'s one category is exactly 1.");
        }
    }

    /**
     * Each template is rendered by Laravel's plural rule at its sample count,
     * and the browser later picks it by CLDR category. The two only agree if
     * every sample lands on the Laravel form that category means: one → form
     * 0, few → form 1, other → form 2 in the three-form languages, while in
     * English and German few and other are the same form.
     *
     * The CLDR side was checked by hand with PHP intl's MessageFormatter
     * (sr-Latn: 21=one, 2=few, 20=other; ro: 1=one, 2=few, 20=other); intl is
     * not a declared dependency, so it is not asserted here.
     */
    public function test_each_sample_lands_on_the_form_its_category_means(): void
    {
        $selector = new MessageSelector;
        $expected = [
            'en' => ['one' => 0, 'few' => 1, 'other' => 1],
            'de' => ['one' => 0, 'few' => 1, 'other' => 1],
            'sr' => ['one' => 0, 'few' => 1, 'other' => 2],
            'ro' => ['one' => 0, 'few' => 1, 'other' => 2],
        ];

        foreach (Locale::cases() as $locale) {
            $indexes = array_map(
                fn (int $sample): int => $selector->getPluralIndex($locale->value, $sample),
                $locale->pluralSamples(),
            );

            $this->assertSame($expected[$locale->value], $indexes, "{$locale->value}'s plural samples land on the wrong forms.");
        }
    }

    #[DataProvider('localeAndPageProvider')]
    public function test_each_plural_template_is_a_single_ready_sentence(Locale $locale, string $route): void
    {
        $user = User::factory()->receptionist()->create();
        $user->locale = $locale;
        $user->save();

        $html = $this->actingAs($user)->get(route($route))->assertOk()->getContent();

        preg_match_all('/data-plural-(one|few|other)="([^"]*)"/', $html, $templates, PREG_SET_ORDER);

        $widgets = ['appointments.create' => 2, 'jobs.create' => 1][$route];

        $this->assertCount(3 * $widgets, $templates, "{$route} should carry one, few and other templates for each of its {$widgets} counting widget(s).");

        foreach ($templates as [, $category, $template]) {
            $template = html_entity_decode($template, ENT_QUOTES);

            $this->assertStringNotContainsString('|', $template, "{$locale->value} {$category} leaked a plural separator.");
            $this->assertDoesNotMatchRegularExpression('/[{\[]\d/', $template, "{$locale->value} {$category} leaked a range marker.");
            if ($locale->pluralSamples()[$category] === 1) {
                continue;
            }

            $this->assertStringContainsString(':count', $template, "{$locale->value} {$category} covers several counts but has nowhere to put the number.");
        }
    }
}
