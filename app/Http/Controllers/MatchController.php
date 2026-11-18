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
        $user = $request->user();

        $matches = MatchModel::where('user_one_id', $user->id)
            ->orWhere('user_two_id', $user->id)
            ->with('userOne:id,name', 'userTwo:id,name')
            ->get()
            ->each(function (MatchModel $match) use ($user) {
                $match->setRelation(
                    'partner',
                    $match->user_one_id === $user->id ? $match->userTwo : $match->userOne
                );
                $match->unsetRelation('userOne')->unsetRelation('userTwo');
            });

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
