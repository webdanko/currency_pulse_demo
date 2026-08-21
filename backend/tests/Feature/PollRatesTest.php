<?php

namespace Tests\Feature;

use App\Events\RatesUpdated;
use App\Models\RateSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PollRatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_poll_command_stores_snapshots_and_broadcasts(): void
    {
        Event::fake([RatesUpdated::class]);

        Http::fake([
            '*/latest*' => Http::response([
                'base' => 'EUR',
                'date' => '2026-08-19',
                'rates' => ['USD' => 1.08, 'GBP' => 0.85],
            ]),
        ]);

        $this->artisan('fx:poll')->assertSuccessful();

        $this->assertDatabaseCount('rate_snapshots', 2);
        $this->assertDatabaseHas('rate_snapshots', ['quote' => 'USD']);

        Event::assertDispatched(RatesUpdated::class);
    }
}
