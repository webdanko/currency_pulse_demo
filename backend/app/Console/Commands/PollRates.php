<?php

namespace App\Console\Commands;

use App\Events\RatesUpdated;
use App\Jobs\EvaluateAlerts;
use App\Models\RateSnapshot;
use App\Services\ExchangeRateProvider;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Scheduled every minute. Captures the current rates, persists a snapshot per
 * currency (the history feed), pushes them live over WebSockets, and queues
 * alert evaluation so the request path never blocks on it.
 */
class PollRates extends Command
{
    protected $signature = 'fx:poll';

    protected $description = 'Fetch the latest FX rates, store a snapshot, and broadcast the update';

    public function handle(ExchangeRateProvider $provider): int
    {
        try {
            $payload = $provider->latest();
        } catch (RuntimeException $e) {
            $this->error("Upstream unavailable: {$e->getMessage()}");

            return self::FAILURE;
        }

        $capturedAt = now();

        $rows = [];
        foreach ($payload['rates'] as $quote => $rate) {
            $rows[] = [
                'base' => $payload['base'],
                'quote' => $quote,
                'rate' => $rate,
                'captured_at' => $capturedAt,
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ];
        }

        RateSnapshot::insert($rows);

        RatesUpdated::dispatch($payload['base'], $payload['rates'], $capturedAt->toIso8601String());
        EvaluateAlerts::dispatch($payload['rates']);

        $this->info(sprintf('Captured %d rates at %s.', count($rows), $capturedAt->toTimeString()));

        return self::SUCCESS;
    }
}
