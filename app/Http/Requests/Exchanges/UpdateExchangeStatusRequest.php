<?php

namespace App\Http\Requests\Exchanges;

use App\Enums\ExchangeStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExchangeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('respond', $this->route('exchange'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['ACCEPTED', 'REJECTED', 'FINISHED'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $exchange = $this->route('exchange');
            $current = $exchange->status;
            $next = ExchangeStatus::from($this->status);

            $allowed = match ($current) {
                ExchangeStatus::PENDING => [ExchangeStatus::ACCEPTED, ExchangeStatus::REJECTED],
                ExchangeStatus::ACCEPTED => [ExchangeStatus::FINISHED],
                default => [],
            };

            if (! in_array($next, $allowed, true)) {
                $validator->errors()->add('status', "Cannot move from {$current->value} to {$next->value}.");
            }
        });
    }
}
