<?php

namespace App\Http\Requests\Universes;

use App\Models\Universe;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreUniverseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'style' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (Universe::where('user_id', $this->user()->id)->exists()) {
                $validator->errors()->add('user_id', 'You already have a universe.');
            }
        });
    }
}
