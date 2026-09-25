<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MatterType;
use Illuminate\Database\Seeder;

class MatterTypeSeeder extends Seeder
{
    /**
     * @var array<int, array{code: string, name: string, is_litigation: bool, default_prescription_months: int|null}>
     */
    private const TYPES = [
        ['code' => 'CIV', 'name' => 'Civil litigation', 'is_litigation' => true, 'default_prescription_months' => 36],
        ['code' => 'CRM', 'name' => 'Criminal', 'is_litigation' => true, 'default_prescription_months' => null],
        ['code' => 'RAF', 'name' => 'Road Accident Fund', 'is_litigation' => true, 'default_prescription_months' => 36],
        ['code' => 'NEDMAG', 'name' => 'Medical negligence', 'is_litigation' => true, 'default_prescription_months' => 36],
        ['code' => 'LAB', 'name' => 'Labour and employment', 'is_litigation' => true, 'default_prescription_months' => null],
        ['code' => 'FAM', 'name' => 'Family and divorce', 'is_litigation' => true, 'default_prescription_months' => null],
        ['code' => 'COL', 'name' => 'Debt collection', 'is_litigation' => true, 'default_prescription_months' => 36],
        ['code' => 'EST', 'name' => 'Deceased estates', 'is_litigation' => false, 'default_prescription_months' => null],
        ['code' => 'CONV', 'name' => 'Conveyancing', 'is_litigation' => false, 'default_prescription_months' => null],
        ['code' => 'OPIN', 'name' => 'Opinion and advisory', 'is_litigation' => false, 'default_prescription_months' => null],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            MatterType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
