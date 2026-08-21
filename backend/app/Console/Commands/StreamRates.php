<?php

namespace App\Console\Commands;

use App\Events\RatesUpdated;
use App\Jobs\EvaluateAlerts;
use App\Models\RateSnapshot;
use App\Services\ExchangeRateProvider;
use Illuminate\Console\Command;

/**
 * Long-running ticker: pulls spot prices every N seconds and pushes them over
 * the "rates" WebSocket channel, far more often than the once-a-minute
 * scheduler could. Run as its own container; the loop exits on SIGTERM.
 */
class StreamRates extends Command
{
    protected $signature = 'fx:stream {--interval= : Seconds between ticks} {--once : Run a single tick and exit}';

    protected $description = 'Continuously stream live FX spot prices over WebSockets';

    private bool $running = true;

    public function handle(ExchangeRateProvider $provider): int
    {
        $interval = (int) ($this->option('interval') ?: config('services.exchange.stream_interval', 10));
        $interval = max(2, $interval);

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn () => $this->running = false);
            pcntl_signal(SIGINT, fn () => $this->running = false);
        }

        $base = config('services.exchange.base');

        if ($this->option('once')) {
            $this->tick($base, $provider->spot());

            return self::SUCCESS;
        }

        $this->info("Streaming spot rates every {$interval}s (base {$base}).");

        while ($this->running) {
            try {
                $rates = $provider->spot();
                $this->tick($base, $rates);
            } catch (\Throwable $e) {
                $this->error('Tick failed: '.$e->getMessage());
            }

            sleep($interval);
        }

        $this->info('Stream stopped.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, float>  $rates
     */
    private function tick(string $base, array $rates): void
    {
        if ($rates === []) {
            return;
        }

        $capturedAt = now();

        $rows = [];
        foreach ($rates as $quote => $rate) {
            $rows[] = [
                'base' => $base,
                'quote' => $quote,
                'rate' => $rate,
                'captured_at' => $capturedAt,
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ];
        }

        RateSnapshot::insert($rows);

        RatesUpdated::dispatch($base, $rates, $capturedAt->toIso8601String());
        EvaluateAlerts::dispatch($rates);
    }
}
