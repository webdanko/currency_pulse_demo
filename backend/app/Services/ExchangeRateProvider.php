<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin, cache-first client for an external FX rates API (Frankfurter by default).
 *
 * The point of this class is the boring-but-important part clients actually pay for:
 * one place that owns the upstream call, caches it so we don't hammer a third party,
 * and fails in a predictable way instead of taking the whole page down.
 */
class ExchangeRateProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $base,
        private readonly array $symbols,
        private readonly int $cacheTtl,
        private readonly int $timeout,
    ) {
    }

    /**
     * @return array{base: string, date: string, rates: array<string, float>, cached: bool, fetched_at: string}
     */
    public function latest(): array
    {
        $cacheKey = $this->cacheKey();

        if ($cached = Cache::get($cacheKey)) {
            return [...$cached, 'cached' => true];
        }

        $fresh = $this->fetch();

        Cache::put($cacheKey, $fresh, $this->cacheTtl);

        return [...$fresh, 'cached' => false];
    }

    /**
     * Daily historical series for a single quote currency, straight from the
     * provider — so a chart has real data before the poller has accumulated
     * any snapshots. Cached for an hour since daily rates don't move.
     *
     * @return array{symbol: string, points: array<int, array{rate: float, captured_at: string}>}
     */
    public function timeseries(string $symbol, int $days): array
    {
        $symbol = strtoupper($symbol);
        $end = now();
        $start = $end->copy()->subDays($days);
        $cacheKey = sprintf('fx:ts:%s:%s:%d', $this->base, $symbol, $days);

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->retry(2, 200, throw: false)
                ->get(sprintf('/%s..%s', $start->toDateString(), $end->toDateString()), [
                    'base' => $this->base,
                    'symbols' => $symbol,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('FX timeseries unreachable', ['error' => $e->getMessage()]);

            throw new RuntimeException('Exchange rate provider is unreachable.', 503, $e);
        }

        if ($response->failed()) {
            Log::warning('FX timeseries returned an error', ['status' => $response->status()]);

            throw new RuntimeException('Exchange rate provider returned an error.', 502);
        }

        $rates = $response->json('rates') ?? [];
        ksort($rates);

        $points = [];
        foreach ($rates as $date => $day) {
            if (! isset($day[$symbol])) {
                continue;
            }

            $points[] = [
                'rate' => round((float) $day[$symbol], 4),
                'captured_at' => $date,
            ];
        }

        $result = ['symbol' => $symbol, 'points' => $points];
        Cache::put($cacheKey, $result, 3600);

        return $result;
    }

    /**
     * Intraday series for the last 24h from a keyless source (Yahoo Finance
     * chart, 15-minute bars). Frankfurter only serves daily reference rates,
     * so a real intraday chart needs a different upstream. Cached briefly.
     *
     * @return array{symbol: string, points: array<int, array{rate: float, captured_at: string}>}
     */
    public function intraday(string $symbol): array
    {
        $symbol = strtoupper($symbol);
        $cacheKey = sprintf('fx:intraday:%s:%s', $this->base, $symbol);

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $url = config('services.exchange.intraday_url');

        try {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->timeout($this->timeout)
                ->retry(2, 200, throw: false)
                ->get(sprintf('%s/%s%s=X', rtrim($url, '/'), $this->base, $symbol), [
                    'interval' => '5m',
                    'range' => '1d',
                ]);
        } catch (ConnectionException $e) {
            Log::warning('FX intraday unreachable', ['error' => $e->getMessage()]);

            throw new RuntimeException('Exchange rate provider is unreachable.', 503, $e);
        }

        if ($response->failed()) {
            Log::warning('FX intraday returned an error', ['status' => $response->status()]);

            throw new RuntimeException('Exchange rate provider returned an error.', 502);
        }

        $result = $response->json('chart.result.0') ?? [];
        $timestamps = $result['timestamp'] ?? [];
        $closes = $result['indicators']['quote'][0]['close'] ?? [];

        $points = [];
        foreach ($timestamps as $i => $ts) {
            $close = $closes[$i] ?? null;
            if ($close === null) {
                continue;
            }

            $points[] = [
                'rate' => round((float) $close, 4),
                'captured_at' => Carbon::createFromTimestamp($ts)->toIso8601String(),
            ];
        }

        // Yahoo prepends a stale "prior close" segment; the jump to the live
        // session shows up as a step no real 5-minute FX move would make
        // (>0.4%). Keep only the data after the last such artifact so the
        // chart starts at the live session instead of a flat line + step.
        $cut = 0;
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $prev = $points[$i - 1]['rate'];
            if ($prev > 0 && abs($points[$i]['rate'] - $prev) / $prev > 0.004) {
                $cut = $i;
            }
        }
        $points = array_values(array_slice($points, $cut));

        $series = ['symbol' => $symbol, 'points' => $points];
        Cache::put($cacheKey, $series, 300);

        return $series;
    }

    /**
     * Current spot price per symbol from the intraday source (moves during the
     * day, unlike Frankfurter's daily reference rates). Falls back to the daily
     * rates if the intraday source is unavailable, so the stream never stalls.
     *
     * @return array<string, float>
     */
    public function spot(): array
    {
        $url = config('services.exchange.intraday_url');
        $rates = [];

        foreach ($this->symbols as $symbol) {
            try {
                $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                    ->timeout($this->timeout)
                    ->get(sprintf('%s/%s%s=X', rtrim($url, '/'), $this->base, $symbol), [
                        'interval' => '1m',
                        'range' => '1d',
                    ]);

                $price = $response->json('chart.result.0.meta.regularMarketPrice');
                if ($price !== null) {
                    $rates[strtoupper($symbol)] = round((float) $price, 4);
                }
            } catch (\Throwable $e) {
                Log::warning('FX spot failed for symbol', ['symbol' => $symbol, 'error' => $e->getMessage()]);
            }
        }

        if ($rates === []) {
            return $this->latest()['rates'];
        }

        return $rates;
    }

    /**
     * @return array{base: string, date: string, rates: array<string, float>, fetched_at: string}
     */
    private function fetch(): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->retry(2, 200, throw: false)
                ->get('/latest', [
                    'from' => $this->base,
                    'to' => implode(',', $this->symbols),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('FX upstream unreachable', ['error' => $e->getMessage()]);

            throw new RuntimeException('Exchange rate provider is unreachable.', 503, $e);
        }

        if ($response->failed()) {
            Log::warning('FX upstream returned an error', ['status' => $response->status()]);

            throw new RuntimeException('Exchange rate provider returned an error.', 502);
        }

        $data = $response->json();

        return [
            'base' => $data['base'] ?? $this->base,
            'date' => $data['date'] ?? now()->toDateString(),
            'rates' => $this->normalizeRates($data['rates'] ?? []),
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $rates
     * @return array<string, float>
     */
    private function normalizeRates(array $rates): array
    {
        return array_map(static fn ($rate): float => round((float) $rate, 4), $rates);
    }

    private function cacheKey(): string
    {
        return sprintf('fx:%s:%s', $this->base, implode('-', $this->symbols));
    }
}
