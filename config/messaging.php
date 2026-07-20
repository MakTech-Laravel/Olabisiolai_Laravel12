<?php

declare(strict_types=1);

return [
    'platform_admin_email' => env('MESSAGING_PLATFORM_ADMIN_EMAIL', 'superadmin@dev.com'),
    'platform_admin_display_name' => env('MESSAGING_PLATFORM_ADMIN_NAME', 'Olabisiolai Admin'),
    'vendor_admin_message_url' => env('MESSAGING_VENDOR_ADMIN_MESSAGE_URL', '/vendor/leads?channel=admin'),

    'max_attachment_size_mb' => (int) env('MAX_ATTACHMENT_SIZE_MB', 50),
    'max_participants_per_conversation' => (int) env('MAX_PARTICIPANTS_PER_CONVERSATION', 50),
    'message_rate_limit_per_minute' => (int) env('MESSAGE_RATE_LIMIT_PER_MINUTE', 60),
    'signed_url_ttl_minutes' => (int) env('MESSAGING_ATTACHMENT_URL_TTL', 60),
    'use_htmlpurifier' => (bool) env('MESSAGING_USE_HTMLPURIFIER', true),

    /** DB "online" older than this is exposed as offline (seconds). */
    'presence_stale_seconds' => (int) env('MESSAGING_PRESENCE_STALE_SECONDS', 120),
    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'video/mp4',
        'audio/mpeg',
        'audio/wav',
    ],

    /** Minutes between outbound email/SMS alerts per vendor+conversation while away. */
    'away_alert_debounce_minutes' => (int) env('MESSAGING_AWAY_ALERT_DEBOUNCE_MINUTES', 15),
    'away_alert_app_url' => env('FRONTEND_URL', env('APP_URL')),
];
