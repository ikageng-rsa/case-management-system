<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Entries are told of once
Schedule::command('diary:alert-overdue')
    ->hourly()->withoutOverlapping();

// Reminders go out as each entry asks, down to half an hour ahead, so this runs often. Each is sent once.
Schedule::command('diary:remind-upcoming')->everyFiveMinutes()->withoutOverlapping();
