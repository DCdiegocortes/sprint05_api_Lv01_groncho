<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Models\MatchModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        $matches = MatchModel::where('user_one_id', $request->user()->id)
            ->orWhere('user_two_id', $request->user()->id)
            ->get();

        return response()->json($matches);
    }

    public function destroy(MatchModel $match)
    {
        Gate::authorize('close', $match);

        $match->update(['status' => MatchStatus::CLOSED]);

        return response()->json(['message' => 'Match closed'], 200);
    }
}
