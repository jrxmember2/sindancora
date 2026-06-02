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
