<?php

namespace App\Http\Controllers;

use App\Http\Requests\Universes\DestroyUniverseImagesRequest;
use App\Http\Requests\Universes\ReorderUniverseImagesRequest;
use App\Http\Requests\Universes\StoreUniverseImagesRequest;
use App\Models\Universe;
use App\Models\UniverseImage;
use App\Services\ActivityNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class UniverseImageController extends Controller
{
    public function store(StoreUniverseImagesRequest $request, Universe $universe)
    {
        $lastPosition = UniverseImage::where('universe_id', $universe->id)->max('position') ?? 0;

        $images = collect($request->file('images'))->values()->map(function ($file, $index) use ($universe, $lastPosition) {
            return UniverseImage::create([
                'universe_id' => $universe->id,
                'path' => $file->store('universe-images', 'public'),
                'position' => $lastPosition + $index + 1,
            ]);
        });

        return response()->json($images, 201);
    }

    public function reorder(ReorderUniverseImagesRequest $request, Universe $universe)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->image_ids as $index => $imageId) {
                UniverseImage::where('id', $imageId)->update(['position' => $index + 1]);
            }
        });

        return response()->json($universe->images()->get());
    }

    public function destroyMany(DestroyUniverseImagesRequest $request, Universe $universe, ActivityNotifier $notifier)
    {
        $images = UniverseImage::where('universe_id', $universe->id)->whereIn('id', $request->image_ids)->get();

        DB::transaction(fn () => UniverseImage::whereIn('id', $images->pluck('id'))->delete());

        Storage::disk('public')->delete($images->pluck('path')->all());

        $notifier->universePhotoRemoved($universe, $request->user(), $images->count());

        return response()->json(['message' => 'Images deleted'], 200);
    }

    public function destroy(Request $request, Universe $universe, UniverseImage $image, ActivityNotifier $notifier)
    {
        Gate::authorize('delete', $universe);

        abort_if($image->universe_id !== $universe->id, 404);

        Storage::disk('public')->delete($image->path);

        $image->delete();

        $notifier->universePhotoRemoved($universe, $request->user());

        return response()->json(['message' => 'Image deleted'], 200);
    }
}
