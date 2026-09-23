<?php

namespace App\Http\Controllers;

use App\Http\Requests\Items\StoreItemImagesRequest;
use App\Models\Item;
use App\Models\ItemImage;
use Illuminate\Support\Facades\Gate;

class ItemImageController extends Controller
{
    public function store(StoreItemImagesRequest $request, Item $item)
    {
        $images = collect($request->file('images'))->map(function ($file) use ($item) {
            return ItemImage::create([
                'item_id' => $item->id,
                'path' => $file->store('item-images', 'public'),
            ]);
        });

        return response()->json($images, 201);
    }

    public function destroy(Item $item, ItemImage $image)
    {
        Gate::authorize('delete', $item);

        abort_if($image->item_id !== $item->id, 404);

        $image->delete();

        return response()->json(['message' => 'Image deleted'], 200);
    }
}
