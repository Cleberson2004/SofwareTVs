<?php

namespace Tests\Feature;

use App\Enums\CommandStatus;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommandWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_is_leased_started_and_completed_once(): void
    {
        $device = Device::factory()->create(['credential_hash' => hash('sha256', 'valid-token')]);
        $command = DeviceCommand::factory()->for($device)->create(['type' => 'vlc.status']);

        $leased = $this->withToken('valid-token')->getJson('/api/v1/agent/commands/next');

        $leased->assertOk();
        $leased->assertJsonPath('id', $command->id);
        $this->assertDatabaseHas('device_commands', ['id' => $command->id, 'status' => CommandStatus::Leased->value]);

        $this->withToken('valid-token')->postJson("/api/v1/agent/commands/{$command->id}/started")->assertOk();
        $this->withToken('valid-token')->postJson("/api/v1/agent/commands/{$command->id}/completed", ['result' => ['running' => true]])->assertOk();
        $this->withToken('valid-token')->postJson("/api/v1/agent/commands/{$command->id}/completed", ['result' => ['running' => true]])->assertOk();

        $this->assertDatabaseHas('device_commands', ['id' => $command->id, 'status' => CommandStatus::Succeeded->value]);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_unknown_command_type_returns_422(): void
    {
        $this->actingAs(User::factory()->create());
        $device = Device::factory()->create();

        $response = $this->postJson("/api/v1/devices/{$device->id}/commands", [
            'type' => 'system.shell',
            'payload' => ['command' => 'whoami'],
            'idempotency_key' => 'unsafe-command',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['type']);
        $this->assertDatabaseCount('device_commands', 0);
    }
}
