<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Client;
use App\Models\Matter;
use App\Models\Media;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Accesses are deduplicated per request, and a test is a fresh request.
        Client::flushRecordedAccesses();
        Matter::flushRecordedAccesses();
        Media::flushRecordedAccesses();
    }
}
