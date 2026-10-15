<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'display_profile_id' => ['required', 'integer', 'exists:display_profiles,id'],
            'target_scope' => ['nullable', 'in:screen,wall'],
            'tv_count' => ['nullable', 'integer', 'min:1', 'max:64'],
            'orientation' => ['nullable', 'required_with:tv_count', 'in:landscape,portrait'],
            'rows' => ['nullable', 'integer', 'min:1', 'max:16'],
            'columns' => ['nullable', 'integer', 'min:1', 'max:16'],
            'file' => ['nullable', 'required_without:files', 'file', 'max:'.config('tv_agent.media_max_kilobytes')],
            'files' => ['nullable', 'required_without:file', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'max:'.config('tv_agent.media_max_kilobytes')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required_without' => 'Selecione pelo menos uma imagem.',
            'files.required_without' => 'Selecione pelo menos uma imagem.',
            'files.max' => 'Envie no máximo 20 imagens por vez.',
            'files.*.max' => 'Cada imagem pode ter no máximo :max KB.',
        ];
    }
}
