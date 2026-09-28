<?php

namespace App\Http\Controllers;

use App\Services\RankingService;

class RankingController extends Controller
{
    public function __construct(private RankingService $rankingService) {}

    public function users()
    {
        return response()->json($this->rankingService->usersBySuccessRate());
    }

    public function usersBest()
    {
        return response()->json($this->rankingService->usersBySuccessRate()->first());
    }

    public function usersWorst()
    {
        return response()->json($this->rankingService->usersBySuccessRate()->last());
    }

    public function items()
    {
        return response()->json($this->rankingService->itemsByRequestCount());
    }
}
