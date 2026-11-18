<?php

namespace App\Http\Requests\Items;

use App\Enums\ItemCondition;
use App\Enums\ItemType;
use App\Enums\OfferType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('item'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'size' => ['sometimes', 'nullable', 'string', 'max:20'],
            'item_condition' => ['sometimes', Rule::enum(ItemCondition::class)],
            'type' => ['sometimes', Rule::enum(ItemType::class)],
            'offer_type' => ['sometimes', Rule::enum(OfferType::class)],
        ];
    }
}
