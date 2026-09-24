<?php

namespace Tests\Support;

use Illuminate\Mail\Mailable;

trait AssertsMailRows
{
    /**
     * The HTML body lays each detail out as a label cell followed by its value
     * cell, so a label and a value are only proven to belong together when
     * they are adjacent cells of one row.
     */
    protected function assertHtmlRow(Mailable $mailable, string $label, string $value): void
    {
        $this->assertMatchesRegularExpression(
            '/>\s*'.preg_quote(e($label), '/').'\s*<\/td>\s*<td\b[^>]*>\s*'.preg_quote(e($value), '/').'\s*<\/td>/',
            $mailable->render(),
            "The HTML body has no row pairing '{$label}' with '{$value}'.",
        );
    }

    protected function assertNoHtmlRowLabelled(Mailable $mailable, string $label): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/>\s*'.preg_quote(e($label), '/').'\s*<\/td>/',
            $mailable->render(),
        );
    }
}
