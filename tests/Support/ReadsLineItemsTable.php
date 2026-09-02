<?php

namespace Tests\Support;

use Illuminate\Testing\TestResponse;

/**
 * Price-column assertions on the job page must be scoped to the line-items
 * table head, never made against the whole document.
 *
 * "Price" is one of the 473 surnames in Faker's en_US list, and the job page
 * renders up to five generated names — the signed-in user in the nav, the
 * customer, the job's creator, and one per labor line. A whole-page
 * assertDontSee('Price') therefore fails roughly once in a hundred runs on a
 * customer or mechanic who happens to be called Price, and the matching
 * assertSee passes for the same wrong reason even when the column is gone.
 */
trait ReadsLineItemsTable
{
    protected function lineItemsTableHead(TestResponse $response): string
    {
        $html = $response->getContent();

        $this->assertSame(
            1,
            preg_match_all('/<thead\b/', $html),
            'This helper assumes the job page renders exactly one table.',
        );

        preg_match('/<thead\b.*?<\/thead>/s', $html, $head);

        $this->assertNotEmpty($head, 'The line-items table head did not render.');

        return $head[0];
    }
}
