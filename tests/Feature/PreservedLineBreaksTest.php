<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Part;
use App\Models\User;
use App\Models\Vehicle;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A `whitespace-pre-line` cell renders every newline in its markup, not only
 * the newlines in the stored text. A line break between the `<dd>` tag and its
 * content therefore shows as an empty first line, and the value sits one row
 * below its label. These tests read every pre-line `<dd>` on each detail page
 * and require that its only newlines are the ones in the data.
 */
class PreservedLineBreaksTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string NOTE = "Line one\nLine two";

    /**
     * @return array<string, array{Closure(?string): string}>
     */
    public static function pages(): array
    {
        return [
            'customer notes' => [fn (?string $text): string => route('customers.show', Customer::factory()->create(['notes' => $text]))],
            'vehicle notes' => [fn (?string $text): string => route('vehicles.show', Vehicle::factory()->create(['notes' => $text]))],
            'part description' => [fn (?string $text): string => route('parts.show', Part::factory()->create(['description' => $text]))],
            'appointment notes' => [fn (?string $text): string => route('appointments.show', Appointment::factory()->create(['notes' => $text]))],
        ];
    }

    /**
     * @param  Closure(?string): string  $page
     */
    #[DataProvider('pages')]
    public function test_a_multi_line_value_starts_on_the_labels_line(Closure $page): void
    {
        $cells = $this->preLineCells($page(self::NOTE));

        $this->assertContains(self::NOTE, $cells, 'The multi-line value must render with only its own line break.');

        foreach ($cells as $cell) {
            $this->assertStringStartsNotWith("\n", $cell);
        }
    }

    /**
     * @param  Closure(?string): string  $page
     */
    #[DataProvider('pages')]
    public function test_an_empty_value_renders_on_a_single_line(Closure $page): void
    {
        $cells = $this->preLineCells($page(null));

        foreach ($cells as $cell) {
            $this->assertStringNotContainsString("\n", $cell);
        }
    }

    /**
     * The inner content of every `whitespace-pre-line` `<dd>`, with the spaces
     * at the start and end of each line removed, because pre-line collapses
     * those and only a newline moves the text.
     *
     * Trailing newlines are removed too. Blade keeps the newline after every
     * `{{ }}` echo, and a browser gives a trailing newline no height: measured
     * at 24px for `abc` and `abc\n` alike, against 48px for `\nabc`. Only a
     * leading newline, or one between parts of the content, moves the text.
     *
     * @return list<string>
     */
    private function preLineCells(string $url): array
    {
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get($url)
            ->assertOk()
            ->getContent();

        preg_match_all('/<dd\b[^>]*whitespace-pre-line[^>]*>(.*?)<\/dd>/s', $html, $matches);

        $this->assertNotEmpty($matches[1], 'The page rendered no pre-line cell.');

        return array_map(
            fn (string $inner): string => rtrim((string) preg_replace('/^[ \t]+|[ \t]+$/m', '', $inner), "\n"),
            $matches[1],
        );
    }
}
