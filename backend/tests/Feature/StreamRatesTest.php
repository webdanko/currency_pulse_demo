<?php

namespace Tests\Feature;

use App\Events\RatesUpdated;
use App\Services\ExchangeRateProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StreamRatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_single_tick_stores_spot_snapshots_and_broadcasts(): void
    {
        Event::fake([RatesUpdated::class]);

        // Every Yahoo chart call returns a live price via meta.regularMarketPrice.
        Http::fake([
            '*finance/chart*' => Http::response([
                'chart' => ['result' => [['meta' => ['regularMarketPrice' => 1.0842]]]],
            ]),
        ]);

        $this->artisan('fx:stream', ['--once' => true])->assertSuccessful();

        // One row per configured symbol (USD,GBP,JPY,CHF,PLN by default).
        $this->assertDatabaseCount('rate_snapshots', 5);
        $this->assertDatabaseHas('rate_snapshots', ['quote' => 'USD', 'rate' => 1.0842]);

        Event::assertDispatched(RatesUpdated::class);
    }

    public function test_spot_falls_back_to_daily_rates_when_intraday_is_empty(): void
    {
        Http::fake([
            '*finance/chart*' => Http::response(['chart' => ['result' => [['meta' => []]]]]),
            '*/latest*' => Http::response([
                'base' => 'EUR',
                'date' => '2026-08-20',
                'rates' => ['USD' => 1.08],
            ]),
        ]);

        $rates = app(ExchangeRateProvider::class)->spot();

        $this->assertSame(['USD' => 1.08], $rates);
    }
}
