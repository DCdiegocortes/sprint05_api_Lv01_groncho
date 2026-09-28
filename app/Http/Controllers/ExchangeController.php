<?php

namespace App\Http\Controllers;

use App\Enums\ExchangeStatus;
use App\Http\Requests\Exchanges\StoreExchangeRequest;
use App\Http\Requests\Exchanges\UpdateExchangeStatusRequest;
use App\Models\Exchange;
use App\Services\ExchangeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExchangeController extends Controller
{
    public function __construct(private ExchangeService $exchangeService) {}

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

    public function update(UpdateExchangeStatusRequest $request, Exchange $exchange)
    {
        $exchange->update(['status' => $request->status]);

        if ($exchange->status === ExchangeStatus::FINISHED) {
            $this->exchangeService->transferOwnership($exchange);
        }

        return response()->json($exchange->fresh());
    }

    public function destroy(Exchange $exchange)
    {
        Gate::authorize('cancel', $exchange);

        abort_if($exchange->status === ExchangeStatus::FINISHED, 422, 'Cannot cancel a finished exchange.');

        $exchange->delete();

        return response()->json(['message' => 'Exchange cancelled'], 200);
    }
}
