<?php

namespace App\Providers;

use App\Listeners\RecordRoleChange;
use App\Models\ActivityType;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Court;
use App\Models\DiaryEntry;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\MatterType;
use App\Models\Media;
use App\Models\Narration;
use App\Models\PopiaConsent;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

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
            'client' => Client::class,
            'client_contact' => ClientContact::class,
            'popia_consent' => PopiaConsent::class,
            'document' => Media::class,
            'court' => Court::class,
            'matter_type' => MatterType::class,
            'activity_type' => ActivityType::class,
            'user' => User::class,
        ]);

        Event::listen([RoleAttachedEvent::class, RoleDetachedEvent::class], RecordRoleChange::class);
    }
}
