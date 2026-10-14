<?php

declare(strict_types=1);

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_front_door_leads_to_signing_in(): void
    {
        $this->withoutVite();

        $this->get('/')->assertRedirect('/login');
    }
}
