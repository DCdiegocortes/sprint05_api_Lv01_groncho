<?php

namespace App\Enums;

enum ExchangeStatus: string
{
    case PENDING = 'PENDING';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
    case FINISHED = 'FINISHED';
}
