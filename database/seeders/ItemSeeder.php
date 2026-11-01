<?php

namespace Database\Seeders;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    private const ITEMS = [
        'ana@groncho.com' => [
            ['Beige trench coat', 'Classic cut, barely worn', 'M'],
            ['White linen shirt', 'Light and breathable', 'S'],
            ['Black ankle boots', 'Leather, small scuff on the heel', '38'],
        ],
        'beto@groncho.com' => [
            ['Vintage denim jacket', 'Faded wash, very soft', 'L'],
            ['Striped polo', 'Navy and white stripes', 'M'],
            ['Corduroy pants', 'Brown, straight fit', '32'],
        ],
        'carla@groncho.com' => [
            ['Floral midi dress', 'Summer favorite', 'S'],
            ['Wool scarf', 'Warm and oversized', 'One size'],
            ['Straw hat', 'Wide brim', null],
        ],
        'dani@groncho.com' => [
            ['Tweed blazer', 'Green herringbone', 'M'],
            ['Plaid skirt', 'Pleated, knee length', 'S'],
        ],
        'eli@groncho.com' => [
            ['Running jacket', 'Windproof, reflective details', 'L'],
            ['Leather backpack', 'Roomy, brass zippers', null],
        ],
    ];

    public function run(): void
    {
        foreach (self::ITEMS as $email => $items) {
            $user = User::where('email', $email)->firstOrFail();

            foreach ($items as [$name, $description, $size]) {
                Item::firstOrCreate(
                    ['name' => $name],
                    [
                        'user_id' => $user->id,
                        'description' => $description,
                        'size' => $size,
                        'status' => ItemStatus::AVAILABLE,
                    ]
                );
            }
        }
    }
}
