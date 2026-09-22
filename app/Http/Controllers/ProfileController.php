<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    public function update(UpdateProfileRequest $request)
    {
        $request->user()->update($request->only(['name', 'email']));

        return response()->json($request->user()->fresh());
    }
}
