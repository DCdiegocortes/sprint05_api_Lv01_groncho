<?php

namespace App\Http\Requests\Items;

use App\Enums\ItemCondition;
use App\Enums\ItemType;
use App\Enums\OfferType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'size' => ['nullable', 'string', 'max:20'],
            'item_condition' => ['sometimes', Rule::enum(ItemCondition::class)],
            'type' => ['sometimes', Rule::enum(ItemType::class)],
            'offer_type' => ['sometimes', Rule::enum(OfferType::class)],
        ];
    }
}
