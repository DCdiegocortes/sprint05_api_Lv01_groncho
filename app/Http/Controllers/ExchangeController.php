<?php

namespace App\Http\Controllers;

use App\Enums\ExchangeStatus;
use App\Http\Requests\Exchanges\StoreExchangeRequest;
use App\Models\Exchange;

class ExchangeController extends Controller
{
    public function store(StoreExchangeRequest $request)
    {
        $exchange = Exchange::create([
            'match_id' => $request->match_id,
            'requester_id' => $request->user()->id,
            'requested_item_id' => $request->requested_item_id,
            'offered_item_id' => $request->offered_item_id,
            'type' => $request->type,
            'status' => ExchangeStatus::PENDING,
            'message' => $request->message,
        ]);

        return response()->json($exchange, 201);
    }
}
