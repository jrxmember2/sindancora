<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WaConversation;
use App\Models\WaMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Relatórios operacionais do atendimento de WhatsApp (Fase 5): volume de conversas por período,
 * por setor/condomínio, abertas vs encerradas, mensagens recebidas/enviadas, tempo de 1ª resposta
 * e ranking de atendentes. Permissão sectors:manage.
 */
class WhatsappReportController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $from = $request->date('from') ? Carbon::parse($request->date('from'))->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $request->date('to') ? Carbon::parse($request->date('to'))->endOfDay() : now()->endOfDay();

        $conversations = WaConversation::where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$from, $to]);

        $totalConversations = (clone $conversations)->count();
        $openNow = WaConversation::where('tenant_id', $tenant->id)->where('status', 'open')->count();
        $closedInPeriod = (clone $conversations)->where('status', 'closed')->count();

        // Por setor (inclui "Sem setor").
        $bySector = (clone $conversations)
            ->leftJoin('sectors', 'sectors.id', '=', 'wa_conversations.sector_id')
            ->selectRaw('COALESCE(sectors.name, ?) as label, COUNT(*) as total', ['Sem setor'])
            ->groupBy('label')->orderByDesc('total')->get()
            ->map(fn ($r) => ['label' => $r->label, 'total' => (int) $r->total]);

        // Por condomínio (inclui "Sem condomínio").
        $byCondominium = (clone $conversations)
            ->leftJoin('condominiums', 'condominiums.id', '=', 'wa_conversations.condominium_id')
            ->selectRaw('COALESCE(condominiums.name, ?) as label, COUNT(*) as total', ['Sem condomínio'])
            ->groupBy('label')->orderByDesc('total')->get()
            ->map(fn ($r) => ['label' => $r->label, 'total' => (int) $r->total]);

        // Mensagens recebidas/enviadas + quebra bot/atendente no período.
        $messages = WaMessage::where('tenant_id', $tenant->id)->whereBetween('created_at', [$from, $to]);
        $inbound = (clone $messages)->where('direction', 'in')->count();
        $outbound = (clone $messages)->where('direction', 'out')->count();
        $botMessages = (clone $messages)->where('direction', 'out')->whereNull('sent_by')->count();
        $agentMessages = (clone $messages)->where('direction', 'out')->whereNotNull('sent_by')->count();

        // Ranking de atendentes (mensagens enviadas por humanos no período).
        $ranking = (clone $messages)
            ->where('direction', 'out')->whereNotNull('sent_by')
            ->selectRaw('sent_by, COUNT(*) as total')
            ->groupBy('sent_by')->orderByDesc('total')->limit(10)->get();
        $names = User::whereIn('id', $ranking->pluck('sent_by'))->pluck('name', 'id');
        $attendants = $ranking->map(fn ($r) => ['name' => $names[$r->sent_by] ?? '—', 'total' => (int) $r->total]);

        return Inertia::render('Inbox/Reports', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'kpis' => [
                'total_conversations' => $totalConversations,
                'open_now' => $openNow,
                'closed_in_period' => $closedInPeriod,
                'inbound' => $inbound,
                'outbound' => $outbound,
                'bot_messages' => $botMessages,
                'agent_messages' => $agentMessages,
                'avg_first_response_minutes' => $this->avgFirstResponseMinutes($tenant->id, $from, $to),
            ],
            'by_sector' => $bySector,
            'by_condominium' => $byCondominium,
            'attendants' => $attendants,
        ]);
    }

    /**
     * Tempo médio (minutos) entre a 1ª mensagem recebida e a 1ª resposta de um atendente (humano),
     * nas conversas criadas no período. Ignora conversas sem resposta humana.
     */
    private function avgFirstResponseMinutes(string $tenantId, Carbon $from, Carbon $to): ?float
    {
        $ids = WaConversation::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return null;
        }

        $diffs = [];
        foreach ($ids as $id) {
            $firstIn = WaMessage::where('conversation_id', $id)->where('direction', 'in')->min('created_at');
            if (! $firstIn) {
                continue;
            }
            $firstReply = WaMessage::where('conversation_id', $id)
                ->where('direction', 'out')->whereNotNull('sent_by')
                ->where('created_at', '>=', $firstIn)
                ->min('created_at');
            if (! $firstReply) {
                continue;
            }
            $diffs[] = Carbon::parse($firstIn)->diffInSeconds(Carbon::parse($firstReply));
        }

        if (empty($diffs)) {
            return null;
        }

        return round((array_sum($diffs) / count($diffs)) / 60, 1);
    }
}
