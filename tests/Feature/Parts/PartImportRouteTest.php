<?php

namespace Tests\Feature\Parts;

use App\Enums\PartImportColumn;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesRealUploads;
use Tests\TestCase;

class PartImportRouteTest extends TestCase
{
    use CreatesRealUploads, LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function frontOfficeProvider(): array
    {
        return ['receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_reaches_the_import_page(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('parts.import.create'))
            ->assertOk()
            ->assertSee(__('parts.import.title'));
    }

    public function test_a_mechanic_is_refused_every_import_route(): void
    {
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs($mechanic)->get(route('parts.import.create'))->assertForbidden();
        $this->actingAs($mechanic)->get(route('parts.import.template'))->assertForbidden();
        $this->actingAs($mechanic)
            ->post(route('parts.import.store'), ['file' => $this->realUpload('p.csv', "sku,quantity\nA,1\n")])
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_from_every_import_route(): void
    {
        $this->get(route('parts.import.create'))->assertRedirect(route('login'));
        $this->get(route('parts.import.template'))->assertRedirect(route('login'));
        $this->post(route('parts.import.store'))->assertRedirect(route('login'));
    }

    /**
     * `/parts/import` shares a prefix with `/parts/{part}`. Declared after the
     * wildcard it would resolve as a part lookup and 404, so this pins the
     * ordering rather than trusting route:list.
     */
    public function test_the_import_path_is_not_swallowed_by_the_part_wildcard(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get('/parts/import')
            ->assertOk()
            ->assertSee(__('parts.import.title'));
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_the_template_downloads_as_a_csv_attachment(string $role): void
    {
        $response = $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('parts.import.template'))
            ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'attachment; filename="parts-import-template.csv"',
            $response->headers->get('Content-Disposition'),
        );
    }

    /**
     * The template is generated from the same enum the parser reads, so a
     * column added to one must appear in the other. Compared against the enum
     * rather than a literal list, which would just restate the fixture.
     */
    public function test_the_template_header_row_is_exactly_the_parsers_columns(): void
    {
        $body = $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.import.template'))
            ->assertOk()
            ->getContent();

        $header = str_getcsv(strtok($body, "\r\n"), ',', '"', '\\');

        $this->assertSame(PartImportColumn::headers(), $header);
    }

    public function test_the_template_carries_one_example_row(): void
    {
        $body = $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.import.template'))
            ->assertOk()
            ->getContent();

        $lines = array_values(array_filter(explode("\r\n", $body), fn (string $l): bool => $l !== ''));

        $this->assertCount(2, $lines);
        $this->assertStringContainsString(PartImportColumn::Sku->exampleValue(), $lines[1]);
    }

    /**
     * The template handed to a receptionist must be one their own import
     * accepts. It carried `cost_price` until review, so downloading it and
     * uploading it back was rejected by our own header gate — a template that
     * teaches a header row the importer refuses.
     */
    public function test_a_receptionists_template_omits_the_admin_only_column(): void
    {
        $body = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.import.template'))
            ->assertOk()
            ->getContent();

        $header = str_getcsv(strtok($body, "\r\n"), ',', '"', '\\');

        $this->assertNotContains(PartImportColumn::CostPrice->value, $header);
        $this->assertContains(PartImportColumn::Sku->value, $header);
    }

    public function test_an_admins_template_keeps_the_admin_only_column(): void
    {
        $body = $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.import.template'))
            ->assertOk()
            ->getContent();

        $header = str_getcsv(strtok($body, "\r\n"), ',', '"', '\\');

        $this->assertContains(PartImportColumn::CostPrice->value, $header);
    }

    /**
     * The property the two tests above exist to protect: the header row a role
     * is handed is one that role's own import accepts. Only the header is
     * reused — the example row deliberately names a brand and a fake SKU, so
     * importing the template verbatim SHOULD fail rather than seed junk into
     * the catalogue.
     */
    #[DataProvider('frontOfficeProvider')]
    public function test_the_templates_header_row_is_accepted_from_the_role_it_was_built_for(string $role): void
    {
        $user = User::factory()->{$role}()->create();

        $template = $this->actingAs($user)->get(route('parts.import.template'))->assertOk()->getContent();
        $header = strtok($template, "\r\n");

        $columns = str_getcsv($header, ',', '"', '\\');
        $row = implode(',', array_map(fn (string $column): string => match ($column) {
            'sku' => 'REAL-1',
            'quantity' => '3',
            'name' => 'Real part',
            'unit_price' => '1200.00',
            'cost_price' => '600.00',
            default => '',
        }, $columns));

        $this->actingAs($user)
            ->post(route('parts.import.store'), ['file' => $this->realUpload('t.csv', $header."\r\n".$row."\r\n")])
            ->assertRedirect(route('parts.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, Part::query()->where('sku', 'REAL-1')->sole()->quantity_in_stock);
    }

    public function test_the_column_help_hides_the_admin_only_column_from_a_receptionist(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.import.create'))
            ->assertOk()
            ->assertDontSee(PartImportColumn::CostPrice->value);
    }

    public function test_the_column_help_shows_the_admin_only_column_to_an_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.import.create'))
            ->assertOk()
            ->assertSee(PartImportColumn::CostPrice->value);
    }

    /**
     * A rejected CSV body lands in the named `import` bag, not the default
     * one, so x-forms.field's own error handling never sees it. Without this
     * the file input announces as valid on the page that exists to report it
     * failed (WCAG 3.3.1).
     */
    public function test_the_file_input_announces_as_invalid_after_a_rejected_csv(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->from(route('parts.import.create'))
            ->post(route('parts.import.store'), [
                'file' => $this->realUpload('p.csv', "sku,quantity\nBP-1,nope\n"),
            ])
            ->assertRedirect(route('parts.import.create'));

        $html = $this->get(route('parts.import.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*id="file"[^>]*aria-invalid="true"/s', $html);
        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="file"[^>]*aria-describedby="import-errors-heading"/s',
            $html,
        );
    }

    public function test_the_file_input_is_not_marked_invalid_on_a_clean_page(): void
    {
        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.import.create'))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="file"[^>]*aria-invalid/s', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="file"[^>]*aria-describedby="import-limits"/s', $html);
    }

    public function test_a_successful_import_redirects_with_the_counts(): void
    {
        Part::factory()->create(['sku' => 'BP-1']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.import.store'), [
                'file' => $this->realUpload('p.csv', "sku,quantity,name,unit_price\nBP-1,2,x,1.00\nBP-2,3,New,4200.00\n"),
            ])
            ->assertRedirect(route('parts.index'))
            ->assertSessionHas('success', __('parts.import.flash.imported', [
                'created' => 1,
                'restocked' => 1,
            ]));
    }

    public function test_ignored_details_are_reported_as_an_info_flash(): void
    {
        Part::factory()->create(['sku' => 'BP-1']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.import.store'), [
                'file' => $this->realUpload('p.csv', "sku,quantity,name,unit_price\nBP-1,2,Ignored,9.00\n"),
            ])
            ->assertSessionHas('info', __('parts.import.flash.details_ignored', ['count' => 1]));
    }

    public function test_an_import_with_no_ignored_details_flashes_no_info_region(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.import.store'), [
                'file' => $this->realUpload('p.csv', "sku,quantity,name,unit_price\nBP-2,3,New,4200.00\n"),
            ])
            ->assertSessionMissing('info');
    }

    /**
     * `assertSessionHasErrors()` restarts the session store and consumes the
     * flashed bag, so a follow-up request would render as though validation
     * passed (.ai/rules/tests.md). This asserts on the RENDERED page instead
     * and deliberately never calls it.
     */
    public function test_row_errors_render_on_the_import_page(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->from(route('parts.import.create'))
            ->post(route('parts.import.store'), [
                'file' => $this->realUpload('p.csv', "sku,quantity\nBP-1,nope\n"),
            ])
            ->assertRedirect(route('parts.import.create'));

        $this->get(route('parts.import.create'))
            ->assertOk()
            ->assertSee(__('parts.import.errors.heading'))
            ->assertSee(__('parts.import.errors.bad_quantity', ['line' => 2, 'sku' => 'BP-1']));
    }

    public function test_a_clean_import_page_shows_no_error_region(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.import.create'))
            ->assertOk()
            ->assertDontSee(__('parts.import.errors.heading'));
    }

    public function test_a_missing_file_is_a_validation_error(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.import.store'), [])
            ->assertSessionHasErrors('file');
    }

    public function test_a_file_with_the_wrong_extension_is_refused(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('parts.import.store'), [
                'file' => $this->realImageUpload('sheet.jpg'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Part::query()->count());
    }

    /**
     * A semicolon CSV reports text/plain, so text/plain must be accepted and a
     * .txt of arbitrary content therefore passes the MIME rule. The header row
     * is what actually rejects it — this pins that the weak gate is backed up.
     */
    public function test_a_text_file_that_is_not_a_csv_is_rejected_by_its_headers(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->from(route('parts.import.create'))
            ->post(route('parts.import.store'), [
                'file' => $this->realUpload('notes.txt', "just some prose\nwith no columns\n"),
            ])
            ->assertRedirect(route('parts.import.create'));

        $this->get(route('parts.import.create'))
            ->assertOk()
            ->assertSee(__('parts.import.errors.missing_column', ['column' => 'sku']));

        $this->assertSame(0, Part::query()->count());
    }

    public function test_the_index_offers_the_import_action_to_front_office(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.index'))
            ->assertOk()
            ->assertSee(__('parts.import.action'));
    }

    public function test_the_index_hides_the_import_action_from_a_mechanic(): void
    {
        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('parts.index'))
            ->assertOk()
            ->assertDontSee(__('parts.import.action'));
    }
}
