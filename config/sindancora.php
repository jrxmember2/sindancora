<?php

return [
    'domain' => env('SINDANCORA_DOMAIN', 'sindancora.com.br'),
    'super_admin_subdomain' => env('SUPER_ADMIN_SUBDOMAIN', 'admin'),
    'version' => env('APP_VERSION', '1.0.0'),

    'storage' => [
        'soft_delete_days' => env('STORAGE_SOFT_DELETE_DAYS', 30),
        'max_file_size_mb' => env('STORAGE_MAX_FILE_MB', 50),
    ],

    'plans' => [
        'trial_days' => env('TRIAL_DAYS', 14),
    ],

    'notifications' => [
        'storage_warning_at' => 0.85, // 85% — alerta de armazenamento
        'storage_critical_at' => 0.95, // 95% — alerta crítico
    ],
];
