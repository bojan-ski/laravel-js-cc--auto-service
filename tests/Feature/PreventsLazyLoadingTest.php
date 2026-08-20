<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class PreventsLazyLoadingTest extends TestCase
{
    public function test_lazy_loading_is_prevented_outside_production(): void
    {
        $this->assertFalse($this->app->isProduction());
        $this->assertTrue(Model::preventsLazyLoading());
    }
}
