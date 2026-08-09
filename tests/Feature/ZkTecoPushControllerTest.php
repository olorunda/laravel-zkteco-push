<?php

namespace ZkTeco\Push\Tests\Feature;

use ZkTeco\Push\Tests\TestCase;

class ZkTecoPushControllerTest extends TestCase
{
    public function test_hardware_cdata_handshake_endpoint_responds_ok(): void
    {
        $response = $this->get('/iclock/cdata?SN=ZK-HTTP-001&options=all');

        $response->assertStatus(200);
        $response->assertSee('GET OPTION FROM:');
    }

    public function test_hardware_cdata_post_attendance_log_responds_ok(): void
    {
        $payload = "101\t2026-08-09 08:30:15\t0\t1\t0\t0\t0";

        $response = $this->call('POST', '/iclock/cdata?SN=ZK-HTTP-001&table=ATTLOG', [], [], [], [], $payload);

        $response->assertStatus(200);
        $response->assertSee('OK:');
    }

    public function test_admin_ui_dashboard_renders_html_with_csrf_token(): void
    {
        $response = $this->get('/zkteco/admin');

        $response->assertStatus(200);
        $response->assertSee('ZKTeco Push SDK Middleware');
        $response->assertSee('_token');
    }

    public function test_admin_ui_form_post_save_config_with_csrf_token(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

        $response = $this->post('/zkteco/admin', [
            'action' => 'save_config',
            'external_api_url' => 'https://api.test-company.com/v1',
            'webhook_secret_token' => 'sk_test_token_123',
            'attendance_webhook_path' => '/webhooks/attendance',
            'heartbeat_webhook_path' => '/webhooks/heartbeat',
            'middleware_api_key' => 'zk_api_key_test_123',
        ]);

        $response->assertStatus(200);
        $response->assertSee('Configuration updated successfully');
    }

    public function test_rest_api_devices_endpoint(): void
    {
        $response = $this->withHeader('X-API-Key', 'zk_api_key_test_123')->getJson('/api/zkteco/devices');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'devices'
        ]);
    }
}
