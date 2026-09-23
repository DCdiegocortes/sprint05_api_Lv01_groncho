<?php

namespace App\Http\Controllers;

use App\Http\Requests\Swipes\StoreSwipeRequest;
use App\Models\Swipe;
use App\Services\MatchService;

class SwipeController extends Controller
{
    public function __construct(private MatchService $matchService) {}

    public function store(StoreSwipeRequest $request)
    {
        $swipe = Swipe::create([
            'swiper_user_id' => $request->user()->id,
            'target_user_id' => $request->target_user_id,
            'liked' => $request->liked,
        ]);

        if ($swipe->liked) {
            $this->matchService->checkForMutualLike($request->user(), $swipe->target);
        }

        return response()->json($swipe, 201);
    }
}
