<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobBoardMineUrlTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string PLATE = 'BG-MINE-1';

    private function myJobs(User $user, int $count): void
    {
        $vehicle = Vehicle::factory()->create(['registration_plate' => self::PLATE]);

        Job::factory()
            ->received()
            ->for($vehicle)
            ->count($count)
            ->create()
            ->each(fn (Job $job) => JobItem::factory()->forJob($job)->performedBy($user)->create());
    }

    /**
     * @param  array<string, string|int>  $query
     */
    private function board(User $user, array $query): TestResponse
    {
        return $this->actingAs($user)->get(route('jobs.index', $query))->assertOk();
    }

    private function xpath(TestResponse $response): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());

        return new DOMXPath($dom);
    }

    /**
     * @return list<DOMElement>
     */
    private function elements(DOMXPath $xpath, string $expression): array
    {
        $found = [];

        foreach ($xpath->query($expression) as $node) {
            $found[] = $node;
        }

        return $found;
    }

    /**
     * @return array<string, string>
     */
    private function queryOf(DOMElement $link): array
    {
        parse_str((string) parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);

        return $query;
    }

    private function literal(string $text): string
    {
        return '"'.$text.'"';
    }

    /**
     * @param  list<DOMElement>  $links
     */
    private function assertEveryLinkKeepsMine(array $links, int $expectedCount): void
    {
        $this->assertCount($expectedCount, $links);

        foreach ($links as $link) {
            $this->assertSame('1', $this->queryOf($link)['mine'] ?? null, $link->getAttribute('href'));
        }
    }

    public function test_every_status_tab_keeps_mine(): void
    {
        $user = User::factory()->mechanic()->create();
        $this->myJobs($user, 1);

        $tabs = $this->elements(
            $this->xpath($this->board($user, ['mine' => 1])),
            '//nav[@aria-label='.$this->literal(__('jobs.board.tabs_label')).']//a',
        );

        $this->assertEveryLinkKeepsMine($tabs, count(JobStatus::cases()) + 1);
    }

    public function test_every_status_tab_omits_mine_when_it_is_off(): void
    {
        $user = User::factory()->mechanic()->create();
        $this->myJobs($user, 1);

        $tabs = $this->elements(
            $this->xpath($this->board($user, [])),
            '//nav[@aria-label='.$this->literal(__('jobs.board.tabs_label')).']//a',
        );

        $this->assertNotEmpty($tabs);

        foreach ($tabs as $tab) {
            $this->assertArrayNotHasKey('mine', $this->queryOf($tab));
        }
    }

    public function test_every_sort_header_keeps_mine(): void
    {
        $user = User::factory()->mechanic()->create();
        $this->myJobs($user, 1);

        $headers = $this->elements($this->xpath($this->board($user, ['mine' => 1])), '//th/a');

        $this->assertEveryLinkKeepsMine($headers, 4);
    }

    public function test_the_search_form_carries_one_hidden_mine_field_only_when_on(): void
    {
        $user = User::factory()->mechanic()->create();
        $this->myJobs($user, 1);

        $on = $this->elements($this->xpath($this->board($user, ['mine' => 1])), '//form[@role="search"]//*[@name="mine"]');

        $this->assertCount(1, $on);
        $this->assertSame('hidden', $on[0]->getAttribute('type'));
        $this->assertSame('1', $on[0]->getAttribute('value'));

        $off = $this->elements($this->xpath($this->board($user, [])), '//form[@role="search"]//*[@name="mine"]');

        $this->assertCount(0, $off);
    }

    public function test_the_search_forms_clear_search_keeps_mine(): void
    {
        $user = User::factory()->mechanic()->create();
        $this->myJobs($user, 1);

        $clear = $this->elements(
            $this->xpath($this->board($user, ['mine' => 1, 'search' => 'MINE'])),
            '//form[@role="search"]//a',
        );

        $this->assertEveryLinkKeepsMine($clear, 1);
        $this->assertArrayNotHasKey('search', $this->queryOf($clear[0]));
    }

    public function test_pagination_links_keep_mine_on_page_two(): void
    {
        $user = User::factory()->mechanic()->create();
        $this->myJobs($user, 26);
        Job::factory()->count(3)->create();

        $response = $this->board($user, ['mine' => 1, 'page' => 2]);

        $this->assertSame(26, $response->viewData('jobs')->total());
        $this->assertCount(1, $response->viewData('jobs')->items());

        $links = $this->elements($this->xpath($response), '//nav[@role="navigation"]//a');

        $this->assertNotEmpty($links);

        foreach ($links as $link) {
            $this->assertSame('1', $this->queryOf($link)['mine'] ?? null, $link->getAttribute('href'));
        }
    }

    public function test_the_no_matches_clear_search_keeps_mine(): void
    {
        $user = User::factory()->mechanic()->create();
        $this->myJobs($user, 1);

        $response = $this->board($user, ['mine' => 1, 'search' => 'zz-no-such-plate', 'status' => JobStatus::Received->value]);
        $response->assertSeeText(__('jobs.board.empty.no_matches.title'));

        $clear = $this->elements(
            $this->xpath($response),
            '//a[normalize-space()='.$this->literal(__('app.actions.clear_search')).'][not(ancestor::form)]',
        );

        $this->assertEveryLinkKeepsMine($clear, 1);
        $this->assertSame(JobStatus::Received->value, $this->queryOf($clear[0])['status'] ?? null);
        $this->assertArrayNotHasKey('search', $this->queryOf($clear[0]));
    }

    public function test_the_none_mine_link_drops_mine_and_keeps_status_sort_and_direction(): void
    {
        $user = User::factory()->mechanic()->create();
        Job::factory()->received()->create();

        $response = $this->board($user, ['mine' => 1, 'status' => JobStatus::Completed->value, 'sort' => 'plate', 'direction' => 'asc']);

        $links = $this->elements(
            $this->xpath($response),
            '//a[normalize-space()='.$this->literal(__('jobs.board.empty.none_mine.clear')).']',
        );

        $this->assertCount(1, $links);
        $this->assertSame(
            ['status' => JobStatus::Completed->value, 'sort' => 'plate', 'direction' => 'asc'],
            $this->queryOf($links[0]),
        );
    }

    private function toggle(DOMXPath $xpath): DOMElement
    {
        $buttons = $this->elements($xpath, '//form[@method="GET"]/button[@aria-pressed]');

        $this->assertCount(1, $buttons);

        return $buttons[0];
    }

    /**
     * @return array<string, string>
     */
    private function toggleSubmission(DOMElement $button): array
    {
        $form = $button->parentNode;

        $this->assertInstanceOf(DOMElement::class, $form);
        $this->assertSame('form', $form->nodeName);
        $this->assertSame('GET', strtoupper($form->getAttribute('method')));
        $this->assertSame(route('jobs.index'), $form->getAttribute('action'));

        $fields = [];

        foreach ($form->getElementsByTagName('input') as $input) {
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }

        if ($button->hasAttribute('name')) {
            $fields[$button->getAttribute('name')] = $button->getAttribute('value');
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function classesOf(DOMElement $element): array
    {
        return preg_split('/\s+/', trim($element->getAttribute('class')));
    }

    public function test_the_off_toggle_submits_mine_and_keeps_every_other_filter(): void
    {
        $user = User::factory()->mechanic()->create();
        $this->myJobs($user, 1);

        $query = ['status' => JobStatus::Received->value, 'search' => 'MINE', 'sort' => 'plate', 'direction' => 'asc', 'page' => 1];
        $button = $this->toggle($this->xpath($this->board($user, $query)));

        $this->assertSame('false', $button->getAttribute('aria-pressed'));
        $this->assertSame('mine', $button->getAttribute('name'));

        $submission = $this->toggleSubmission($button);

        $this->assertSame(
            ['status' => JobStatus::Received->value, 'search' => 'MINE', 'sort' => 'plate', 'direction' => 'asc', 'mine' => '1'],
            $submission,
        );

        $this->assertTrue($this->board($user, $submission)->viewData('filters')['mine']);
    }

    public function test_the_on_toggle_has_no_name_so_submitting_it_turns_mine_off(): void
    {
        $user = User::factory()->mechanic()->create();
        $this->myJobs($user, 1);

        $query = ['status' => JobStatus::Received->value, 'search' => 'MINE', 'sort' => 'plate', 'direction' => 'asc', 'mine' => 1];
        $button = $this->toggle($this->xpath($this->board($user, $query)));

        $this->assertSame('true', $button->getAttribute('aria-pressed'));
        $this->assertFalse($button->hasAttribute('name'));

        $submission = $this->toggleSubmission($button);

        $this->assertSame(
            ['status' => JobStatus::Received->value, 'search' => 'MINE', 'sort' => 'plate', 'direction' => 'asc'],
            $submission,
        );

        $filters = $this->board($user, $submission)->viewData('filters');

        $this->assertFalse($filters['mine']);
        $this->assertSame('MINE', $filters['search']);
    }

    public function test_the_toggle_carries_the_applied_search_not_the_page(): void
    {
        $user = User::factory()->mechanic()->create();

        $submission = $this->toggleSubmission($this->toggle($this->xpath($this->board($user, ['page' => 3]))));

        $this->assertArrayNotHasKey('page', $submission);
        $this->assertArrayNotHasKey('search', $submission);
        $this->assertArrayNotHasKey('status', $submission);
    }

    public function test_the_toggles_accessible_name_begins_with_the_visible_label(): void
    {
        $user = User::factory()->mechanic()->create();

        $button = $this->toggle($this->xpath($this->board($user, [])));
        $name = preg_replace('/\s+/', ' ', trim($button->textContent));

        $this->assertStringStartsWith(__('jobs.board.mine').' ', $name);
        $this->assertStringEndsWith(__('jobs.board.mine_hint'), $name);

        $hint = $this->elements($this->xpath($this->board($user, [])), '//form[@method="GET"]/button[@aria-pressed]/span[contains(@class, "sr-only")]');

        $this->assertCount(1, $hint);
        $this->assertSame(__('jobs.board.mine_hint'), trim($hint[0]->textContent));
    }

    public function test_the_pressed_toggle_uses_the_active_tab_treatment_and_the_unpressed_one_the_outline(): void
    {
        $user = User::factory()->mechanic()->create();

        $on = $this->classesOf($this->toggle($this->xpath($this->board($user, ['mine' => 1]))));
        $off = $this->classesOf($this->toggle($this->xpath($this->board($user, []))));

        foreach (['bg-surface-dark', 'text-text-inverse', 'border-transparent'] as $class) {
            $this->assertContains($class, $on);
            $this->assertNotContains($class, $off);
        }

        foreach (['border-border', 'text-text-soft', 'hover:bg-surface-muted'] as $class) {
            $this->assertContains($class, $off);
            $this->assertNotContains($class, $on);
        }

        $this->assertNotContains('bg-brand', $on);
        $this->assertNotContains('bg-brand', $off);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roles(): array
    {
        return [
            'mechanic' => ['mechanic'],
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('roles')]
    public function test_the_toggle_renders_for_every_role(string $role): void
    {
        $user = User::factory()->{$role}()->create();

        $button = $this->toggle($this->xpath($this->board($user, [])));

        $this->assertSame('false', $button->getAttribute('aria-pressed'));
    }
}
