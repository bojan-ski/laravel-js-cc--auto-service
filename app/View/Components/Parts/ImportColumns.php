<?php

namespace App\View\Components\Parts;

use App\Enums\PartImportColumn;
use App\Models\Part;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;

class ImportColumns extends Component
{
    /**
     * @var list<array{header: string, requirement: string, description: string}>
     */
    public array $rows;

    public function __construct()
    {
        $columns = PartImportColumn::submittableWith(Gate::allows('viewCostPrice', Part::class));

        $this->rows = array_map(fn (PartImportColumn $column): array => [
            'header' => $column->value,
            'requirement' => $this->requirementLabel($column),
            'description' => $column->description(),
        ], $columns);
    }

    private function requirementLabel(PartImportColumn $column): string
    {
        return match (true) {
            $column->isAlwaysRequired() => __('parts.import.required_always'),
            $column->isRequiredForNewPart() => __('parts.import.required_new'),
            default => __('parts.import.required_never'),
        };
    }

    public function render(): View
    {
        return view('components.parts.import-columns');
    }
}
