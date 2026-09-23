<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Http\Requests\Items\StoreItemRequest;
use App\Models\Item;

class ItemController extends Controller
{
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
}
