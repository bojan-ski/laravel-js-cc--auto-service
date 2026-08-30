<?php

namespace Tests\Support;

use App\Models\Job;
use App\Models\User;
use App\Services\JobService;

trait ResolvesJobService
{
    protected function service(): JobService
    {
        return app(JobService::class);
    }

    /**
     * Dispatch a transition by name. Only cancel() takes an actor, because only
     * cancel() moves stock, so a data-provider driven test cannot call all six
     * uniformly.
     */
    protected function transitionJob(string $transition, Job $job, ?User $actor = null): Job
    {
        return $transition === 'cancel'
            ? $this->service()->cancel($job, $actor ?? User::factory()->receptionist()->create())
            : $this->service()->{$transition}($job);
    }
}
