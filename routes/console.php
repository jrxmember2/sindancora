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

// Limpeza automática de mídia de WhatsApp por tenant (política por data/cota); antes do purge-trash
Schedule::command('whatsapp:cleanup-media')->dailyAt('03:15')->withoutOverlapping();

// Expurga a lixeira de arquivos (remoção definitiva após 30 dias)
Schedule::command('storage:purge-trash')->dailyAt('03:30')->withoutOverlapping();

// Inicia campanhas de WhatsApp agendadas quando a data marcada chega
Schedule::command('campaigns:dispatch-scheduled')->everyMinute()->withoutOverlapping();

// Alerta de documentos vencendo (AVCB, alvarás, contratos) — uma vez por dia, de manhã
Schedule::command('documents:notify-expiring')->dailyAt('07:00')->withoutOverlapping();

// Alerta de manutenções preventivas próximas/atrasadas — uma vez por dia, de manhã
Schedule::command('maintenance:notify-due')->dailyAt('07:30')->withoutOverlapping();

// Alerta de contas a pagar proximas do vencimento/vencidas - uma vez por dia, de manha
Schedule::command('expenses:notify-due')->dailyAt('07:45')->withoutOverlapping();

// Alerta de SLA de chamados (ocorrências) próximos do prazo ou atrasados — uma vez por dia
Schedule::command('occurrences:notify-sla')->dailyAt('08:00')->withoutOverlapping();

// Alerta de ferias de funcionarios proximas do prazo limite ou atrasadas
Schedule::command('employees:notify-vacations')->dailyAt('08:15')->withoutOverlapping();

// Régua de cobrança SaaS (plataforma → tenant): lembretes, atrasos, bloqueio em D+15 e
// desbloqueio por confiança. Uma vez por dia, de manhã.
Schedule::command('billing:run-dunning')->dailyAt('06:30')->withoutOverlapping();

// Reconciliação dos pagamentos SaaS com o Asaas (fonte da verdade), corrige divergências.
Schedule::command('billing:reconcile')->dailyAt('05:30')->withoutOverlapping();
