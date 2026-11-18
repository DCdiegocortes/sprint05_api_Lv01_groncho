<?php

namespace App\Http\Controllers;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Enums\OfferType;
use App\Http\Requests\Items\StoreItemRequest;
use App\Http\Requests\Items\UpdateItemRequest;
use App\Models\Exchange;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

    public function destroy(Item $item)
    {
        Gate::authorize('delete', $item);

        abort_if(
            Exchange::where('requested_item_id', $item->id)->orWhere('offered_item_id', $item->id)->exists(),
            409,
            'Cannot delete an item that is part of an exchange.'
        );

        $item->delete();

        return response()->json(['message' => 'Item deleted'], 200);
    }
}
