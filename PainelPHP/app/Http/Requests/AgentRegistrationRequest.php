<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AgentRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return hash_equals((string) config('tv_agent.enrollment_key'), (string) $this->header('X-Enrollment-Key'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_uuid' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'agent_version' => ['required', 'string', 'max:50'],
            'windows_version' => ['nullable', 'string', 'max:255'],
            'gpu_name' => ['nullable', 'string', 'max:255'],
            'driver_version' => ['nullable', 'string', 'max:100'],
            'display_provider' => ['required', 'in:Mock,NvApi'],
            'simulation_mode' => ['required', 'boolean'],
        ];
    }
}
