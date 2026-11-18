<?php

namespace App\Enums;

enum OfferType: string
{
    case TRADE = 'TRADE';
    case GIFT = 'GIFT';
    case BOTH = 'BOTH';
}
