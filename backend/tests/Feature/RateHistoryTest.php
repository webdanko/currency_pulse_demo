<?php

namespace Tests\Feature;

use App\Models\RateSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_time_series_for_a_symbol(): void
    {
        RateSnapshot::insert([
            ['base' => 'EUR', 'quote' => 'USD', 'rate' => 1.08, 'captured_at' => now()->subHours(2), 'created_at' => now(), 'updated_at' => now()],
            ['base' => 'EUR', 'quote' => 'USD', 'rate' => 1.09, 'captured_at' => now()->subHour(), 'created_at' => now(), 'updated_at' => now()],
            ['base' => 'EUR', 'quote' => 'GBP', 'rate' => 0.85, 'captured_at' => now()->subHour(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson('/api/rates/history?symbol=usd&hours=24');

        $response
            ->assertOk()
            ->assertJsonPath('data.symbol', 'USD')
            ->assertJsonCount(2, 'data.points');
    }

    public function test_it_validates_the_symbol(): void
    {
        $this->getJson('/api/rates/history')->assertStatus(422);
        $this->getJson('/api/rates/history?symbol=DOLLAR')->assertStatus(422);
    }
}
