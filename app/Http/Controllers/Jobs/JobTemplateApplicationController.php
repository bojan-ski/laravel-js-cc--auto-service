<?php

namespace App\Http\Controllers\Jobs;

use App\Exceptions\JobTemplateHasDeletedPart;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Jobs\Concerns\FlashesStockWarning;
use App\Http\Controllers\Jobs\Concerns\RedirectsClosedJob;
use App\Http\Requests\Jobs\ApplyJobTemplateRequest;
use App\Models\Job;
use App\Services\JobItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class JobTemplateApplicationController extends Controller
{
    use FlashesStockWarning;
    use RedirectsClosedJob;

    public function __construct(
        private JobItemService $items,
    ) {}

    public function store(ApplyJobTemplateRequest $request, Job $job): RedirectResponse
    {
        Gate::authorize('createItem', $job);

        $template = $request->template();

        return $this->unlessJobClosed($job->getKey(), function () use ($request, $job, $template): RedirectResponse {
            try {
                $lines = $this->items->applyTemplate($job, $template, $request->user(), $request->mechanicId());
            } catch (JobTemplateHasDeletedPart $exception) {
                return redirect()->to(route('jobs.show', ['job' => $job, 'add' => 'template']).'#line-item-form')
                    ->withErrors(['job_template_id' => __('templates.apply.errors.deleted_part', ['part' => $exception->partName])])
                    ->withInput();
            }

            return redirect()->route('jobs.show', $job)->with([
                'success' => trans_choice('templates.apply.flash.applied', $lines->count(), ['name' => $template->name, 'count' => $lines->count()]),
            ] + $this->templateStockWarning($lines));
        });
    }
}
