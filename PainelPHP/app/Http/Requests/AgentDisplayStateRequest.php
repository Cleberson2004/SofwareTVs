<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AgentDisplayStateRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
            'rows' => ['required', 'integer', 'min:1', 'max:16'],
            'columns' => ['required', 'integer', 'min:1', 'max:16'],
            'display_width' => ['required', 'integer', 'min:1'],
            'display_height' => ['required', 'integer', 'min:1'],
            'total_width' => ['required', 'integer', 'min:1'],
            'total_height' => ['required', 'integer', 'min:1'],
            'refresh_rate' => ['required', 'numeric', 'min:1'],
            'overlap_x' => ['required', 'integer'],
            'overlap_y' => ['required', 'integer'],
            'displays' => ['required', 'array'],
            'displays.*.position' => ['required', 'integer', 'min:1'],
            'displays.*.display_id' => ['required', 'string', 'max:255'],
            'displays.*.rotation' => ['required', 'integer', 'in:0,90,180,270'],
        ];
    }
}
