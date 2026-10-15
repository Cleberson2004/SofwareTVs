<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceCommandRequest extends FormRequest
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
            'type' => ['required', Rule::in(config('tv_agent.allowed_commands'))],
            'payload' => ['sometimes', 'array'],
            'priority' => ['sometimes', 'integer', 'between:1,10'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'confirmed' => [Rule::requiredIf(fn (): bool => in_array($this->string('type')->toString(), config('tv_agent.critical_commands'), true)), 'accepted'],
        ];
    }
}
