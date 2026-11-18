<?php

namespace App\Http\Controllers;

use App\Http\Requests\Universes\ReorderUniverseImagesRequest;
use App\Http\Requests\Universes\StoreUniverseImagesRequest;
use App\Models\Universe;
use App\Models\UniverseImage;
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

    public function destroy(Universe $universe, UniverseImage $image)
    {
        Gate::authorize('delete', $universe);

        abort_if($image->universe_id !== $universe->id, 404);

        Storage::disk('public')->delete($image->path);

        $image->delete();

        return response()->json(['message' => 'Image deleted'], 200);
    }
}
