<?php

namespace App\Http\Controllers;

use App\Http\Requests\Universes\StoreUniverseImagesRequest;
use App\Models\Universe;
use App\Models\UniverseImage;
use Illuminate\Support\Facades\Gate;

class UniverseImageController extends Controller
{
    public function store(StoreUniverseImagesRequest $request, Universe $universe)
    {
        $images = collect($request->file('images'))->map(function ($file) use ($universe) {
            return UniverseImage::create([
                'universe_id' => $universe->id,
                'path' => $file->store('universe-images', 'public'),
            ]);
        });

        return response()->json($images, 201);
    }

    public function destroy(Universe $universe, UniverseImage $image)
    {
        Gate::authorize('delete', $universe);

        abort_if($image->universe_id !== $universe->id, 404);

        $image->delete();

        return response()->json(['message' => 'Image deleted'], 200);
    }
}
