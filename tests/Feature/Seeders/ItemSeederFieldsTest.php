<?php

namespace Tests\Feature\Seeders;

use App\Enums\ItemType;
use App\Enums\OfferType;
use App\Models\Exchange;
use App\Models\Item;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemSeederFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_every_seeded_exchange_matches_the_offer_of_its_requested_item(): void
    {
        $exchanges = Exchange::with('requestedItem')->get();

        $this->assertNotEmpty($exchanges);

        foreach ($exchanges as $exchange) {
            $offer = $exchange->requestedItem->offer_type;

            $this->assertTrue(
                $offer === OfferType::BOTH || $offer->value === $exchange->type->value,
                "{$exchange->requestedItem->name} is offered as {$offer->value} but was requested as {$exchange->type->value}."
            );
        }
    }

    public function test_the_seeded_accessories_are_marked_as_accessories(): void
    {
        foreach (['Wool scarf', 'Straw hat', 'Leather backpack'] as $name) {
            $this->assertSame(ItemType::ACCESSORIES, Item::where('name', $name)->firstOrFail()->type);
        }
    }

    public function test_the_other_seeded_items_are_marked_as_clothes(): void
    {
        $this->assertSame(ItemType::CLOTHES, Item::where('name', 'Beige trench coat')->firstOrFail()->type);
    }
}
