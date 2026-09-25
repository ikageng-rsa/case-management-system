<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Court\CourtTier;
use App\Models\Court;
use Illuminate\Database\Seeder;

class CourtSeeder extends Seeder
{
    /**
     * @var array<int, array{tier: CourtTier, seat: string}>
     */
    private const COURTS = [
        ['tier' => CourtTier::District, 'seat' => 'Rustenburg'],
        ['tier' => CourtTier::Regional, 'seat' => 'Rustenburg'],
        ['tier' => CourtTier::High, 'seat' => 'Makhanda'],
        ['tier' => CourtTier::High, 'seat' => 'Bloemfontein'],
        ['tier' => CourtTier::High, 'seat' => 'Pretoria'],
        ['tier' => CourtTier::High, 'seat' => 'Pietermaritzburg'],
        ['tier' => CourtTier::High, 'seat' => 'Polokwane'],
        ['tier' => CourtTier::High, 'seat' => 'Mbombela'],
        ['tier' => CourtTier::High, 'seat' => 'Mahikeng'],
        ['tier' => CourtTier::High, 'seat' => 'Kimberley'],
        ['tier' => CourtTier::High, 'seat' => 'Cape Town'],
        ['tier' => CourtTier::SupremeAppeal, 'seat' => 'Bloemfontein'],
        ['tier' => CourtTier::Constitutional, 'seat' => 'Johannesburg'],
    ];

    public function run(): void
    {
        foreach (self::COURTS as $court) {
            Court::updateOrCreate(
                ['tier' => $court['tier'], 'seat' => $court['seat']],
                ['name' => $court['tier']->courtName()],
            );
        }
    }
}
