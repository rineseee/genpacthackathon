<?php

namespace Tests\Feature;

use App\Enums\AlertMetric;
use App\Models\AlertRule;
use App\Models\Company;
use App\Models\SimulationRun;
use App\Services\Margin\Alerts\AlertDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\SeedsDemoBakery;
use Tests\TestCase;

class MarginAlertsTest extends TestCase
{
    use RefreshDatabase, SeedsDemoBakery;

    private const string WEBHOOK_URL = 'http://n8n.test/webhook/margin-alert';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'margin.alerts.n8n_webhook_url' => self::WEBHOOK_URL,
            'margin.alerts.n8n_token' => 'secret-token',
        ]);
    }

    public function test_command_sends_the_demo_bakery_stress_alert_from_the_radar(): void
    {
        Http::fake([self::WEBHOOK_URL => Http::response(['sent' => true])]);
        $company = $this->seedDemoBakery();
        $company->alertRules()->update(['threshold' => 0.01]);

        $this->artisan('margin:check-alerts')
            ->expectsOutputToContain('Sent stress_probability alert for Furra Demo to owner@furra-demo.test')
            ->expectsOutputToContain('1 alert(s) sent.')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->url() === self::WEBHOOK_URL
            && $request['metric'] === 'stress_probability'
            && $request['company']['name'] === 'Furra Demo');
        $this->assertNotNull($company->alertRules()->first()->last_triggered_at);
    }

    public function test_crossed_alert_is_sent_to_n8n_and_marked_triggered(): void
    {
        Http::fake([self::WEBHOOK_URL => Http::response(['sent' => true])]);
        $company = Company::factory()->create(['name' => 'Furra Arbi']);
        SimulationRun::factory()->for($company)->create();
        $rule = AlertRule::factory()->for($company)->create([
            'metric' => AlertMetric::MarginAtRisk,
            'threshold' => 1000,
            'recipient_email' => 'owner@example.com',
        ]);

        $sent = app(AlertDispatcher::class)->check($company, ['margin_at_risk' => 1450.0, 'stress_probability' => 0.58]);

        $this->assertCount(1, $sent);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::WEBHOOK_URL
            && $request['metric'] === 'margin_at_risk'
            && $request['unit'] === 'EUR'
            && $request['observed'] === 1450.0
            && $request['threshold'] === 1000.0
            && $request['recipient_email'] === 'owner@example.com'
            && $request['company']['name'] === 'Furra Arbi');
        $this->assertNotNull($rule->fresh()->last_triggered_at);
    }

    public function test_alert_below_threshold_is_not_sent(): void
    {
        Http::fake();
        $company = Company::factory()->create();
        AlertRule::factory()->for($company)->create(['threshold' => 0.3]);

        $sent = app(AlertDispatcher::class)->check($company, ['stress_probability' => 0.14]);

        $this->assertCount(0, $sent);
        Http::assertNothingSent();
    }

    public function test_alert_inside_cooldown_is_not_sent_again(): void
    {
        Http::fake();
        $company = Company::factory()->create();
        AlertRule::factory()->for($company)->create(['threshold' => 0.3, 'last_triggered_at' => now()->subDays(2)]);

        app(AlertDispatcher::class)->check($company, ['stress_probability' => 0.58]);

        Http::assertNothingSent();
    }

    public function test_failed_delivery_keeps_the_alert_pending(): void
    {
        Http::fake([self::WEBHOOK_URL => Http::response('down', 500)]);
        $company = Company::factory()->create();
        $rule = AlertRule::factory()->for($company)->create(['threshold' => 0.3]);

        $sent = app(AlertDispatcher::class)->check($company, ['stress_probability' => 0.58]);

        $this->assertCount(0, $sent);
        $this->assertNull($rule->fresh()->last_triggered_at);
    }
}
