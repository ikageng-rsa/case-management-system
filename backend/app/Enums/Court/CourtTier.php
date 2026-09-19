<?php

namespace App\Enums\Court;

enum CourtTier: string
{
    case District = 'district';
    case Regional = 'regional';
    case High = 'high';
    case SupremeAppeal = 'supreme_appeal';
    case Constitutional = 'constitutional';
}
