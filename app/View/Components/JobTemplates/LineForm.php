<?php

namespace App\View\Components\JobTemplates;

use App\Enums\JobItemType;
use App\Models\JobTemplate;
use App\Models\JobTemplateItem;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class LineForm extends Component
{
    /**
     * @param  array<int, string>  $partOptions
     */
    public function __construct(
        public JobTemplate $template,
        public ?JobTemplateItem $item = null,
        public string $adding = 'labor',
        public array $partOptions = [],
    ) {}

    public function isEdit(): bool
    {
        return $this->item !== null;
    }

    public function type(): JobItemType
    {
        return $this->item?->type ?? JobItemType::from($this->adding);
    }

    public function isPart(): bool
    {
        return $this->type() === JobItemType::Part;
    }

    public function hasNoParts(): bool
    {
        return $this->isPart() && $this->partOptions === [];
    }

    public function action(): string
    {
        return $this->isEdit()
            ? route('job-templates.items.update', ['jobTemplate' => $this->template, 'item' => $this->item])
            : route('job-templates.items.store', $this->template);
    }

    public function heading(): string
    {
        if ($this->isEdit()) {
            return __('templates.lines.edit.title_for', ['description' => $this->item->displayName()]);
        }

        return $this->isPart()
            ? __('templates.lines.add.part_title')
            : __('templates.lines.add.labor_title');
    }

    public function submitLabel(): string
    {
        return $this->isEdit() ? __('templates.lines.edit.save') : __('templates.lines.add.submit');
    }

    public function quantityLabel(): string
    {
        return $this->isPart() ? __('templates.lines.add.units') : __('templates.lines.add.hours');
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
        if ($this->item !== null) {
            return $this->type()->formatQuantity($this->item->quantity);
        }

        return $this->isPart() ? '1' : null;
    }

    public function selectedPart(): ?int
    {
        return $this->item === null || $this->item->hasDeletedPart() ? null : $this->item->part_id;
    }

    public function render(): View
    {
        return view('components.job-templates.line-form');
    }
}
