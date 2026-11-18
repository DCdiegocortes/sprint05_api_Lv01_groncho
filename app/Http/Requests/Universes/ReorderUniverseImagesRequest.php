<?php

namespace App\Http\Requests\Universes;

use App\Models\UniverseImage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ReorderUniverseImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reorderImages', $this->route('universe'));
    }

    public function rules(): array
    {
        return [
            'image_ids' => ['required', 'array'],
            'image_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $universeImageIds = UniverseImage::where('universe_id', $this->route('universe')->id)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $sentIds = collect($this->image_ids)->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($sentIds !== $universeImageIds) {
                $validator->errors()->add('image_ids', 'The order must list every photo of this universe exactly once.');
            }
        });
    }
}
