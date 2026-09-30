<?php

namespace App\Http\Requests\Exchanges;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\MatchModel;
use App\Services\ExchangeService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'match_id' => ['required', 'integer', Rule::exists('matches', 'id')],
            'requested_item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'offered_item_id' => ['nullable', 'integer', Rule::exists('items', 'id')],
            'type' => ['required', Rule::in(['TRADE', 'GIFT'])],
            'message' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $match = MatchModel::find($this->match_id);
            $requestedItem = Item::find($this->requested_item_id);
            $offeredItem = $this->offered_item_id ? Item::find($this->offered_item_id) : null;

            if ($match && ! $match->involves($this->user())) {
                $validator->errors()->add('match_id', 'You are not part of this match.');

                return;
            }

            if ($match && $requestedItem) {
                $matchPartnerId = $match->user_one_id === $this->user()->id
                    ? $match->user_two_id
                    : $match->user_one_id;

                if ($requestedItem->user_id !== $matchPartnerId) {
                    $validator->errors()->add('requested_item_id', 'This item does not belong to your match partner.');
                }
            }

            if ($requestedItem && $requestedItem->status !== ItemStatus::AVAILABLE) {
                $validator->errors()->add('requested_item_id', 'This item is not available.');
            }

            if ($this->type === 'TRADE' && ! $offeredItem) {
                $validator->errors()->add('offered_item_id', 'A trade requires an offered item.');
            }

            if ($this->type === 'GIFT' && $offeredItem) {
                $validator->errors()->add('offered_item_id', 'A gift cannot include an offered item.');
            }

            if ($offeredItem && $offeredItem->user_id !== $this->user()->id) {
                $validator->errors()->add('offered_item_id', 'You can only offer your own item.');
            }

            if ($offeredItem && $offeredItem->status !== ItemStatus::AVAILABLE) {
                $validator->errors()->add('offered_item_id', 'The offered item is not available.');
            }

            if (app(ExchangeService::class)->exceedsGiveReceiveBalance($this->user())) {
                abort(403, 'You need to give something back before requesting more exchanges.');
            }
        });
    }
}
