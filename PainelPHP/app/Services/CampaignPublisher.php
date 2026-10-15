<?php

namespace App\Services;

use App\Enums\CommandStatus;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\DB;

class CampaignPublisher
{
    public function publish(MediaVariant $variant, Device $device, string $name, ?int $userId, ?string $ipAddress): Campaign
    {
        return DB::transaction(function () use ($variant, $device, $name, $userId, $ipAddress): Campaign {
            $campaign = Campaign::create([
                'device_id' => $device->id,
                'media_variant_id' => $variant->id,
                'name' => $name,
                'status' => 'publishing',
                'published_by' => $userId,
            ]);

            $command = DeviceCommand::create([
                'device_id' => $device->id,
                'type' => 'campaign.download',
                'payload' => [
                    'campaign_id' => $campaign->id,
                    'download_path' => route('agent.media.download', $variant, false),
                    'sha256' => $variant->sha256,
                    'width' => $variant->width,
                    'height' => $variant->height,
                    'filename' => 'campaign-'.$campaign->id.'.png',
                    'activate' => true,
                ],
                'status' => CommandStatus::Queued,
                'priority' => 8,
                'idempotency_key' => 'campaign-'.$campaign->id.'-download-v1',
                'requested_by' => $userId,
                'expires_at' => now()->addDay(),
            ]);

            AuditLog::create([
                'user_id' => $userId,
                'device_id' => $device->id,
                'action' => 'campaign.published',
                'auditable_type' => Campaign::class,
                'auditable_id' => (string) $campaign->id,
                'context' => ['command_id' => $command->id, 'media_variant_id' => $variant->id],
                'ip_address' => $ipAddress,
            ]);

            return $campaign;
        });
    }
}
