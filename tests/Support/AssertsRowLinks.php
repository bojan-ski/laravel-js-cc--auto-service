<?php

namespace Tests\Support;

trait AssertsRowLinks
{
    /**
     * The row is positioned and its one link stretches over it, so the whole
     * row is clickable; only a browser can prove the hit-testing itself.
     */
    protected function assertRowIsOneLinkTo(string $html, string $url): void
    {
        $href = 'href="'.e($url).'"';
        $at = strpos($html, $href);

        $this->assertNotFalse($at, "No link to {$url} was rendered.");

        $start = strrpos(substr($html, 0, $at), '<tr');
        $end = strpos($html, '</tr>', $at);

        $this->assertNotFalse($start, "The link to {$url} is not inside a table row.");

        $row = substr($html, $start, $end - $start);

        $this->assertMatchesRegularExpression('/^<tr\b[^>]*class="[^"]*\brelative\b/', $row);
        $this->assertMatchesRegularExpression('/<a\b[^>]*'.preg_quote($href, '/').'[^>]*after:absolute after:inset-0/', $row);
        $this->assertSame(1, preg_match_all('/<a\b/', $row), 'The row carries exactly one link.');
    }
}
