<?php

return [
    'admin_email' => env('TV_ADMIN_EMAIL', 'admin@example.com'),
    'admin_password' => env('TV_ADMIN_PASSWORD', 'change-this-admin-password'),
    'enrollment_key' => env('TV_AGENT_ENROLLMENT_KEY', 'change-this-enrollment-key'),
    'media_max_kilobytes' => (int) env('TV_AGENT_MEDIA_MAX_KB', 51200),
    'dimension_tolerance' => (int) env('TV_AGENT_DIMENSION_TOLERANCE', 2),
    'command_lease_seconds' => (int) env('TV_AGENT_COMMAND_LEASE_SECONDS', 30),
    'allowed_commands' => [
        'vlc.status', 'vlc.start', 'vlc.stop', 'vlc.restart', 'vlc.reload_playlist',
        'campaign.download', 'campaign.activate',
        'mosaic.inspect', 'mosaic.validate_profile', 'mosaic.apply_profile',
        'mosaic.restore_snapshot', 'mosaic.enable_current', 'mosaic.disable_current',
        'device.collect_diagnostics', 'device.restart_agent', 'device.restart_computer',
    ],
    'critical_commands' => [
        'mosaic.apply_profile', 'mosaic.restore_snapshot', 'mosaic.disable_current',
        'device.restart_agent', 'device.restart_computer',
    ],
];
