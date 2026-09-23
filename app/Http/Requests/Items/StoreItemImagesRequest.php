<?php

namespace App\Http\Requests\Items;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('uploadImages', $this->route('item'));
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['image', 'max:5120'],
        ];
    }
}
