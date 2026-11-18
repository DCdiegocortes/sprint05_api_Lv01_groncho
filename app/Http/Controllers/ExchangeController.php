<?php

namespace App\Http\Controllers;

use App\Enums\ExchangeStatus;
use App\Http\Requests\Exchanges\StoreExchangeRequest;
use App\Http\Requests\Exchanges\UpdateExchangeStatusRequest;
use App\Models\Exchange;
use App\Services\ActivityNotifier;
use App\Services\ExchangeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExchangeController extends Controller
{
    public function __construct(
        private ExchangeService $exchangeService,
        private ActivityNotifier $notifier,
    ) {}

    public function index(Request $request)
    {
        if ($request->user()->role === 'admin') {
            $exchanges = Exchange::with($this->relations())->get();
        } else {
            $exchanges = Exchange::with($this->relations())
                ->where('requester_id', $request->user()->id)
                ->orWhereHas('requestedItem', function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id);
                })
                ->orWhereHas('match', function ($query) use ($request) {
                    $query->where('user_one_id', $request->user()->id)
                        ->orWhere('user_two_id', $request->user()->id);
                })
                ->get();
        }

        return response()->json($exchanges->each(fn (Exchange $exchange) => $this->present($exchange)));
    }

    public function show(Exchange $exchange)
    {
        Gate::authorize('view', $exchange);

        return response()->json($this->present($exchange->load($this->relations())));
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

        $this->notifier->exchangeRequested($exchange, $request->user());

        return response()->json($exchange, 201);
    }

    public function update(UpdateExchangeStatusRequest $request, Exchange $exchange)
    {
        $exchange->update(['status' => $request->status]);
        $exchange->load('requestedItem');

        if ($exchange->status === ExchangeStatus::ACCEPTED) {
            $this->exchangeService->lockItems($exchange)->each(
                fn (Exchange $declined) => $this->notifier->exchangeAnswered($declined, $request->user())
            );
        }

        if ($exchange->status === ExchangeStatus::FINISHED) {
            $this->exchangeService->transferOwnership($exchange);
            $this->exchangeService->releaseItems($exchange);
        }

        $this->notifier->exchangeAnswered($exchange, $request->user());

        return response()->json($exchange->fresh());
    }

    public function destroy(Request $request, Exchange $exchange)
    {
        Gate::authorize('cancel', $exchange);

        abort_if($exchange->status === ExchangeStatus::FINISHED, 422, 'Cannot cancel a finished exchange.');

        if ($exchange->status === ExchangeStatus::ACCEPTED) {
            $this->exchangeService->releaseItems($exchange);
        }

        if ($request->user()->id === $exchange->requester_id) {
            $this->notifier->exchangeCancelled($exchange, $request->user());
        }

        $exchange->delete();

        return response()->json(['message' => 'Exchange cancelled'], 200);
    }

    private function relations(): array
    {
        return [
            'requester:id,name,avatar',
            'requestedItem:id,name,size',
            'requestedItem.images',
            'offeredItem:id,name,size',
            'offeredItem.images',
            'match.userOne:id,name,avatar',
            'match.userTwo:id,name,avatar',
        ];
    }

    private function present(Exchange $exchange): Exchange
    {
        $match = $exchange->match;

        $exchange->setRelation(
            'receiver',
            $match->user_one_id === $exchange->requester_id ? $match->userTwo : $match->userOne
        );

        return $exchange->unsetRelation('match');
    }
}
