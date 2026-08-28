<?php

namespace Tests\Support;

use App\Models\Job;

trait AssertsJobLinks
{
    /**
     * The full href, so that /jobs/1 never matches /jobs/12.
     */
    protected function jobLink(Job $job): string
    {
        return 'href="'.route('jobs.show', $job).'"';
    }
}
