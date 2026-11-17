<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Enums\MatchStatus;
use App\Models\Item;
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

    public function items(Request $request, MatchModel $match)
    {
        Gate::authorize('viewItems', $match);

        $partnerId = $match->user_one_id === $request->user()->id
            ? $match->user_two_id
            : $match->user_one_id;

        $items = Item::where('user_id', $partnerId)
            ->where('status', ItemStatus::AVAILABLE)
            ->with('images')
            ->get();

        return response()->json($items);
    }

    public function destroy(MatchModel $match)
    {
        Gate::authorize('close', $match);

        $match->update(['status' => MatchStatus::CLOSED]);

        return response()->json(['message' => 'Match closed'], 200);
    }
}
