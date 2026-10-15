<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PublishScreenCompositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'display_profile_id' => ['required', 'integer', 'exists:display_profiles,id'],
            'name' => ['required', 'string', 'max:255'],
            'placements' => ['required', 'array', 'min:1', 'max:64'],
            'placements.*.screen' => ['required', 'integer', 'min:1', 'distinct'],
            'placements.*.asset_id' => ['required', 'integer', 'exists:media_assets,id'],
            'placements.*.variant_id' => ['nullable', 'integer', 'exists:media_variants,id'],
            'tv_count' => ['nullable', 'integer', 'min:1', 'max:64'],
            'orientation' => ['nullable', 'required_with:tv_count', 'in:landscape,portrait'],
            'rows' => ['nullable', 'integer', 'min:1', 'max:16'],
            'columns' => ['nullable', 'integer', 'min:1', 'max:16'],
        ];
    }
}
