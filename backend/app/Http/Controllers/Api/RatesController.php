<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RateSnapshot;
use App\Services\ExchangeRateProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RatesController extends Controller
{
    /** Current rates — cached, provider-backed, works with an empty database. */
    public function index(ExchangeRateProvider $rates): JsonResponse
    {
        try {
            $payload = $rates->latest();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 502);
        }

        return response()->json(['data' => $payload]);
    }

    /** Historical series for one currency, read from stored snapshots. */
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'size:3'],
            'hours' => ['sometimes', 'integer', 'min:1', 'max:168'],
        ]);

        $symbol = strtoupper($validated['symbol']);
        $hours = (int) ($validated['hours'] ?? 24);

        $points = RateSnapshot::query()
            ->forQuote($symbol)
            ->since(now()->subHours($hours))
            ->orderBy('captured_at')
            ->get(['rate', 'captured_at'])
            ->map(fn (RateSnapshot $s): array => [
                'rate' => $s->rate,
                'captured_at' => $s->captured_at->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'symbol' => $symbol,
                'hours' => $hours,
                'points' => $points,
            ],
        ]);
    }

    /** Daily historical series pulled straight from the provider (chart data). */
    public function timeseries(Request $request, ExchangeRateProvider $rates): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'size:3'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ]);

        try {
            $series = $rates->timeseries($validated['symbol'], (int) ($validated['days'] ?? 30));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 502);
        }

        return response()->json(['data' => $series]);
    }

    /**
     * Last-24h intraday series from the provider, falling back to whatever we
     * have stored if the intraday upstream is unavailable.
     */
    public function intraday(Request $request, ExchangeRateProvider $rates): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'size:3'],
        ]);

        $symbol = strtoupper($validated['symbol']);

        try {
            $series = $rates->intraday($symbol);
            if (! empty($series['points'])) {
                return response()->json(['data' => [...$series, 'source' => 'provider']]);
            }
        } catch (RuntimeException) {
            // fall through to stored snapshots
        }

        $points = RateSnapshot::query()
            ->forQuote($symbol)
            ->since(now()->subDay())
            ->orderBy('captured_at')
            ->get(['rate', 'captured_at'])
            ->map(fn (RateSnapshot $s): array => [
                'rate' => $s->rate,
                'captured_at' => $s->captured_at->toIso8601String(),
            ]);

        return response()->json([
            'data' => ['symbol' => $symbol, 'points' => $points, 'source' => 'snapshots'],
        ]);
    }
}
