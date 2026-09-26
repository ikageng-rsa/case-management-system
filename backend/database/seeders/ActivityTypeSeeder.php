<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Narration\ActivityMeasure;
use App\Models\ActivityType;
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
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            ActivityType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
