<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Database\Seeder;

class MatchSeeder extends Seeder
{
    private const MATCHES = [
        ['ana@groncho.com', 'beto@groncho.com', MatchStatus::ACTIVE],
        ['ana@groncho.com', 'carla@groncho.com', MatchStatus::ACTIVE],
        ['beto@groncho.com', 'dani@groncho.com', MatchStatus::ACTIVE],
        ['carla@groncho.com', 'eli@groncho.com', MatchStatus::CLOSED],
    ];

    public function run(): void
    {
        foreach (self::MATCHES as [$firstEmail, $secondEmail, $status]) {
            $firstId = User::where('email', $firstEmail)->firstOrFail()->id;
            $secondId = User::where('email', $secondEmail)->firstOrFail()->id;

            MatchModel::createOrFirst(
                ['user_one_id' => min($firstId, $secondId), 'user_two_id' => max($firstId, $secondId)],
                ['status' => $status]
            );
        }
    }
}
