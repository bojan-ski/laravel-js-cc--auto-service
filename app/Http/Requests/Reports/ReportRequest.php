<?php

namespace App\Http\Requests\Reports;

use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $redirectRoute = 'reports.index';

    public function authorize(): bool
    {
        return $this->user()->can('viewReports', Invoice::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from' => __('reports.period.from'),
            'to' => __('reports.period.to'),
        ];
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public function period(): array
    {
        $from = $this->validated('from');
        $to = $this->validated('to');

        if ($from === null || $to === null) {
            return [
                'from' => CarbonImmutable::today()->startOfMonth(),
                'to' => CarbonImmutable::today(),
            ];
        }

        return [
            'from' => CarbonImmutable::createFromFormat('!Y-m-d', $from),
            'to' => CarbonImmutable::createFromFormat('!Y-m-d', $to),
        ];
    }
}
