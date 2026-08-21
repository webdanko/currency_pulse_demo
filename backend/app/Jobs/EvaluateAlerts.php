<?php

namespace App\Jobs;

use App\Events\AlertTriggered;
use App\Models\AlertRule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Runs off the queue after each poll: checks the latest rates against every
 * active alert rule and broadcasts the ones that crossed. Debounced so a rule
 * hovering around its threshold doesn't spam the channel.
 */
class EvaluateAlerts implements ShouldQueue
{
    use Queueable;

    private const DEBOUNCE_MINUTES = 10;

    /**
     * @param  array<string, float>  $rates
     */
    public function __construct(
        private readonly array $rates,
    ) {
    }

    public function handle(): void
    {
        $rules = AlertRule::query()->active()->get();

        foreach ($rules as $rule) {
            $rate = $this->rates[$rule->quote] ?? null;

            if ($rate === null || ! $rule->isCrossedBy((float) $rate)) {
                continue;
            }

            if ($this->recentlyTriggered($rule)) {
                continue;
            }

            $rule->forceFill(['last_triggered_at' => now()])->save();

            AlertTriggered::dispatch($rule, (float) $rate);
        }
    }

    private function recentlyTriggered(AlertRule $rule): bool
    {
        return $rule->last_triggered_at instanceof Carbon
            && $rule->last_triggered_at->gt(now()->subMinutes(self::DEBOUNCE_MINUTES));
    }
}
