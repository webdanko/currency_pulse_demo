<?php

namespace Tests\Feature;

use App\Models\RateSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntradayTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_intraday_points_from_the_provider(): void
    {
        Http::fake([
            '*' => Http::response([
                'chart' => [
                    'result' => [[
                        'timestamp' => [1_755_648_000, 1_755_648_900],
                        'indicators' => [
                            'quote' => [[
                                'close' => [1.0831, 1.0837],
                            ]],
                        ],
                    ]],
                    'error' => null,
                ],
            ]),
        ]);

        $response = $this->getJson('/api/rates/intraday?symbol=usd');

        $response
            ->assertOk()
            ->assertJsonPath('data.symbol', 'USD')
            ->assertJsonPath('data.source', 'provider')
            ->assertJsonCount(2, 'data.points')
            ->assertJsonPath('data.points.0.rate', 1.0831);
    }

    public function test_it_falls_back_to_stored_snapshots_when_upstream_fails(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        RateSnapshot::insert([
            ['base' => 'EUR', 'quote' => 'USD', 'rate' => 1.08, 'captured_at' => now()->subHours(2), 'created_at' => now(), 'updated_at' => now()],
            ['base' => 'EUR', 'quote' => 'USD', 'rate' => 1.09, 'captured_at' => now()->subHour(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->getJson('/api/rates/intraday?symbol=USD')
            ->assertOk()
            ->assertJsonPath('data.source', 'snapshots')
            ->assertJsonCount(2, 'data.points');
    }

    public function test_it_trims_the_stale_prefix_and_session_step(): void
    {
        // A stale carried-forward prefix (1.2000) then a jump to the live
        // session (~1.0850). The >0.4% step is a Yahoo artifact and must be cut,
        // so the series starts at the live session.
        Http::fake([
            '*' => Http::response([
                'chart' => [
                    'result' => [[
                        'timestamp' => [1, 2, 3, 4, 5, 6],
                        'indicators' => [
                            'quote' => [[
                                'close' => [1.2000, 1.2000, 1.2000, 1.0850, 1.0851, 1.0852],
                            ]],
                        ],
                    ]],
                ],
            ]),
        ]);

        $this->getJson('/api/rates/intraday?symbol=USD')
            ->assertOk()
            ->assertJsonPath('data.source', 'provider')
            ->assertJsonCount(3, 'data.points')
            ->assertJsonPath('data.points.0.rate', 1.085);
    }
}
