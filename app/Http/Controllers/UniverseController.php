<?php

namespace App\Http\Controllers;

use App\Http\Requests\Universes\StoreUniverseRequest;
use App\Http\Requests\Universes\UpdateUniverseRequest;
use App\Models\Universe;
use Illuminate\Support\Facades\Gate;

class UniverseController extends Controller
{
    public function index()
    {
        return response()->json(Universe::all());
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
        ]);

        return response()->json($universe, 201);
    }

    public function update(UpdateUniverseRequest $request, Universe $universe)
    {
        $universe->update($request->only(['name', 'style']));

        return response()->json($universe->fresh());
    }

    public function destroy(Universe $universe)
    {
        Gate::authorize('delete', $universe);

        $universe->delete();

        return response()->json(['message' => 'Universe deleted'], 200);
    }
}
