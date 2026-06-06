<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Snapshot diário de uso de storage por tenant
Schedule::command('storage:snapshot')->dailyAt('02:00');

// Limpeza de tokens expirados do Sanctum
Schedule::command('sanctum:prune-expired', ['--hours=24'])->daily();

// Publica comunicados agendados quando a data marcada chega
Schedule::command('announcements:publish-scheduled')->everyMinute()->withoutOverlapping();

// Marca cobranças vencidas e notifica os moradores (uma vez por dia, de manhã)
Schedule::command('charges:mark-overdue')->dailyAt('06:00')->withoutOverlapping();

// Expurga a lixeira de arquivos (remoção definitiva após 30 dias)
Schedule::command('storage:purge-trash')->dailyAt('03:30')->withoutOverlapping();

// Inicia campanhas de WhatsApp agendadas quando a data marcada chega
Schedule::command('campaigns:dispatch-scheduled')->everyMinute()->withoutOverlapping();

// Alerta de documentos vencendo (AVCB, alvarás, contratos) — uma vez por dia, de manhã
Schedule::command('documents:notify-expiring')->dailyAt('07:00')->withoutOverlapping();
