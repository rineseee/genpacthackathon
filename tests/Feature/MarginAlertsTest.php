<?php

namespace Tests\Feature;

use App\Enums\AlertMetric;
use App\Models\AlertRule;
use App\Models\Company;
use App\Models\SimulationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarginAlertsTest extends TestCase
{
    use RefreshDatabase;

    private const string WEBHOOK_URL = 'http://n8n.test/webhook/margin-alert';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'margin.alerts.n8n_webhook_url' => self::WEBHOOK_URL,
            'margin.alerts.n8n_token' => 'secret-token',
        ]);
    }

    public function test_crossed_stress_alert_is_sent_to_n8n_and_marked_triggered(): void
    {
        Http::fake([self::WEBHOOK_URL => Http::response(['ok' => true])]);
        $company = Company::factory()->create(['name' => 'Furra Arbi']);
        SimulationRun::factory()->for($company)->create(['result' => ['stress_probability' => 0.58]]);
        $rule = AlertRule::factory()->for($company)->create([
            'metric' => AlertMetric::StressProbability,
            'threshold' => 0.3,
            'recipient_email' => 'owner@example.com',
        ]);

        $this->artisan('margin:check-alerts')
            ->expectsOutputToContain('1 alert(s) sent.')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->url() === self::WEBHOOK_URL
            && $request->hasHeader('X-Margin-Token', 'secret-token')
            && $request['metric'] === 'stress_probability'
            && $request['observed'] === 0.58
            && $request['threshold'] === 0.3
            && $request['recipient_email'] === 'owner@example.com'
            && $request['company']['name'] === 'Furra Arbi');
        $this->assertNotNull($rule->fresh()->last_triggered_at);
    }

    public function test_alert_below_threshold_is_not_sent(): void
    {
        Http::fake();
        $company = Company::factory()->create();
        SimulationRun::factory()->for($company)->create(['result' => ['stress_probability' => 0.14]]);
        AlertRule::factory()->for($company)->create(['threshold' => 0.3]);

        $this->artisan('margin:check-alerts')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_alert_inside_cooldown_is_not_sent_again(): void
    {
        Http::fake();
        $company = Company::factory()->create();
        SimulationRun::factory()->for($company)->create(['result' => ['stress_probability' => 0.58]]);
        AlertRule::factory()->for($company)->create(['threshold' => 0.3, 'last_triggered_at' => now()->subDays(2)]);

        $this->artisan('margin:check-alerts')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_what_if_runs_do_not_trigger_alerts(): void
    {
        Http::fake();
        $company = Company::factory()->create();
        SimulationRun::factory()->for($company)->create(['scenario' => 'baseline', 'result' => ['stress_probability' => 0.14]]);
        SimulationRun::factory()->for($company)->create(['scenario' => 'replay_2022', 'result' => ['stress_probability' => 0.9]]);
        AlertRule::factory()->for($company)->create(['threshold' => 0.3]);

        $this->artisan('margin:check-alerts')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_failed_delivery_keeps_the_alert_pending(): void
    {
        Http::fake([self::WEBHOOK_URL => Http::response('down', 500)]);
        $company = Company::factory()->create();
        SimulationRun::factory()->for($company)->create(['result' => ['stress_probability' => 0.58]]);
        $rule = AlertRule::factory()->for($company)->create(['threshold' => 0.3]);

        $this->artisan('margin:check-alerts')
            ->expectsOutputToContain('0 alert(s) sent.')
            ->assertSuccessful();

        $this->assertNull($rule->fresh()->last_triggered_at);
    }
}
