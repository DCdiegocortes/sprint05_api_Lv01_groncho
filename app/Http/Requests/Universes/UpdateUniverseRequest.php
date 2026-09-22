<?php

namespace App\Http\Requests\Universes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUniverseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('universe'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'style' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
