<?php

namespace App\Support;

class NotificationPreferenceRegistry
{
    public const CHANNELS = [
        'database' => 'Sino',
        'broadcast' => 'Tempo real',
        'mail' => 'E-mail',
        'whatsapp' => 'WhatsApp',
    ];

    public const EVENTS = [
        'announcement_published' => [
            'group' => 'Comunicacao',
            'label' => 'Comunicados publicados',
            'description' => 'Novos comunicados enviados ao painel ou portal.',
            'channels' => ['database', 'broadcast', 'whatsapp'],
        ],
        'poll_opened' => [
            'group' => 'Comunicacao',
            'label' => 'Enquetes abertas',
            'description' => 'Aviso ao morador quando uma enquete do condominio e aberta para votacao.',
            'channels' => ['database', 'broadcast', 'whatsapp'],
        ],
        'occurrence_updated' => [
            'group' => 'Ocorrencias',
            'label' => 'Atualizacoes de ocorrencias',
            'description' => 'Abertura, resposta ou mudanca relevante em ocorrencias.',
            'channels' => ['database', 'broadcast', 'whatsapp'],
        ],
        'occurrence_sla_due' => [
            'group' => 'Ocorrencias',
            'label' => 'SLA de chamados',
            'description' => 'Chamados proximos do prazo ou atrasados.',
            'channels' => ['database', 'broadcast', 'mail'],
        ],
        'reservation_updated' => [
            'group' => 'Reservas',
            'label' => 'Reservas',
            'description' => 'Criacao, aprovacao, recusa ou cancelamento de reservas.',
            'channels' => ['database', 'broadcast', 'whatsapp'],
        ],
        'visitor_arrived' => [
            'group' => 'Portaria',
            'label' => 'Visitante chegou',
            'description' => 'Aviso ao morador quando uma autorizacao de visitante e utilizada.',
            'channels' => ['database', 'broadcast'],
        ],
        'parcel_arrived' => [
            'group' => 'Portaria',
            'label' => 'Encomenda recebida',
            'description' => 'Aviso ao morador quando uma encomenda chega na portaria.',
            'channels' => ['database', 'broadcast', 'whatsapp'],
        ],
        'charge_overdue' => [
            'group' => 'Financeiro',
            'label' => 'Cobrancas vencidas',
            'description' => 'Aviso ao morador quando uma cobranca fica vencida.',
            'channels' => ['database', 'broadcast', 'mail', 'whatsapp'],
        ],
        'expense_due' => [
            'group' => 'Financeiro',
            'label' => 'Contas a pagar',
            'description' => 'Contas a pagar proximas do vencimento ou vencidas.',
            'channels' => ['database', 'broadcast', 'mail'],
        ],
        'document_expiring' => [
            'group' => 'Documentos',
            'label' => 'Documentos vencendo',
            'description' => 'Documentos com validade proxima ou vencida.',
            'channels' => ['database', 'broadcast', 'mail'],
        ],
        'maintenance_due' => [
            'group' => 'Operacao',
            'label' => 'Manutencoes preventivas',
            'description' => 'Manutencoes proximas do vencimento ou atrasadas.',
            'channels' => ['database', 'broadcast', 'mail'],
        ],
        'employee_vacation_due' => [
            'group' => 'Operacao',
            'label' => 'Ferias de funcionarios',
            'description' => 'Ferias proximas do prazo limite ou atrasadas.',
            'channels' => ['database', 'broadcast', 'mail'],
        ],
        'public_submission_received' => [
            'group' => 'Operacao',
            'label' => 'Envios de links publicos',
            'description' => 'Novo auto-cadastro de morador ou ocorrencia recebida por link/QR, aguardando moderacao.',
            'channels' => ['database', 'broadcast', 'mail'],
        ],
    ];

    public static function events(): array
    {
        return self::EVENTS;
    }

    public static function channels(): array
    {
        return self::CHANNELS;
    }

    public static function eventChannels(string $event): array
    {
        return self::EVENTS[$event]['channels'] ?? [];
    }
}
