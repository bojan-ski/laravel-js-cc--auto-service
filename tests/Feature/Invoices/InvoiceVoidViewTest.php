<?php

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceVoidViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function issued(): Invoice
    {
        $job = Job::factory()->completed()->create();

        JobItem::factory()->forJob($job)->create(['quantity' => 2, 'unit_price' => 3000]);

        return app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());
    }

    private function confirmation(Invoice $invoice): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get(route('invoices.void.create', $invoice))
            ->assertOk()
            ->getContent();
    }

    public function test_an_admin_sees_the_void_control_on_a_voidable_invoice(): void
    {
        $invoice = $this->issued();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('href="'.route('invoices.void.create', $invoice).'"', false)
            ->assertSee(__('invoices.void.action'));
    }

    /**
     * A receptionist may issue an invoice and mark it paid, and may not undo
     * either. The control is absent, not merely disabled.
     */
    public function test_a_receptionist_sees_no_void_control(): void
    {
        $invoice = $this->issued();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee('href="'.route('invoices.void.create', $invoice).'"', false)
            ->assertDontSee(__('invoices.void.action'));
    }

    public function test_a_void_invoice_shows_no_void_control_for_anyone(): void
    {
        $invoice = Invoice::factory()->void()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee('href="'.route('invoices.void.create', $invoice).'"', false);
    }

    /**
     * WCAG 2.5.3 Label in Name: the aria-label overrides the visible text as the
     * control's accessible name, so the visible label must survive inside it as
     * a contiguous substring or speech input cannot address the control.
     */
    public function test_the_void_control_accessible_name_contains_its_visible_label(): void
    {
        $invoice = $this->issued();

        $visible = __('invoices.void.action');
        $accessible = __('invoices.void.action_for', ['number' => $invoice->number]);

        $this->assertStringContainsString($visible, $accessible);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('aria-label="'.e($accessible).'"', false);
    }

    public function test_the_confirmation_names_the_job_and_its_total(): void
    {
        $invoice = $this->issued();
        $html = $this->confirmation($invoice);

        $this->assertStringContainsString($invoice->job->reference, $html);
        $this->assertStringContainsString(route('jobs.show', $invoice->job), $html);
        $this->assertStringContainsString(number_format((float) $invoice->total, 2), $html);
        $this->assertStringContainsString($invoice->number, $html);
    }

    /**
     * WCAG 2.4.4 Link Purpose: a bare reference string in a links list says
     * nothing about where it goes. Every other place this codebase renders this
     * link — x-invoices.details and x-jobs.lock-banner — labels it first, and the
     * label is asserted to sit immediately before the anchor rather than merely
     * somewhere on the page.
     */
    public function test_the_job_link_carries_a_visible_label(): void
    {
        $invoice = $this->issued();
        $html = $this->confirmation($invoice);

        $this->assertMatchesRegularExpression(
            '/'.preg_quote(__('invoices.show.job'), '/').'\s*<\/span>\s*<a\s+href="'
                .preg_quote(route('jobs.show', $invoice->job), '/').'"/',
            $html,
            'The job reference link must be preceded by its label.',
        );
    }

    public function test_the_confirmation_states_all_four_consequences(): void
    {
        $invoice = $this->issued();
        $html = $this->confirmation($invoice);

        foreach ([
            __('invoices.void.consequences.kept'),
            __('invoices.void.consequences.number', ['number' => $invoice->number]),
            __('invoices.void.consequences.stock'),
            __('invoices.void.consequences.reissue'),
        ] as $consequence) {
            $this->assertStringContainsString(e($consequence), $html, "Missing consequence: {$consequence}");
        }
    }

    /**
     * The spec's own sketch promised that voiding lets the line items "be edited
     * again". They cannot: the job is still completed, and three layers refuse
     * the write until it is reopened. The copy has to say what actually happens.
     */
    public function test_the_confirmation_says_the_job_stays_completed_and_must_be_reopened(): void
    {
        $invoice = $this->issued();
        $html = $this->confirmation($invoice);

        $this->assertStringContainsString(
            e(__('invoices.void.warning_title', ['reference' => $invoice->job->reference])),
            $html,
        );

        $this->assertStringContainsString(e(__('invoices.void.warning_body')), $html);
    }

    /**
     * Voiding a settled invoice is a different decision from voiding an unpaid
     * one, so the notice is asserted by its date-independent prefix — the date
     * itself would make an absence check pass for the wrong reason.
     */
    public function test_the_paid_notice_appears_only_when_the_invoice_is_paid(): void
    {
        $prefix = Str::before(__('invoices.void.paid_notice', ['date' => '@@DATE@@']), '@@DATE@@');

        $this->assertNotSame('', $prefix, 'The notice must carry text before its date.');

        $this->assertStringNotContainsString(e($prefix), $this->confirmation($this->issued()));

        $paid = app(InvoiceService::class)->markPaid($this->issued());

        $this->assertStringContainsString(
            e(__('invoices.void.paid_notice', ['date' => $paid->fresh()->paid_at->isoFormat('D MMM YYYY')])),
            $this->confirmation($paid),
        );
    }

    /**
     * A screen reader in forms mode reaches the submit button without reading
     * the surrounding prose, so the consequences must be tied to the control.
     */
    public function test_the_submit_button_is_described_by_the_warning(): void
    {
        $html = $this->confirmation($this->issued());

        $this->assertStringContainsString('id="void-warning"', $html);
        $this->assertStringContainsString('role="note"', $html);

        $this->assertMatchesRegularExpression(
            '/<button[^>]*\baria-describedby="void-warning"/',
            $html,
            'The description must be on the submit button itself — a page-wide containment check '
                .'would pass with the attribute moved to the Cancel link.',
        );

        $this->assertLessThan(
            strpos($html, 'aria-describedby="void-warning"'),
            strpos($html, 'id="void-warning"'),
            'The described element must precede the control that references it.',
        );
    }

    public function test_the_confirmation_posts_a_spoofed_patch_to_the_void_route(): void
    {
        $invoice = $this->issued();
        $html = $this->confirmation($invoice);

        $this->assertStringContainsString('action="'.route('invoices.void', $invoice).'"', $html);
        $this->assertStringContainsString('name="_method" value="PATCH"', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }

    /**
     * The confirmation is a page precisely so it works without JavaScript. A
     * data-confirm attribute here would mean the real gate is a window.confirm()
     * that never fires with JS off.
     */
    public function test_the_confirmation_page_relies_on_no_javascript(): void
    {
        $html = $this->confirmation($this->issued());

        $this->assertStringNotContainsString('data-confirm', $html);
    }

    /**
     * Specs 11 and 18 both shipped heading defects that no functional test could
     * see, so the confirmation's whole outline is asserted rather than just the
     * presence of an h1.
     */
    public function test_the_confirmation_page_has_exactly_one_top_level_heading(): void
    {
        $html = $this->confirmation($this->issued());

        preg_match_all('/<(h[1-6])\b[^>]*>(.*?)<\/\1>/s', $html, $headings, PREG_SET_ORDER);

        $levels = array_map(fn (array $match): string => $match[1], $headings);

        $this->assertSame(['h1'], $levels, 'The confirmation is one page with one heading.');
        $this->assertStringContainsString(__('invoices.void.heading'), strip_tags($headings[0][2]));
    }

    public function test_the_cancel_link_returns_to_the_invoice_without_writing(): void
    {
        $invoice = $this->issued();
        $html = $this->confirmation($invoice);

        $this->assertStringContainsString('href="'.route('invoices.show', $invoice).'"', $html);
        $this->assertStringContainsString(__('invoices.void.cancel'), $html);
    }

    /**
     * The two labels are asserted as whole <dt> elements rather than as loose
     * text: "Voided" is a substring of "Voided by", so a containment check would
     * pass with the date row deleted.
     */
    public function test_a_voided_invoice_shows_when_it_was_voided_and_by_whom(): void
    {
        $invoice = $this->issued();
        $admin = User::factory()->admin()->create(['name' => 'Ana Đorđević']);

        app(InvoiceService::class)->void($invoice, $admin);

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<dt[^>]*>\s*'.preg_quote(__('invoices.void.voided'), '/').'\s*<\/dt>/',
            $html,
            'The Voided date row is missing.',
        );

        $this->assertMatchesRegularExpression(
            '/<dt[^>]*>\s*'.preg_quote(__('invoices.void.voided_by'), '/').'\s*<\/dt>/',
            $html,
            'The Voided by row is missing.',
        );

        $this->assertStringContainsString('Ana Đorđević', $html);
        $this->assertStringContainsString($invoice->fresh()->voided_at->isoFormat('D MMM YYYY'), $html);
    }

    public function test_the_voided_by_name_renders_when_that_admin_has_since_been_soft_deleted(): void
    {
        $invoice = $this->issued();
        $admin = User::factory()->admin()->create(['name' => 'Ana Đorđević']);

        app(InvoiceService::class)->void($invoice, $admin);

        $admin->delete();

        $this->assertSoftDeleted($admin);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Ana Đorđević')
            ->assertSee(__('app.deleted'));
    }

    public function test_a_live_invoice_shows_neither_void_row(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.show', $this->issued()))
            ->assertOk()
            ->assertDontSee(__('invoices.void.voided_by'))
            ->assertDontSee(__('invoices.void.voided'));
    }
}
