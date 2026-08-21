<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RatesTest extends TestCase
{
    public function test_it_returns_normalized_rates_from_the_upstream(): void
    {
        Http::fake([
            '*/latest*' => Http::response([
                'base' => 'EUR',
                'date' => '2026-08-19',
                'rates' => ['USD' => 1.08321, 'GBP' => 0.85114],
            ]),
        ]);

        $response = $this->getJson('/api/rates');

        $response
            ->assertOk()
            ->assertJsonPath('data.base', 'EUR')
            ->assertJsonPath('data.cached', false)
            ->assertJsonPath('data.rates.USD', 1.0832)
            ->assertJsonPath('data.rates.GBP', 0.8511);
    }

    public function test_it_serves_the_second_request_from_cache(): void
    {
        Http::fake([
            '*/latest*' => Http::response([
                'base' => 'EUR',
                'date' => '2026-08-19',
                'rates' => ['USD' => 1.08],
            ]),
        ]);

        $this->getJson('/api/rates')->assertJsonPath('data.cached', false);
        $this->getJson('/api/rates')->assertJsonPath('data.cached', true);

        Http::assertSentCount(1);
    }

    public function test_it_returns_a_gateway_error_when_the_upstream_fails(): void
    {
        Cache::flush();

        Http::fake([
            '*/latest*' => Http::response(['message' => 'boom'], 500),
        ]);

        $this->getJson('/api/rates')->assertStatus(502);
    }
}
