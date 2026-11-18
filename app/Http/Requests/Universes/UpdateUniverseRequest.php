<?php

namespace App\Http\Requests\Universes;

use App\Enums\UniverseLayout;
use App\Services\StyleNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUniverseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('universe'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->style)) {
            $this->merge(['style' => app(StyleNormalizer::class)->normalize($this->style)]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'style' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'layout' => ['sometimes', Rule::enum(UniverseLayout::class)],
        ];
    }
}
