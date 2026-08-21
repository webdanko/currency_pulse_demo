<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a fresh set of rates is captured. Pushed to every connected
 * client over the "rates" WebSocket channel so the UI updates without polling.
 */
class RatesUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, float>  $rates
     */
    public function __construct(
        public readonly string $base,
        public readonly array $rates,
        public readonly string $capturedAt,
    ) {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('rates');
    }

    public function broadcastAs(): string
    {
        return 'rates.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'base' => $this->base,
            'rates' => $this->rates,
            'captured_at' => $this->capturedAt,
        ];
    }
}
