<?php

namespace Tests\Feature\Quality;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\QaFixture;
use Tests\TestCase;

/**
 * The Quality sweeps only reach a branch the fixture builds. This pins that the
 * shop holds both a company and a private buyer on the surfaces the sweeps walk,
 * so a later reshuffle of QaFixture cannot quietly stop rendering either one.
 */
class QaFixtureBuyerBranchesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_fixture_renders_both_the_company_and_the_private_branch(): void
    {
        $shop = QaFixture::build();

        $this->assertTrue($shop->customer->isCompany(), 'customers.show and customers.edit walk this customer.');
        $this->assertFalse($shop->minimalCustomer->isCompany(), 'customers.show:minimal walks this customer.');

        $this->assertTrue($shop->unpaidInvoice->hasCompanyBuyer(), 'invoices.show:unpaid walks this invoice.');
        $this->assertFalse($shop->paidInvoice->hasCompanyBuyer(), 'invoices.show:paid walks this invoice.');
        $this->assertFalse($shop->invoiceWithTrashedCustomer->hasCompanyBuyer());

        $this->assertTrue($shop->completedJob->customer->isCompany(), 'jobs.invoice.create walks this job.');
    }
}
