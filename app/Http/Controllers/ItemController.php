<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Http\Requests\Items\StoreItemRequest;
use App\Http\Requests\Items\UpdateItemRequest;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $items = $request->user()->role === 'admin'
            ? Item::all()
            : Item::where('user_id', $request->user()->id)->get();

        return response()->json($items);
    }

    public function show(Item $item)
    {
        Gate::authorize('view', $item);

        return response()->json($item);
    }

    public function store(StoreItemRequest $request)
    {
        $item = Item::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'description' => $request->description,
            'size' => $request->size,
            'status' => ItemStatus::AVAILABLE,
        ]);

        return response()->json($item, 201);
    }

    public function update(UpdateItemRequest $request, Item $item)
    {
        $item->update($request->only(['name', 'description', 'size']));

        return response()->json($item->fresh());
    }

    public function destroy(Item $item)
    {
        Gate::authorize('delete', $item);

        $item->delete();

        return response()->json(['message' => 'Item deleted'], 200);
    }
}
