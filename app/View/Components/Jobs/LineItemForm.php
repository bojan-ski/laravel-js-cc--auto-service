<?php

namespace App\View\Components\Jobs;

use App\Enums\JobItemType;
use App\Models\Job;
use App\Models\JobItem;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class LineItemForm extends Component
{
    /**
     * @param  array<int, string>  $userOptions
     */
    public function __construct(
        public Job $job,
        public ?JobItem $item = null,
        public bool $showPricing = false,
        public array $userOptions = [],
        public ?string $defaultRate = null,
    ) {}

    public function isEdit(): bool
    {
        return $this->item !== null;
    }

    public function type(): JobItemType
    {
        return $this->item?->type ?? JobItemType::Labor;
    }

    public function isPart(): bool
    {
        return $this->type() === JobItemType::Part;
    }

    public function action(): string
    {
        return $this->isEdit()
            ? route('job-items.update', $this->item)
            : route('jobs.items.store', $this->job);
    }

    public function heading(): string
    {
        return $this->isEdit()
            ? __('jobs.items.edit.title_for', ['description' => $this->item->description])
            : __('jobs.items.add.title');
    }

    public function submitLabel(): string
    {
        return $this->isEdit() ? __('jobs.items.edit.save') : __('jobs.items.add.submit');
    }

    public function quantityLabel(): string
    {
        return $this->isPart()
            ? __('jobs.items.add.quantity_units')
            : __('jobs.items.add.quantity');
    }

    public function quantityStep(): string
    {
        return $this->type()->quantityStep();
    }

    public function quantityMin(): string
    {
        return $this->type()->quantityMin();
    }

    public function quantityValue(): ?string
    {
        return $this->item === null
            ? null
            : $this->type()->formatQuantity($this->item->quantity);
    }

    public function descriptionPlaceholder(): string
    {
        return $this->isPart()
            ? __('jobs.items.add.description_placeholder_part')
            : __('jobs.items.add.description_placeholder');
    }

    public function rateLabel(): string
    {
        return $this->isPart() ? __('jobs.items.edit.price') : __('jobs.items.add.rate');
    }

    public function assigneePlaceholder(): string
    {
        return $this->isPart()
            ? __('jobs.items.add.who_unassigned')
            : __('jobs.items.add.who_placeholder');
    }

    public function render(): View
    {
        return view('components.jobs.line-item-form');
    }
}
