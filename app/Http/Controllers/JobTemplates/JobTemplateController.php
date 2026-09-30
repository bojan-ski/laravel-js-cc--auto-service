<?php

namespace App\Http\Controllers\JobTemplates;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobTemplates\JobTemplateEditRequest;
use App\Http\Requests\JobTemplates\JobTemplateRequest;
use App\Models\JobTemplate;
use App\Services\JobTemplateService;
use App\Services\PartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JobTemplateController extends Controller
{
    public function __construct(
        private JobTemplateService $templates,
        private PartService $parts,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', JobTemplate::class);

        $templates = $this->templates->paginate(Gate::allows('viewDeleted', JobTemplate::class));

        return view('pages.job-templates.index', [
            'templates' => $templates,
            'showActions' => $templates->getCollection()->contains->trashed(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', JobTemplate::class);

        return view('pages.job-templates.create');
    }

    public function store(JobTemplateRequest $request): RedirectResponse
    {
        Gate::authorize('create', JobTemplate::class);

        $template = $this->templates->create($request->validated());

        return redirect()->route('job-templates.edit', $template)
            ->with('success', __('templates.flash.created', ['name' => $template->name]));
    }

    public function edit(JobTemplateEditRequest $request, JobTemplate $jobTemplate): View
    {
        Gate::authorize('update', $jobTemplate);

        $lines = $this->templates->linesFor($jobTemplate);
        $editing = $lines->firstWhere('id', $request->editingItemId());
        $adding = $request->addingType();
        $showsParts = $editing === null ? $adding === 'part' : $editing->isPart();

        return view('pages.job-templates.edit', [
            'template' => $jobTemplate,
            'lines' => $lines,
            'editing' => $editing,
            'adding' => $adding,
            'partOptions' => $showsParts ? $this->parts->pickerOptions() : [],
            'hasDeletedPart' => $lines->contains->hasDeletedPart(),
        ]);
    }

    public function update(JobTemplateRequest $request, JobTemplate $jobTemplate): RedirectResponse
    {
        Gate::authorize('update', $jobTemplate);

        $this->templates->update($jobTemplate, $request->validated());

        return redirect()->route('job-templates.edit', $jobTemplate)
            ->with('success', __('templates.flash.updated', ['name' => $jobTemplate->name]));
    }

    public function destroy(JobTemplate $jobTemplate): RedirectResponse
    {
        Gate::authorize('delete', $jobTemplate);

        $this->templates->delete($jobTemplate);

        return redirect()->route('job-templates.index')
            ->with('success', __('templates.flash.deleted', ['name' => $jobTemplate->name]));
    }

    public function restore(JobTemplate $jobTemplate): RedirectResponse
    {
        Gate::authorize('restore', $jobTemplate);

        $this->templates->restore($jobTemplate);

        return redirect()->route('job-templates.index')
            ->with('success', __('templates.flash.restored', ['name' => $jobTemplate->name]));
    }
}
