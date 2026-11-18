<?php

namespace App\Http\Controllers;

use App\Enums\ExchangeStatus;
use App\Enums\MatchStatus;
use App\Enums\UniverseLayout;
use App\Http\Requests\Universes\StoreUniverseRequest;
use App\Http\Requests\Universes\UpdateUniverseRequest;
use App\Models\Exchange;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\Swipe;
use App\Models\Universe;
use App\Services\SpotifyTrackResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UniverseController extends Controller
{
    private const FEED_BATCH_SIZE = 10;

    public function index()
    {
        return response()->json(Universe::all());
    }

    public function feed(Request $request)
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));

        $universes = Universe::with(['user:id,name', 'images'])
            ->where('user_id', '!=', $user->id)
            ->whereNotIn('user_id', Swipe::where('swiper_user_id', $user->id)->select('target_user_id'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('style', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($owner) => $owner->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('id')
            ->limit(self::FEED_BATCH_SIZE)
            ->get();

        return response()->json($universes);
    }

    public function mine(Request $request)
    {
        $user = $request->user();
        $universe = $user->universe()->with('images')->firstOrFail();

        $involvesUser = fn ($query) => $query->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id);

        return response()->json([
            ...$universe->toArray(),
            'counts' => [
                'pieces' => Item::where('user_id', $user->id)->count(),
                'matches' => MatchModel::where('status', MatchStatus::ACTIVE)->where($involvesUser)->count(),
                'requests' => Exchange::where('status', ExchangeStatus::PENDING)
                    ->where('requester_id', '!=', $user->id)
                    ->whereHas('match', $involvesUser)
                    ->count(),
            ],
        ]);
    }

    public function show(Universe $universe)
    {
        return response()->json($universe);
    }

    public function store(StoreUniverseRequest $request)
    {
        $universe = Universe::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'style' => $request->style,
            'description' => $request->description,
            'layout' => UniverseLayout::MASONRY,
        ]);

        return response()->json($universe, 201);
    }

    public function update(UpdateUniverseRequest $request, Universe $universe, SpotifyTrackResolver $spotify)
    {
        $data = $request->only(['name', 'style', 'description', 'layout']);

        if ($request->has('spotify_url')) {
            $data = [...$data, ...($request->spotify_url === null
                ? ['spotify_track_id' => null, 'spotify_track_title' => null, 'spotify_track_cover_url' => null]
                : $spotify->resolve($request->spotify_url))];
        }

        $universe->update($data);

        return response()->json($universe->fresh());
    }

    public function destroy(Universe $universe)
    {
        Gate::authorize('delete', $universe);

        $universe->delete();

        return response()->json(['message' => 'Universe deleted'], 200);
    }
}
