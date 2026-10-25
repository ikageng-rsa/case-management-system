<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Client\Client;
use App\Models\Client\ClientContact;
use App\Models\Client\ClientContactPerson;
use App\Models\Client\PopiaConsent;
use App\Models\Diary\DiaryEntry;
use App\Models\Document\Media;
use App\Models\Invitation\UserInvitation;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\Matter\MatterParty;
use App\Models\Narration\Narration;
use App\Models\Reference\ActivityType;
use App\Models\Reference\Court;
use App\Models\Reference\MatterType;
use App\Models\User\NotificationPreference;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * store an alia rather than the actual class name
         */
        Relation::enforceMorphMap([
            'matter' => Matter::class,
            'narration' => Narration::class,
            'diary_entry' => DiaryEntry::class,
            'matter_assignment' => MatterAssignment::class,
            'matter_party' => MatterParty::class,
            'client' => Client::class,
            'client_contact' => ClientContact::class,
            'client_contact_person' => ClientContactPerson::class,
            'popia_consent' => PopiaConsent::class,
            'document' => Media::class,
            'court' => Court::class,
            'matter_type' => MatterType::class,
            'activity_type' => ActivityType::class,
            'user' => User::class,
            'role' => Role::class,
            'user_invitation' => UserInvitation::class,
            'notification_preference' => NotificationPreference::class,
        ]);

        // The API, per signed-in user, or per address before sign-in.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->getKey() ?: $request->ip());
        });
    }
}
