<?php

namespace App\Http\Controllers\Jobs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\JobIndexRequest;
use App\Http\Requests\Jobs\JobShowRequest;
use App\Http\Requests\Jobs\StoreJobRequest;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobAttachment;
use App\Models\JobItem;
use App\Services\CustomerIntakeService;
use App\Services\JobService;
use App\Services\JobTemplateService;
use App\Services\PartService;
use App\Services\UserService;
use App\Services\VehicleService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JobController extends Controller
{
    public function __construct(
        private JobService $jobs,
        private VehicleService $vehicles,
        private UserService $users,
        private PartService $parts,
        private JobTemplateService $templates,
        private CustomerIntakeService $intake,
    ) {}

    public function index(JobIndexRequest $request): View
    {
        Gate::authorize('viewAny', Job::class);

        $filters = $request->filters();

        return view('pages.jobs.index', [
            'jobs' => $this->jobs->paginateFiltered($filters, $request->user()),
            'counts' => $this->jobs->countByStatus($filters, $request->user()),
            'filters' => $filters,
            'hasAny' => $this->jobs->hasAny(),
        ]);
    }

    public function create(Request $request): View
    {
        if (! $request->filled('appointment')) {
            Gate::authorize('create', Job::class);

            return view('pages.jobs.create', ['vehicleOptions' => $this->vehicles->pickerOptions()]);
        }

        $appointment = Appointment::query()
            ->with(['vehicle', 'customer', 'createdBy'])
            ->findOrFail($request->integer('appointment'));

        Gate::authorize('markArrived', $appointment);

        return view('pages.jobs.create', ['appointment' => $appointment]);
    }

    public function store(StoreJobRequest $request): RedirectResponse
    {
        $appointment = $request->appointment();

        return $appointment === null
            ? $this->storeWalkIn($request)
            : $this->storeFromAppointment($request, $appointment);
    }

    public function show(JobShowRequest $request, Job $job): View
    {
        Gate::authorize('view', $job);

        $user = $request->user();
        $showPricing = Gate::allows('viewPricing', Job::class);

        $job->load([
            'customer',
            'vehicle',
            'createdBy',
            'appointment',
            'items' => fn (HasMany $query) => $query->visibleTo($user)->with('user')->orderBy('id'),
            'generalAttachments' => fn (HasMany $query) => $query->with('uploadedBy'),
            'checkIn' => fn (HasOne $query) => $query->withCount('photos'),
        ]);

        $job->items->each(fn (JobItem $item) => $item->setRelation('job', $job));

        $editing = $this->editableItem($job, $request->editingItemId());
        $discounting = $editing === null ? $this->discountableItem($job, $request->discountingItemId()) : null;
        $adding = $request->addingType();
        $addPanel = $editing === null && $discounting === null && Gate::allows('createItem', $job) ? $adding : null;
        $showPartForm = $addPanel === 'part';
        $showTemplateForm = $addPanel === 'template';

        return view('pages.jobs.show', [
            'job' => $job,
            'invoice' => $this->activeInvoiceFor($job),
            'showPricing' => $showPricing,
            'subtotal' => $showPricing ? $job->subtotal() : null,
            'discountTotal' => $showPricing && $job->items->contains->hasDiscount() ? $job->discountTotal() : null,
            'editing' => $editing,
            'discounting' => $discounting,
            'adding' => $adding,
            'showPartForm' => $showPartForm,
            'showTemplateForm' => $showTemplateForm,
            'userOptions' => $user->isFrontOffice() ? $this->users->pickerOptions() : [],
            'partOptions' => $showPartForm ? $this->parts->pickerOptions() : [],
            'templateOptions' => $showTemplateForm ? $this->templates->pickerOptions() : [],
            'defaultRate' => $showPricing ? (string) config('shop.labor_rate') : null,
            'photos' => $job->generalAttachments->filter(fn (JobAttachment $a): bool => $a->isPhoto())->values(),
            'documents' => $job->generalAttachments->filter(fn (JobAttachment $a): bool => $a->isDocument())->values(),
        ]);
    }

    private function activeInvoiceFor(Job $job): ?Invoice
    {
        return $job->isLocked() && Gate::allows('viewAny', Invoice::class)
            ? $job->activeInvoice()->first()
            : null;
    }

    private function editableItem(Job $job, ?int $itemId): ?JobItem
    {
        if ($itemId === null) {
            return null;
        }

        $item = $job->items->firstWhere('id', $itemId);

        return $item !== null && Gate::allows('update', $item) ? $item : null;
    }

    private function discountableItem(Job $job, ?int $itemId): ?JobItem
    {
        if ($itemId === null) {
            return null;
        }

        $item = $job->items->firstWhere('id', $itemId);

        return $item !== null && Gate::allows('discount', $item) ? $item : null;
    }

    private function storeWalkIn(StoreJobRequest $request): RedirectResponse
    {
        Gate::authorize('create', Job::class);

        $job = $this->intake->openWalkIn($request->intake(), $request->user(), $request->jobAttributes());

        return redirect()
            ->route('jobs.show', $job)
            ->with($this->flashes($job));
    }

    private function storeFromAppointment(StoreJobRequest $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('markArrived', $appointment);

        $job = $this->jobs->createFromAppointment($appointment, $request->user(), $request->jobAttributes());

        return redirect()
            ->route('jobs.show', $job)
            ->with($this->flashes($job));
    }

    /**
     * @return array<string, string>
     */
    private function flashes(Job $job): array
    {
        $flashes = ['success' => __('jobs.flash.created', ['reference' => $job->reference])];

        if ($job->mileageInIsBelowVehicleMileage()) {
            $flashes['warning'] = __('jobs.flash.mileage_lower', [
                'entered' => number_format($job->mileage_in),
                'stored' => number_format($job->vehicle->mileage),
            ]);
        }

        return $flashes;
    }
}
