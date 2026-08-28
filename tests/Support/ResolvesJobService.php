<?php

namespace Tests\Support;

use App\Services\JobService;

trait ResolvesJobService
{
    protected function service(): JobService
    {
        return app(JobService::class);
    }
}
