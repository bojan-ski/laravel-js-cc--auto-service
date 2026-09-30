<?php

namespace App\Http\Controllers\JobTemplates;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobTemplates\StoreJobTemplateItemRequest;
use App\Http\Requests\JobTemplates\UpdateJobTemplateItemRequest;
use App\Models\JobTemplate;
use App\Models\JobTemplateItem;
use App\Services\JobTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class JobTemplateItemController extends Controller
{
    public function __construct(
        private JobTemplateService $templates,
    ) {}

    public function store(StoreJobTemplateItemRequest $request, JobTemplate $jobTemplate): RedirectResponse
    {
        Gate::authorize('update', $jobTemplate);

        $this->templates->addItem($jobTemplate, $request->type(), $request->lineAttributes());

        return $this->redirectToTemplate($jobTemplate, __('templates.flash.line_added'));
    }

    public function update(UpdateJobTemplateItemRequest $request, JobTemplate $jobTemplate, JobTemplateItem $item): RedirectResponse
    {
        Gate::authorize('update', $jobTemplate);

        $this->templates->updateItem($item, $request->lineAttributes());

        return $this->redirectToTemplate($jobTemplate, __('templates.flash.line_updated'));
    }

    public function destroy(JobTemplate $jobTemplate, JobTemplateItem $item): RedirectResponse
    {
        Gate::authorize('update', $jobTemplate);

        $this->templates->removeItem($item);

        return $this->redirectToTemplate($jobTemplate, __('templates.flash.line_removed'));
    }

    private function redirectToTemplate(JobTemplate $template, string $message): RedirectResponse
    {
        return redirect()
            ->route('job-templates.edit', $template)
            ->with('success', $message);
    }
}
