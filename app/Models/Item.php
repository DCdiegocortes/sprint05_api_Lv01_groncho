<?php

namespace App\Models;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Enums\OfferType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'description', 'size', 'item_condition', 'type', 'offer_type', 'status'])]
class Item extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ItemStatus::class,
            'item_condition' => ItemCondition::class,
            'type' => ItemType::class,
            'offer_type' => OfferType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ItemImage::class);
    }
}
