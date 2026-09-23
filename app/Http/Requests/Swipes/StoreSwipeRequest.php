<?php

namespace App\Http\Requests\Swipes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSwipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::notIn([$this->user()->id]),
                Rule::unique('swipes', 'target_user_id')->where('swiper_user_id', $this->user()->id),
            ],
            'liked' => ['required', 'boolean'],
        ];
    }
}
