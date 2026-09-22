<?php

namespace App\Http\Controllers;

use App\Http\Requests\Universes\StoreUniverseRequest;
use App\Models\Universe;

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
}
