<?php

declare(strict_types=1);

namespace App\Enums\Matter;

enum Assignment: string
{
    case Responsible = 'responsible';
    case Supervising = 'supervising';
    case Assisting = 'assisting';
    case Messenger = 'messenger';
}
