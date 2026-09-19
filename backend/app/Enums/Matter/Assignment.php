<?php

namespace App\Enums\Matter;

enum Assignment: string
{
    case Responsible = 'responsible';
    case Supervising = 'supervising';
    case Assisting = 'assisting';
    case Messenger = 'messenger';
}
