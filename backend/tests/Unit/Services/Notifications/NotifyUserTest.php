<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Notifications;

use App\Enums\Matter\Assignment;
use App\Models\Matter\Matter;
use App\Models\User\User;
use App\Notifications\MatterAssigned;
use App\Services\Notifications\NotifyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class NotifyUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_outside_a_transaction_it_tells_them_at_once(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->assertTrue((new NotifyUser)->about($user, $this->assigned()));

        Notification::assertSentTo($user, MatterAssigned::class);
    }

    public function test_inside_a_transaction_it_waits_for_the_commit(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        DB::transaction(function () use ($user): void {
            (new NotifyUser)->about($user, $this->assigned());

            Notification::assertNothingSent();
        });

        Notification::assertSentTo($user, MatterAssigned::class);
    }

    public function test_nothing_is_sent_when_the_change_is_rolled_back(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        try {
            DB::transaction(function () use ($user): void {
                (new NotifyUser)->about($user, $this->assigned());

                throw new RuntimeException('the change failed');
            });
        } catch (RuntimeException) {
            // The change did not happen, so neither does the message.
        }

        Notification::assertNothingSent();
    }

    protected function assigned(): MatterAssigned
    {
        return new MatterAssigned(Matter::factory()->create(), Assignment::Responsible, null);
    }
}
