<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Client;

use App\Enums\Client\ContactKind;
use App\Models\Client;
use App\Rules\Client\UniqueClientContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniqueClientContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_a_value_already_on_the_client_however_it_is_formatted(): void
    {
        $client = $this->clientWithEmail('jane@example.com');

        $this->assertFalse($this->passes($client, ContactKind::Email, '  Jane@Example.com '));
    }

    public function test_it_allows_the_same_value_on_a_different_client(): void
    {
        $this->clientWithEmail('shared@example.com');
        $other = Client::factory()->create();

        $this->assertTrue($this->passes($other, ContactKind::Email, 'shared@example.com'));
    }

    private function clientWithEmail(string $email): Client
    {
        $client = Client::factory()->create();
        $client->contacts()->create([
            'kind' => ContactKind::Email,
            'value' => $email,
            'is_primary' => true,
        ]);

        return $client;
    }

    private function passes(Client $client, ContactKind $kind, string $value): bool
    {
        $failed = false;

        (new UniqueClientContact($client, $kind))->validate(
            'value',
            $value,
            function () use (&$failed): void {
                $failed = true;
            },
        );

        return ! $failed;
    }
}
