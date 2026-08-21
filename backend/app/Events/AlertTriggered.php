<?php

namespace App\Events;

use App\Models\AlertRule;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a rate crosses an alert rule's threshold. Broadcast to the
 * "alerts" channel so the UI can raise a toast in real time.
 */
class AlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AlertRule $rule,
        public readonly float $rate,
    ) {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('alerts');
    }

    public function broadcastAs(): string
    {
        return 'alert.triggered';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'quote' => $this->rule->quote,
            'operator' => $this->rule->operator,
            'threshold' => $this->rule->threshold,
            'rate' => $this->rate,
            'message' => sprintf(
                '%s is %s %s (now %s)',
                $this->rule->quote,
                $this->rule->operator,
                rtrim(rtrim(number_format($this->rule->threshold, 4), '0'), '.'),
                rtrim(rtrim(number_format($this->rate, 4), '0'), '.'),
            ),
        ];
    }
}
