<?php

namespace Tests\Feature;

use App\Events\AlertTriggered;
use App\Jobs\EvaluateAlerts;
use App\Models\AlertRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EvaluateAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_broadcasts_when_a_threshold_is_crossed(): void
    {
        Event::fake([AlertTriggered::class]);

        $rule = AlertRule::create([
            'quote' => 'USD',
            'operator' => 'above',
            'threshold' => 1.05,
        ]);

        (new EvaluateAlerts(['USD' => 1.10]))->handle();

        Event::assertDispatched(AlertTriggered::class);
        $this->assertNotNull($rule->fresh()->last_triggered_at);
    }

    public function test_it_does_not_broadcast_when_not_crossed(): void
    {
        Event::fake([AlertTriggered::class]);

        AlertRule::create([
            'quote' => 'USD',
            'operator' => 'above',
            'threshold' => 1.20,
        ]);

        (new EvaluateAlerts(['USD' => 1.10]))->handle();

        Event::assertNotDispatched(AlertTriggered::class);
    }

    public function test_it_debounces_repeated_alerts(): void
    {
        Event::fake([AlertTriggered::class]);

        AlertRule::create([
            'quote' => 'USD',
            'operator' => 'above',
            'threshold' => 1.05,
            'last_triggered_at' => now()->subMinutes(2),
        ]);

        (new EvaluateAlerts(['USD' => 1.10]))->handle();

        Event::assertNotDispatched(AlertTriggered::class);
    }
}
