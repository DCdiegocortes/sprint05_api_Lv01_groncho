<?php

namespace App\Http\Controllers;

use App\Enums\ExchangeStatus;
use App\Http\Requests\Exchanges\StoreExchangeRequest;
use App\Models\Exchange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExchangeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role === 'admin') {
            $exchanges = Exchange::all();
        } else {
            $exchanges = Exchange::where('requester_id', $request->user()->id)
                ->orWhereHas('requestedItem', function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id);
                })
                ->get();
        }

        return response()->json($exchanges);
    }

    public function show(Exchange $exchange)
    {
        Gate::authorize('view', $exchange);

        return response()->json($exchange);
    }

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
