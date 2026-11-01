<?php

namespace Tests\Feature\Seeders;

use App\Enums\ExchangeStatus;
use App\Enums\ExchangeType;
use App\Enums\ItemStatus;
use App\Enums\MatchStatus;
use App\Models\Exchange;
use App\Models\Item;
use App\Models\MatchModel;
use App\Models\Universe;
use App\Models\User;
use App\Services\RankingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_EMAILS = [
        'ana@groncho.com',
        'beto@groncho.com',
        'carla@groncho.com',
        'dani@groncho.com',
        'eli@groncho.com',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_it_creates_the_admin_the_demo_users_universes_and_items(): void
    {
        $this->assertDatabaseCount('users', 6);
        $this->assertDatabaseHas('users', ['email' => 'admin@groncho.com', 'role' => 'admin']);
        $this->assertDatabaseCount('universes', 5);
        $this->assertDatabaseCount('items', 13);
    }

    public function test_every_demo_user_has_the_documented_password_and_one_universe(): void
    {
        foreach (self::DEMO_EMAILS as $email) {
            $user = User::where('email', $email)->firstOrFail();

            $this->assertTrue(Hash::check('password', $user->password));
            $this->assertSame(1, Universe::where('user_id', $user->id)->count());
        }
    }

    public function test_every_item_starts_with_an_owner_and_a_valid_status(): void
    {
        $this->assertSame(0, Item::whereNull('user_id')->count());
        $this->assertSame(13, Item::whereIn('status', [ItemStatus::AVAILABLE->value, ItemStatus::UNAVAILABLE->value])->count());
    }

    public function test_it_creates_swipes_and_matches_with_one_closed_match(): void
    {
        $this->assertDatabaseCount('swipes', 10);
        $this->assertDatabaseCount('matches', 4);
        $this->assertSame(1, MatchModel::where('status', MatchStatus::CLOSED->value)->count());
    }

    public function test_demo_matches_store_the_smaller_user_id_first(): void
    {
        foreach (MatchModel::all() as $match) {
            $this->assertLessThan($match->user_two_id, $match->user_one_id);
        }
    }

    public function test_demo_exchanges_cover_every_status_and_both_types(): void
    {
        $this->assertDatabaseCount('exchanges', 6);

        foreach (ExchangeStatus::cases() as $status) {
            $this->assertGreaterThan(0, Exchange::where('status', $status->value)->count(), $status->value);
        }

        $this->assertGreaterThan(0, Exchange::where('type', ExchangeType::GIFT->value)->count());
        $this->assertGreaterThan(0, Exchange::where('type', ExchangeType::TRADE->value)->count());
    }

    public function test_finished_exchanges_already_transferred_their_items(): void
    {
        $finished = Exchange::where('status', ExchangeStatus::FINISHED->value)->get();

        $this->assertNotEmpty($finished);

        foreach ($finished as $exchange) {
            $this->assertSame($exchange->requester_id, $exchange->requestedItem->user_id);
            $this->assertSame(ItemStatus::AVAILABLE, $exchange->requestedItem->status);

            if ($exchange->type === ExchangeType::TRADE) {
                $this->assertNotSame($exchange->requester_id, $exchange->offeredItem->user_id);
                $this->assertSame(ItemStatus::AVAILABLE, $exchange->offeredItem->status);
            }
        }
    }

    public function test_accepted_exchanges_have_their_items_locked(): void
    {
        $accepted = Exchange::where('status', ExchangeStatus::ACCEPTED->value)->get();

        $this->assertNotEmpty($accepted);

        foreach ($accepted as $exchange) {
            $this->assertSame(ItemStatus::UNAVAILABLE, $exchange->requestedItem->status);

            if ($exchange->offeredItem) {
                $this->assertSame(ItemStatus::UNAVAILABLE, $exchange->offeredItem->status);
            }
        }
    }

    public function test_two_pending_requests_compete_for_the_same_item(): void
    {
        $ranking = app(RankingService::class)->itemsByRequestCount();

        $this->assertSame(2, $ranking->first()['request_count']);
        $this->assertSame(
            2,
            Exchange::where('status', ExchangeStatus::PENDING->value)
                ->where('requested_item_id', $ranking->first()['item']->id)
                ->count()
        );
    }

    public function test_the_user_ranking_has_data_to_show(): void
    {
        $ranking = app(RankingService::class)->usersBySuccessRate();

        $this->assertGreaterThanOrEqual(3, $ranking->count());
        $this->assertGreaterThan(0, $ranking->first()['success_rate']);
    }

    public function test_seeding_twice_does_not_duplicate_anything(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 6);
        $this->assertDatabaseCount('universes', 5);
        $this->assertDatabaseCount('items', 13);
        $this->assertDatabaseCount('swipes', 10);
        $this->assertDatabaseCount('matches', 4);
        $this->assertDatabaseCount('exchanges', 6);
    }
}
