<?php

namespace App\Http\Requests\Jobs;

use App\Enums\JobStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobIndexRequest extends FormRequest
{
    public const array SORTS = ['plate', 'customer', 'status', 'received'];

    public const string DEFAULT_SORT = 'received';

    public const string DEFAULT_DIRECTION = 'desc';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(JobStatus::class)],
            'search' => ['nullable', 'string', 'max:255'],
            'mine' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array{status: string, search: string, sort: string, direction: string, mine: bool}
     */
    public function filters(): array
    {
        $sort = $this->string('sort')->toString();
        $direction = $this->string('direction')->toString();

        return [
            'status' => $this->string('status')->toString(),
            'search' => $this->string('search')->trim()->toString(),
            'sort' => in_array($sort, self::SORTS, true) ? $sort : self::DEFAULT_SORT,
            'direction' => $direction === 'asc' ? 'asc' : self::DEFAULT_DIRECTION,
            'mine' => $this->boolean('mine'),
        ];
    }
}
