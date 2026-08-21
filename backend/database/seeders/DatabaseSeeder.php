<?php

namespace Database\Seeders;

use App\Models\AlertRule;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a couple of demo alert rules so the alerts channel has something
     * to fire on out of the box.
     */
    public function run(): void
    {
        $rules = [
            ['quote' => 'USD', 'operator' => 'above', 'threshold' => 1.05],
            ['quote' => 'GBP', 'operator' => 'below', 'threshold' => 0.90],
        ];

        foreach ($rules as $rule) {
            AlertRule::updateOrCreate(
                ['quote' => $rule['quote'], 'operator' => $rule['operator']],
                $rule,
            );
        }
    }
}
