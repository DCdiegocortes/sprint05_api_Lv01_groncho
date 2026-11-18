<?php

namespace App\Http\Controllers;

use App\Http\Requests\Items\DestroyItemImagesRequest;
use App\Http\Requests\Items\StoreItemImagesRequest;
use App\Models\Item;
use App\Models\ItemImage;
use App\Services\ActivityNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

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

    public function destroyMany(DestroyItemImagesRequest $request, ActivityNotifier $notifier)
    {
        $images = ItemImage::with('item')->whereIn('id', $request->image_ids)->get();
        $items = $images->pluck('item')->unique('id');

        $items->each(fn (Item $item) => Gate::authorize('delete', $item));

        DB::transaction(fn () => ItemImage::whereIn('id', $images->pluck('id'))->delete());

        Storage::disk('public')->delete($images->pluck('path')->all());

        $items->each(fn (Item $item) => $notifier->itemPhotoRemoved(
            $item,
            $request->user(),
            $images->where('item_id', $item->id)->count()
        ));

        return response()->json(['message' => 'Images deleted'], 200);
    }

    public function destroy(Request $request, Item $item, ItemImage $image, ActivityNotifier $notifier)
    {
        Gate::authorize('delete', $item);

        abort_if($image->item_id !== $item->id, 404);

        Storage::disk('public')->delete($image->path);

        $image->delete();

        $notifier->itemPhotoRemoved($item, $request->user());

        return response()->json(['message' => 'Image deleted'], 200);
    }
}
