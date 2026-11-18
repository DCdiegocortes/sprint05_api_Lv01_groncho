<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SpotifyTrackResolver
{
    public const TRACK_LINK_PATTERN = '#^https://open\.spotify\.com/(?:intl-[a-zA-Z-]+/)?track/([A-Za-z0-9]{22})(?:\?\S*)?$#';

    private const OEMBED_URL = 'https://open.spotify.com/oembed';

    private const TIMEOUT_SECONDS = 5;

    private const MAX_LENGTH = 255;

    public function resolve(string $link): array
    {
        preg_match(self::TRACK_LINK_PATTERN, $link, $matches);
        $trackId = $matches[1];

        return [
            'spotify_track_id' => $trackId,
            ...$this->fetchTitleAndCover($trackId),
        ];
    }

    private function fetchTitleAndCover(string $trackId): array
    {
        $missing = ['spotify_track_title' => null, 'spotify_track_cover_url' => null];

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get(self::OEMBED_URL, [
                'url' => "https://open.spotify.com/track/{$trackId}",
            ]);
        } catch (ConnectionException) {
            return $missing;
        }

        if ($response->failed()) {
            return $missing;
        }

        $title = $response->json('title');
        $cover = $response->json('thumbnail_url');

        return [
            'spotify_track_title' => is_string($title) ? mb_substr($title, 0, self::MAX_LENGTH) : null,
            'spotify_track_cover_url' => is_string($cover) && strlen($cover) <= self::MAX_LENGTH ? $cover : null,
        ];
    }
}
