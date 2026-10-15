<?php

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_enrollment_registers_device_and_returns_token(): void
    {
        config(['tv_agent.enrollment_key' => 'enrollment-secret']);

        $response = $this->withHeader('X-Enrollment-Key', 'enrollment-secret')->postJson('/api/v1/agent/register', [
            'device_uuid' => '84438fd1-0cf4-49f7-93ed-6834e06bbc55',
            'name' => 'Player teste',
            'agent_version' => '1.0.0',
            'windows_version' => 'Windows 11',
            'display_provider' => 'Mock',
            'simulation_mode' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['device_id', 'token']);
        $this->assertDatabaseHas('devices', ['name' => 'Player teste', 'display_provider' => 'Mock', 'simulation_mode' => true]);
    }

    public function test_heartbeat_returns_401_for_invalid_token(): void
    {
        Device::factory()->create();

        $response = $this->withToken('wrong-token')->postJson('/api/v1/agent/heartbeat', []);

        $response->assertUnauthorized();
        $response->assertJson(['message' => 'Credencial do dispositivo inválida ou revogada.']);
    }
}
