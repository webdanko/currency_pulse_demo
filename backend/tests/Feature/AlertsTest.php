<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_alert_rule(): void
    {
        $response = $this->postJson('/api/alerts', [
            'quote' => 'usd',
            'operator' => 'above',
            'threshold' => 1.1,
        ]);

        $response->assertCreated()->assertJsonPath('data.quote', 'USD');
        $this->assertDatabaseHas('alert_rules', ['quote' => 'USD', 'operator' => 'above']);
    }

    public function test_it_rejects_an_invalid_operator(): void
    {
        $this->postJson('/api/alerts', [
            'quote' => 'USD',
            'operator' => 'sideways',
            'threshold' => 1.1,
        ])->assertStatus(422);
    }

    public function test_it_toggles_and_deletes_a_rule(): void
    {
        $rule = \App\Models\AlertRule::create([
            'quote' => 'USD',
            'operator' => 'above',
            'threshold' => 1.1,
        ]);

        $this->patchJson("/api/alerts/{$rule->id}", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $this->deleteJson("/api/alerts/{$rule->id}")->assertNoContent();
        $this->assertDatabaseMissing('alert_rules', ['id' => $rule->id]);
    }
}
