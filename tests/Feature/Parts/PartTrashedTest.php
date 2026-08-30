<?php

namespace Tests\Feature\Parts;

use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PartTrashedTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_deleting_soft_deletes_and_removes_the_part_from_the_catalogue(): void
    {
        $part = Part::factory()->create(['sku' => 'WB-24', 'name' => 'Wiper blade 24"']);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('parts.destroy', $part))
            ->assertRedirect(route('parts.index'));

        $this->assertSoftDeleted($part);

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.index'))
            ->assertOk();

        $this->assertSame(0, $response->viewData('parts')->total());
    }

    public function test_a_deleted_part_is_removed_from_search(): void
    {
        Part::factory()->deleted()->create(['sku' => 'WB-24', 'name' => 'Wiper blade 24"']);

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.index', ['search' => 'WB-24']))
            ->assertOk();

        $this->assertSame(0, $response->viewData('parts')->total());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminProvider(): array
    {
        return [
            'mechanic' => ['mechanic'],
            'receptionist' => ['receptionist'],
        ];
    }

    #[DataProvider('nonAdminProvider')]
    public function test_a_deleted_parts_detail_url_returns_404_for_a_non_admin(string $role): void
    {
        $part = Part::factory()->deleted()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('parts.show', $part))
            ->assertNotFound();
    }

    public function test_an_admin_may_open_a_deleted_parts_detail_page(): void
    {
        $part = Part::factory()->deleted()->create(['sku' => 'WB-24']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.show', $part))
            ->assertOk()
            ->assertSee('WB-24')
            ->assertSee(__('parts.trashed.badge'));
    }

    public function test_a_deleted_part_cannot_be_edited_by_anyone(): void
    {
        $part = Part::factory()->deleted()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.edit', $part))
            ->assertNotFound();
    }

    #[DataProvider('nonAdminProvider')]
    public function test_the_trashed_filter_is_ignored_for_a_non_admin(string $role): void
    {
        Part::factory()->deleted()->create(['sku' => 'WB-24']);
        Part::factory()->create(['sku' => 'OF-01']);

        $response = $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('parts.index', ['trashed' => 'with']))
            ->assertOk();

        $this->assertSame(['OF-01'], $response->viewData('parts')->pluck('sku')->all());
        $this->assertFalse($response->viewData('withTrashed'));
    }

    public function test_the_trashed_filter_is_honoured_for_an_admin(): void
    {
        Part::factory()->deleted()->create(['sku' => 'WB-24']);
        Part::factory()->create(['sku' => 'OF-01']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.index', ['trashed' => 'with']))
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            ['OF-01', 'WB-24'],
            $response->viewData('parts')->pluck('sku')->all(),
        );
        $this->assertTrue($response->viewData('withTrashed'));
    }

    public function test_the_trashed_filter_combines_with_the_low_stock_filter(): void
    {
        Part::factory()->lowStock()->create(['sku' => 'LOW-ACTIVE']);
        Part::factory()->lowStock()->deleted()->create(['sku' => 'LOW-DELETED']);
        Part::factory()->inStock()->deleted()->create(['sku' => 'HEALTHY-DELETED']);
        Part::factory()->inStock()->create(['sku' => 'HEALTHY-ACTIVE']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.index', ['trashed' => 'with', 'filter' => 'low_stock']))
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            ['LOW-ACTIVE', 'LOW-DELETED'],
            $response->viewData('parts')->pluck('sku')->all(),
        );

        $this->assertSame(['all' => 4, 'low_stock' => 2], $response->viewData('counts'));
    }

    public function test_the_trashed_toggle_flips_its_label_and_clears_the_filter_when_active(): void
    {
        $admin = User::factory()->admin()->create();

        $inactive = $this->tabHref(
            $this->actingAs($admin)->get(route('parts.index', ['search' => 'Oil']))->assertOk()->getContent(),
            __('parts.trashed.show'),
        );

        $this->assertStringContainsString('trashed=with', $inactive);
        $this->assertStringContainsString('search=Oil', $inactive);

        $active = $this->tabHref(
            $this->actingAs($admin)->get(route('parts.index', ['trashed' => 'with', 'search' => 'Oil']))->assertOk()->getContent(),
            __('parts.trashed.hide'),
        );

        $this->assertStringNotContainsString('trashed=with', $active);
        $this->assertStringContainsString('search=Oil', $active);
    }

    public function test_the_active_trashed_toggle_does_not_also_claim_to_be_the_current_page(): void
    {
        Part::factory()->lowStock()->create();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.index', ['trashed' => 'with', 'filter' => 'low_stock']))
            ->assertOk()
            ->getContent();

        preg_match('/<nav[^>]*aria-label="'.preg_quote(__('parts.tabs.label'), '/').'".*?<\/nav>/s', $html, $nav);

        $this->assertNotEmpty($nav, 'The filter tab navigation was not rendered.');
        $this->assertStringContainsString(__('parts.trashed.hide'), $nav[0]);
        $this->assertSame(
            1,
            substr_count($nav[0], 'aria-current="page"'),
            'The trashed toggle is a binary control, not one of the mutually exclusive tabs, so only the active tab may be the current page.'
        );
    }

    private function tabHref(string $html, string $label): string
    {
        $pattern = '/<a\s[^>]*href="([^"]+)"[^>]*>\s*'.preg_quote($label, '/').'\s*</s';

        $this->assertMatchesRegularExpression($pattern, $html, "No link labelled \"{$label}\" was rendered.");

        preg_match($pattern, $html, $match);

        return html_entity_decode($match[1]);
    }

    #[DataProvider('nonAdminProvider')]
    public function test_the_trashed_control_is_absent_for_a_non_admin(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('parts.index'))
            ->assertOk()
            ->assertDontSee(__('parts.trashed.show'));
    }

    public function test_the_trashed_control_is_offered_to_an_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.index'))
            ->assertOk()
            ->assertSee(__('parts.trashed.show'));
    }

    public function test_restoring_returns_the_part_with_its_stock_count_unchanged(): void
    {
        $part = Part::factory()->negativeStock()->deleted()->create(['sku' => 'WB-24']);

        $redirect = $this->actingAs(User::factory()->admin()->create())
            ->patch(route('parts.restore', $part))
            ->assertRedirect(route('parts.show', $part));

        $this->followRedirects($redirect)->assertOk()->assertSee('WB-24');

        $fresh = $part->fresh();

        $this->assertFalse($fresh->trashed());
        $this->assertSame(-2, $fresh->quantity_in_stock);

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.index'))
            ->assertOk();

        $this->assertSame(['WB-24'], $response->viewData('parts')->pluck('sku')->all());
    }

    public function test_a_deleted_row_offers_restore_and_not_edit(): void
    {
        $part = Part::factory()->deleted()->create(['sku' => 'WB-24']);

        $body = $this->actingAs(User::factory()->admin()->create())
            ->get(route('parts.index', ['trashed' => 'with']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('parts.restore', $part), $body);
        $this->assertStringNotContainsString(route('parts.edit', $part), $body);
    }
}
