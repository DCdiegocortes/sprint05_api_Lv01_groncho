<?php

namespace App\Http\Controllers;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Enums\OfferType;
use App\Http\Requests\Items\DestroyItemsRequest;
use App\Http\Requests\Items\StoreItemRequest;
use App\Http\Requests\Items\UpdateItemRequest;
use App\Models\Exchange;
use App\Models\Item;
use App\Models\ItemImage;
use App\Models\User;
use App\Services\ActivityNotifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $items = $request->user()->role === 'admin'
            ? Item::with('images')->get()
            : Item::with('images')->where('user_id', $request->user()->id)->get();

        return response()->json($items);
    }

    public function show(Item $item)
    {
        Gate::authorize('view', $item);

        return response()->json($item->load('images'));
    }

    public function store(StoreItemRequest $request)
    {
        $item = Item::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'description' => $request->description,
            'size' => $request->size,
            'item_condition' => $request->input('item_condition', ItemCondition::GOOD->value),
            'type' => $request->input('type', ItemType::CLOTHES->value),
            'offer_type' => $request->input('offer_type', OfferType::TRADE->value),
            'status' => ItemStatus::AVAILABLE,
        ]);

        return response()->json($item, 201);
    }

    public function update(UpdateItemRequest $request, Item $item)
    {
        $item->update($request->only(['name', 'description', 'size', 'item_condition', 'type', 'offer_type']));

        return response()->json($item->fresh());
    }

    public function destroy(Request $request, Item $item, ActivityNotifier $notifier)
    {
        Gate::authorize('delete', $item);

        $this->remove(new Collection([$item]), $request->user(), $notifier);

        return response()->json(['message' => 'Item deleted'], 200);
    }

    public function destroyMany(DestroyItemsRequest $request, ActivityNotifier $notifier)
    {
        $items = Item::whereIn('id', $request->item_ids)->get();

        $items->each(fn (Item $item) => Gate::authorize('delete', $item));

        $this->remove($items, $request->user(), $notifier);

        return response()->json(['message' => 'Items deleted'], 200);
    }

    private function remove(Collection $items, User $actor, ActivityNotifier $notifier): void
    {
        $itemIds = $items->pluck('id');

        abort_if(
            Exchange::whereIn('requested_item_id', $itemIds)->orWhereIn('offered_item_id', $itemIds)->exists(),
            409,
            'Cannot delete an item that is part of an exchange.'
        );

        $photoPaths = ItemImage::whereIn('item_id', $itemIds)->pluck('path')->all();

        DB::transaction(fn () => Item::whereIn('id', $itemIds)->delete());

        Storage::disk('public')->delete($photoPaths);

        $items->each(fn (Item $item) => $notifier->itemRemoved($item, $actor));
    }
}
