<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Stub Vite so rendering a layout never depends on a built manifest.
     *
     * `public/build` is not committed, so an unguarded `@vite` would throw a
     * ViteException on a fresh clone. `@fonts` needs no stub — it degrades to
     * an empty string when its manifest is absent.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
