<?php

namespace App\Http\Requests\Jobs;

use App\Http\Requests\Vehicles\VehicleRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNextServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('scheduleNextService', $this->route('job'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return VehicleRequest::serviceDateRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return VehicleRequest::serviceDateAttributes();
    }
}
