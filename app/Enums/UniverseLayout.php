<?php

namespace App\Enums;

enum UniverseLayout: string
{
    case MASONRY = 'MASONRY';
    case ACCORDION = 'ACCORDION';
    case CAROUSEL = 'CAROUSEL';
    case ROTUNDA = 'ROTUNDA';
    case FLOATING = 'FLOATING';
    case IMAGE_GRID = 'IMAGE_GRID';
    case INFINITE = 'INFINITE';
    case LIQUID_GLASS = 'LIQUID_GLASS';
    case SCROLL_BULGE = 'SCROLL_BULGE';
    case SLIDER = 'SLIDER';
}
