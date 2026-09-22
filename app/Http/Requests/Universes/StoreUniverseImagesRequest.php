<?php

namespace App\Http\Requests\Universes;

use Illuminate\Foundation\Http\FormRequest;

class StoreUniverseImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('uploadImages', $this->route('universe'));
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['image', 'max:5120'],
        ];
    }
}
