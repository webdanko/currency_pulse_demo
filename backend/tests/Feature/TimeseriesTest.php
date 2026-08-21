<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TimeseriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_daily_series_from_the_provider(): void
    {
        Http::fake([
            '*' => Http::response([
                'amount' => 1,
                'base' => 'EUR',
                'rates' => [
                    '2026-08-17' => ['USD' => 1.081],
                    '2026-08-18' => ['USD' => 1.086],
                    '2026-08-19' => ['USD' => 1.083],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/rates/timeseries?symbol=usd&days=7');

        $response
            ->assertOk()
            ->assertJsonPath('data.symbol', 'USD')
            ->assertJsonCount(3, 'data.points')
            ->assertJsonPath('data.points.0.rate', 1.081);
    }

    public function test_it_validates_days_bounds(): void
    {
        $this->getJson('/api/rates/timeseries?symbol=USD&days=999')->assertStatus(422);
    }
}
