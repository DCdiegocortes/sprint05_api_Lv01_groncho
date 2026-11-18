<?php

namespace App\Http\Requests\Universes;

use App\Models\UniverseImage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class DestroyUniverseImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('universe'));
    }

    public function rules(): array
    {
        return [
            'image_ids' => ['required', 'array', 'min:1'],
            'image_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $found = UniverseImage::where('universe_id', $this->route('universe')->id)
                ->whereIn('id', $this->image_ids)
                ->count();

            if ($found !== count($this->image_ids)) {
                $validator->errors()->add('image_ids', 'Every photo must belong to this universe.');
            }
        });
    }
}
