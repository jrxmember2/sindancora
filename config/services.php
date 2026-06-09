<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'asaas' => [
        'sandbox' => env('ASAAS_SANDBOX_URL', 'https://sandbox.asaas.com/api/v3'),
        'production' => env('ASAAS_PRODUCTION_URL', 'https://api.asaas.com/v3'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-8'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.5'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    // Captcha Cloudflare Turnstile (anti-abuso dos formulários públicos). Opcional:
    // sem as chaves, a verificação é ignorada (no-op) e o widget não é exibido.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET'),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
    ],

    // Servidor Evolution API (auto-hospedado). Chave global nunca exposta ao tenant.
    'evolution' => [
        'base_url' => env('EVOLUTION_BASE_URL'),
        'key' => env('EVOLUTION_API_KEY'),
        // URL publica do webhook de recebimento (Fase 2). Se vazio, instancias sao criadas sem webhook.
        'webhook_url' => env('EVOLUTION_WEBHOOK_URL'),
        // Tamanho maximo (MB) de midia de WhatsApp a armazenar/enviar (Fase 4).
        'media_max_mb' => (int) env('WHATSAPP_MEDIA_MAX_MB', 16),
    ],

];
