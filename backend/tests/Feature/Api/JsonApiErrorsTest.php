<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\Auth\Role;
use App\Models\Client\Client;
use App\Models\User\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;

class JsonApiErrorsTest extends ApiTestCase
{
    public function test_no_token_is_a_401_error_document(): void
    {
        $this->getJson('/api/v1/me')
            ->assertJsonApiError(401)
            ->assertExactJson(['errors' => [['status' => '401', 'title' => 'Unauthorized']]]);
    }

    public function test_a_missing_permission_is_a_403(): void
    {
        $this->signInAs(Role::Messenger);

        $this->getJson('/api/v1/clients')->assertJsonApiError(403);
    }

    public function test_a_record_that_is_not_there_does_not_name_the_model(): void
    {
        $this->signInAs(Role::CandidateAttorney);

        $response = $this->getJson('/api/v1/clients/'.Str::uuid())
            ->assertJsonApiError(404)
            ->assertExactJson(['errors' => [['status' => '404', 'title' => 'Not Found']]]);

        $this->assertStringNotContainsString('App\\\\Models', $response->getContent());
    }

    public function test_each_failed_field_is_its_own_error_pointing_at_it(): void
    {
        $this->postJson('/api/v1/auth/sign-in', ['email' => 'not-an-address'])
            ->assertJsonApiError(422, '/email')
            ->assertJsonApiError(422, '/password')
            ->assertJsonApiError(422, '/device_name')
            ->assertJsonCount(3, 'errors')
            ->assertJsonMissingPath('message');
    }

    public function test_too_many_requests_says_when_to_try_again(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/sign-in', ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'Pixel 9']);
        }

        $this->postJson('/api/v1/auth/sign-in', ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'Pixel 9'])
            ->assertJsonApiError(429)
            ->assertHeader('Retry-After');
    }

    public function test_a_fault_in_the_server_keeps_its_message_to_itself(): void
    {
        config(['app.debug' => false]);
        Route::middleware('api')->get('/api/v1/broken', fn () => throw new RuntimeException('The database password is hunter2'));

        $response = $this->getJson('/api/v1/broken')
            ->assertJsonApiError(500)
            ->assertExactJson(['errors' => [['status' => '500', 'title' => 'Internal Server Error']]]);

        $this->assertStringNotContainsString('hunter2', $response->getContent());
    }

    public function test_the_web_app_keeps_its_own_errors(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/clients/'.Client::factory()->create()->id)->assertForbidden();

        $this->assertStringNotContainsString('vnd.api+json', (string) $response->headers->get('Content-Type'));
    }
}
