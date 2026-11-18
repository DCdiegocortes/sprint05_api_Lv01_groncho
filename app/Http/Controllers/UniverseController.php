<?php

namespace App\Http\Controllers;

use App\Http\Requests\Universes\StoreUniverseRequest;
use App\Http\Requests\Universes\UpdateUniverseRequest;
use App\Models\Swipe;
use App\Models\Universe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UniverseController extends Controller
{
    private const FEED_BATCH_SIZE = 10;

    public function index()
    {
        return response()->json(Universe::all());
    }

    public function feed(Request $request)
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));

        $universes = Universe::with(['user:id,name', 'images'])
            ->where('user_id', '!=', $user->id)
            ->whereNotIn('user_id', Swipe::where('swiper_user_id', $user->id)->select('target_user_id'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('style', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($owner) => $owner->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('id')
            ->limit(self::FEED_BATCH_SIZE)
            ->get();

        return response()->json($universes);
    }

    public function show(Universe $universe)
    {
        return response()->json($universe);
    }

    public function store(StoreUniverseRequest $request)
    {
        $universe = Universe::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'style' => $request->style,
            'description' => $request->description,
        ]);

        return response()->json($universe, 201);
    }

    public function update(UpdateUniverseRequest $request, Universe $universe)
    {
        $universe->update($request->only(['name', 'style', 'description']));

        return response()->json($universe->fresh());
    }

    public function destroy(Universe $universe)
    {
        Gate::authorize('delete', $universe);

        $universe->delete();

        return response()->json(['message' => 'Universe deleted'], 200);
    }
}
