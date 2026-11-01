<?php

namespace Database\Seeders;

use App\Models\Swipe;
use App\Models\User;
use Illuminate\Database\Seeder;

class SwipeSeeder extends Seeder
{
    private const SWIPES = [
        ['ana@groncho.com', 'beto@groncho.com', true],
        ['beto@groncho.com', 'ana@groncho.com', true],
        ['ana@groncho.com', 'carla@groncho.com', true],
        ['carla@groncho.com', 'ana@groncho.com', true],
        ['beto@groncho.com', 'dani@groncho.com', true],
        ['dani@groncho.com', 'beto@groncho.com', true],
        ['carla@groncho.com', 'eli@groncho.com', true],
        ['eli@groncho.com', 'carla@groncho.com', true],
        ['dani@groncho.com', 'ana@groncho.com', true],
        ['eli@groncho.com', 'beto@groncho.com', false],
    ];

    public function run(): void
    {
        foreach (self::SWIPES as [$swiperEmail, $targetEmail, $liked]) {
            $swiper = User::where('email', $swiperEmail)->firstOrFail();
            $target = User::where('email', $targetEmail)->firstOrFail();

            Swipe::firstOrCreate(
                ['swiper_user_id' => $swiper->id, 'target_user_id' => $target->id],
                ['liked' => $liked]
            );
        }
    }
}
