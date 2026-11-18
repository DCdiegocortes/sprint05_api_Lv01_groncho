<?php

namespace App\Services;

class StyleNormalizer
{
    public function normalize(?string $style): ?string
    {
        if ($style === null) {
            return null;
        }

        $styles = collect(explode(',', $style))
            ->map(fn (string $item) => mb_strtoupper(preg_replace('/^\s+|\s+$/u', '', $item), 'UTF-8'))
            ->filter(fn (string $item) => $item !== '')
            ->unique(null, true)
            ->values();

        return $styles->isEmpty() ? null : $styles->implode(', ');
    }
}
