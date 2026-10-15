<?php

namespace App\Http\Requests;

use App\Enums\AdjustmentMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NormalizeMediaRequest extends FormRequest
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
            'display_profile_id' => ['required', 'integer', 'exists:display_profiles,id'],
            'adjustment_mode' => ['required', Rule::enum(AdjustmentMode::class)],
            'target_scope' => ['nullable', 'in:screen,wall'],
            'tv_count' => ['nullable', 'integer', 'min:1', 'max:64'],
            'orientation' => ['nullable', 'required_with:tv_count', 'in:landscape,portrait'],
            'rows' => ['nullable', 'integer', 'min:1', 'max:16'],
            'columns' => ['nullable', 'integer', 'min:1', 'max:16'],
            'approve' => ['nullable', 'boolean'],
        ];
    }
}
