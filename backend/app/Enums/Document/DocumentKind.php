<?php

namespace App\Enums\Document;

enum DocumentKind: string
{
    case Bundle = 'bundle';
    case Evidence = 'evidence';
    case Mandate = 'mandate';
    case CourtOrder = 'court_order';
    case Correspondence = 'correspondence';
    case Report = 'report';
}
