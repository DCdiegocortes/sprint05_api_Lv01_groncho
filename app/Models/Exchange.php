<?php

namespace App\Models;

use App\Enums\ExchangeStatus;
use App\Enums\ExchangeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['match_id', 'requester_id', 'requested_item_id', 'offered_item_id', 'type', 'status', 'message'])]
class Exchange extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ExchangeType::class,
            'status' => ExchangeStatus::class,
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'match_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function requestedItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'requested_item_id');
    }

    public function offeredItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'offered_item_id');
    }
}
