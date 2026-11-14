<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Narration\ActivityMeasure;
use App\Models\Reference\ActivityType;
use Illuminate\Database\Seeder;

class ActivityTypeSeeder extends Seeder
{
    /**
     * @var array<int, array{code: string, name: string, measure: ActivityMeasure, increment: int, default_billable: bool, requires_court: bool}>
     */
    private const TYPES = [
        ['code' => 'CALL', 'name' => 'Telephone attendance', 'measure' => ActivityMeasure::Minutes, 'increment' => 5, 'default_billable' => true, 'requires_court' => false],
        ['code' => 'CONS', 'name' => 'Consultation', 'measure' => ActivityMeasure::Minutes, 'increment' => 15, 'default_billable' => true, 'requires_court' => false],
        ['code' => 'DRAFT', 'name' => 'Drafting', 'measure' => ActivityMeasure::Page, 'increment' => 1, 'default_billable' => true, 'requires_court' => false],
        ['code' => 'PERUSE', 'name' => 'Perusal', 'measure' => ActivityMeasure::Page, 'increment' => 1, 'default_billable' => true, 'requires_court' => false],
        ['code' => 'CORR', 'name' => 'Correspondence', 'measure' => ActivityMeasure::Item, 'increment' => 1, 'default_billable' => true, 'requires_court' => false],
        ['code' => 'APP', 'name' => 'Court appearance', 'measure' => ActivityMeasure::Minutes, 'increment' => 15, 'default_billable' => true, 'requires_court' => true],
        ['code' => 'TRAVEL', 'name' => 'Travelling', 'measure' => ActivityMeasure::Kilometre, 'increment' => 1, 'default_billable' => true, 'requires_court' => false],
        ['code' => 'FILE', 'name' => 'Filing and service', 'measure' => ActivityMeasure::Item, 'increment' => 1, 'default_billable' => true, 'requires_court' => false],
        ['code' => 'ADMIN', 'name' => 'File administration', 'measure' => ActivityMeasure::Minutes, 'increment' => 15, 'default_billable' => false, 'requires_court' => false],
        ['code' => 'TRAVTIME', 'name' => 'Travelling time', 'measure' => ActivityMeasure::Minutes, 'increment' => 15, 'default_billable' => true, 'requires_court' => false],
        ['code' => 'DRAFTTIME', 'name' => 'Drafting time', 'measure' => ActivityMeasure::Minutes, 'increment' => 15, 'default_billable' => true, 'requires_court' => false],
    ];

    /**
     * Work that also charges the time it took, and the kind of work the time is charged as.
     *
     * @var array<string, string>
     */
    private const TIME_ON_TOP = [
        'TRAVEL' => 'TRAVTIME',
        'DRAFT' => 'DRAFTTIME',
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            ActivityType::updateOrCreate(['code' => $type['code']], $type);
        }

        foreach (self::TIME_ON_TOP as $code => $timeCode) {
            ActivityType::where('code', $code)->update(['time_activity_type_id' => ActivityType::where('code', $timeCode)->value('id')]);
        }
    }
}
