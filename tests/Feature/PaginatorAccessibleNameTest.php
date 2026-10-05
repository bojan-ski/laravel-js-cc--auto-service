<?php

namespace Tests\Feature;

use App\Enums\Locale;
use DOMDocument;
use DOMXPath;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The desktop paginator's previous and next controls are icon-only, so their
 * `aria-label` is their whole accessible name. `pagination.previous`/`next`
 * carry the visible `&laquo;`/`&raquo;` arrows as HTML entities, and escaping
 * them into an attribute made a screen reader read "&laquo; Zurück" literally
 * (UAT F-6-10). The four label sites are the disabled previous span, the
 * previous link, the next link and the disabled next span; the first and last
 * pages between them render all four.
 */
class PaginatorAccessibleNameTest extends TestCase
{
    /**
     * @return array<string, array{0: Locale, 1: int}>
     */
    public static function localeAndPageProvider(): array
    {
        $cases = [];

        foreach (Locale::cases() as $locale) {
            foreach (['first' => 1, 'last' => 10] as $name => $page) {
                $cases["{$locale->value} {$name} page"] = [$locale, $page];
            }
        }

        return $cases;
    }

    #[DataProvider('localeAndPageProvider')]
    public function test_previous_and_next_are_named_in_words_without_entity_text(Locale $locale, int $page): void
    {
        app()->setLocale($locale->value);

        $labels = $this->previousAndNextLabels($page);

        $this->assertSame(['previous', 'next'], array_keys($labels), 'Both icon-only controls must carry an aria-label.');

        foreach ($labels as $label) {
            $this->assertDoesNotMatchRegularExpression('/&(?:[a-z]+|#\d+|#x[0-9a-f]+);/i', $label, "{$locale->value}: the accessible name \"{$label}\" contains entity text.");
            $this->assertDoesNotMatchRegularExpression('/[«»]/u', $label, "{$locale->value}: the accessible name \"{$label}\" reads the decorative arrow.");
        }

        $this->assertSame(__('Previous page'), $labels['previous']);
        $this->assertSame(__('Next page'), $labels['next']);

        if ($locale !== Locale::En) {
            $this->assertNotSame(__('Previous page', [], 'en'), $labels['previous'], "{$locale->value}: the previous label is not translated.");
            $this->assertNotSame(__('Next page', [], 'en'), $labels['next'], "{$locale->value}: the next label is not translated.");
        }
    }

    /**
     * The visible arrows stay: the mobile links still show them as text.
     */
    #[DataProvider('localeAndPageProvider')]
    public function test_the_visible_links_keep_their_arrows(Locale $locale, int $page): void
    {
        app()->setLocale($locale->value);

        $html = $this->render($page);

        $this->assertStringContainsString(__('pagination.previous'), $html);
        $this->assertStringContainsString(__('pagination.next'), $html);
        $this->assertStringContainsString('&laquo;', __('pagination.previous'));
        $this->assertStringContainsString('&raquo;', __('pagination.next'));
    }

    /**
     * @return array<string, string>
     */
    private function previousAndNextLabels(int $page): array
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$this->render($page), LIBXML_NOERROR);
        $xpath = new DOMXPath($document);

        $disabled = $page === 1 ? 'previous' : 'next';
        $linked = $page === 1 ? 'next' : 'previous';
        $rel = $linked === 'next' ? 'next' : 'prev';

        $labels = [
            $disabled => $xpath->query('//span[@aria-disabled="true"][@aria-label]/@aria-label'),
            $linked => $xpath->query("//a[@rel=\"{$rel}\"][@aria-label]/@aria-label"),
        ];

        $result = [];

        foreach (['previous', 'next'] as $control) {
            $this->assertSame(1, $labels[$control]->length, "Expected exactly one labelled {$control} control on page {$page}.");
            $result[$control] = $labels[$control]->item(0)->nodeValue;
        }

        return $result;
    }

    /**
     * Rendered through the paginator itself, so the published project copy of
     * `pagination::tailwind` is the view under test.
     */
    private function render(int $page): string
    {
        $paginator = new LengthAwarePaginator(range(1, 3), 30, 3, $page, ['path' => '/customers']);

        $html = $paginator->links()->toHtml();

        $this->assertStringContainsString('bg-surface-muted', $html, 'The published project paginator did not render.');

        return $html;
    }
}
