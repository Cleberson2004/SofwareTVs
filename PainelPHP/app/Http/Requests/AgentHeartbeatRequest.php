<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AgentHeartbeatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'agent_version' => ['required', 'string', 'max:50'],
            'windows_version' => ['nullable', 'string', 'max:255'],
            'gpu_name' => ['nullable', 'string', 'max:255'],
            'driver_version' => ['nullable', 'string', 'max:100'],
            'display_provider' => ['required', 'in:Mock,NvApi'],
            'simulation_mode' => ['required', 'boolean'],
            'vlc_status' => ['required', 'array'],
            'mosaic_status' => ['nullable', 'array'],
            'desktop_width' => ['nullable', 'integer', 'min:1'],
            'desktop_height' => ['nullable', 'integer', 'min:1'],
            'screens_detected' => ['required', 'integer', 'min:0'],
            'cpu_usage' => ['nullable', 'numeric', 'between:0,100'],
            'memory_usage' => ['nullable', 'numeric', 'between:0,100'],
            'free_disk_space' => ['nullable', 'integer', 'min:0'],
            'last_command_id' => ['nullable', 'uuid'],
            'diagnostics' => ['nullable', 'array'],
        ];
    }
}
