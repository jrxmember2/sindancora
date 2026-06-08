<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiLegalDocument;
use App\Models\Announcement;
use App\Models\Charge;
use App\Models\Condominium;
use App\Models\Document;
use App\Models\Occurrence;
use App\Models\Reservation;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Assistente de IA do síndico. Injeta contexto do tenant (dados estruturados + trechos de
 * documentos via RAG) na conversa com a Claude. Três capacidades: chat livre, análise de
 * inadimplência e rascunho de comunicado.
 */
class AssistantService
{
    public function __construct(
        private readonly AiProviderManager $ai,
        private readonly DocumentSearch $search,
        private readonly LegalDocumentSearch $legalSearch,
    ) {}

    public function configured(): bool
    {
        return $this->ai->configured();
    }

    /** Responde a uma mensagem na conversa, com histórico + contexto do tenant + RAG. */
    public function chat(AiConversation $conversation, Tenant $tenant, string $userText, ?Condominium $condominium = null): string
    {
        // Histórico (texto puro armazenado), em ordem cronológica.
        $messages = $conversation->messages()
            ->get(['role', 'content'])
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->all();

        // A última mensagem recebe o contexto (mantém o prefixo de sistema cacheável estável).
        $context = $this->buildContext($tenant, $userText, $condominium);
        $messages[] = ['role' => 'user', 'content' => $userText."\n\n".$context['content']];

        $answer = $this->ai->complete($this->systemPrompt($tenant), $messages, 2048);

        // Persiste a mensagem do usuário (sem o contexto) e a resposta.
        $conversation->messages()->create(['role' => 'user', 'content' => $userText]);
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $answer,
            'sources' => $context['sources'],
        ]);
        $conversation->touch();

        return $answer;
    }

    /** Diagnóstico da inadimplência atual com sugestões de ação. */
    public function analyzeDelinquency(Tenant $tenant, ?Condominium $condominium = null): string
    {
        $data = $this->delinquencyContext($tenant, $condominium);

        $system = $this->systemPrompt($tenant)."\n\nVocê está produzindo um diagnóstico de inadimplência. "
            ."Seja prático e direto: resuma a situação, aponte prioridades e sugira ações concretas (régua de cobrança, "
            ."contato, acordo). Não invente números além dos fornecidos.";

        return $this->ai->complete($system, [[
            'role' => 'user',
            'content' => "Analise a inadimplência do condomínio e proponha um plano de ação.\n\n".$data,
        ]], 3072);
    }

    /** Gera um rascunho de comunicado (título + corpo) a partir de um pedido em linguagem natural. */
    public function draftAnnouncement(Tenant $tenant, string $prompt, ?Condominium $condominium = null): array
    {
        $system = $this->systemPrompt($tenant)."\n\nVocê redige comunicados de condomínio claros e cordiais. "
            ."Responda ESTRITAMENTE em JSON válido no formato {\"title\": \"...\", \"body\": \"...\"}, sem texto fora do JSON. "
            ."O corpo pode usar quebras de linha (\\n) e deve ser pronto para publicar.";

        $raw = $this->ai->complete($system, [[
            'role' => 'user',
            'content' => trim(($condominium ? "Condominio selecionado: {$condominium->name}\n\n" : '')."Escreva um comunicado sobre: {$prompt}"),
        ]], 2048);

        $json = $this->extractJson($raw);

        return [
            'title' => (string) ($json['title'] ?? 'Comunicado'),
            'body' => (string) ($json['body'] ?? $raw),
        ];
    }

    /** Sugere uma resposta cordial para uma ocorrência, com base nos dados dela + RAG. */
    public function draftOccurrenceReply(Tenant $tenant, Occurrence $occurrence): string
    {
        $occurrence->loadMissing('condominium:id,name');

        $system = $this->systemPrompt($tenant)."\n\nVocê redige a resposta do síndico/administração a uma "
            ."ocorrência/chamado de morador. Seja claro, cordial e objetivo: reconheça o problema, informe o "
            ."encaminhamento/prazo quando fizer sentido e mantenha um tom profissional. Escreva apenas o texto da "
            ."resposta, pronto para enviar, sem saudações genéricas excessivas nem assinatura.";

        $lastComments = $occurrence->comments()
            ->where('type', 'comment')
            ->latest()
            ->limit(5)
            ->get(['body'])
            ->reverse()
            ->pluck('body')
            ->filter()
            ->implode("\n- ");

        $catLabel = Occurrence::CATEGORIES[$occurrence->category] ?? $occurrence->category;
        $statusLabel = Occurrence::STATUSES[$occurrence->status] ?? $occurrence->status;

        $details = "Ocorrência: {$occurrence->title}\nCategoria: {$catLabel}\nStatus: {$statusLabel}\n"
            ."Descrição: {$occurrence->description}";
        if ($lastComments !== '') {
            $details .= "\nÚltimos acompanhamentos:\n- {$lastComments}";
        }

        $context = $this->buildContext($tenant, $occurrence->title.' '.$occurrence->description, $occurrence->condominium);

        return $this->ai->complete($system, [[
            'role' => 'user',
            'content' => "Escreva uma resposta para esta ocorrência.\n\n{$details}\n\n{$context['content']}",
        ]], 1024);
    }

    // --- contexto ---

    private function systemPrompt(Tenant $tenant): string
    {
        // Estável (cacheável): não interpolar data/hora ou IDs voláteis aqui.
        return "Você é o assistente de gestão do SindÂncora, um sistema de administração de condomínios. "
            ."Ajuda o síndico/administrador com dúvidas sobre o condomínio, finanças, ocorrências, reservas e documentos. "
            ."Responda em português do Brasil, de forma objetiva e profissional. Use SOMENTE as informações do contexto "
            ."fornecido em cada pergunta (dados do condomínio e trechos de documentos); se algo não estiver no contexto, "
            ."diga que não tem essa informação em vez de inventar. Quando usar trechos de documentos ou base legal, cite "
            ."os marcadores das fontes fornecidas, como [D1] ou [L1]. A base legal global é apoio informativo e não "
            ."substitui análise jurídica profissional. Responda diretamente, sem expor seu raciocínio.";
    }

    /**
     * @return array{content:string,sources:array<int,array{label:string,type:string,id:string,title:string,category:string,scope:string}>}
     */
    private function buildContext(Tenant $tenant, string $query, ?Condominium $condominium = null): array
    {
        $sources = [];
        $parts = [
            '<contexto_do_condominio>',
            '<regras_de_resposta>',
            'Escopo da conversa: '.($condominium ? "somente o condominio {$condominium->name}." : 'tenant inteiro.'),
            'Ao usar documento do condominio, cite o marcador [D#]. Ao usar base legal global, cite o marcador [L#].',
            'Se o contexto nao trouxer a informacao solicitada, diga isso claramente.',
            '</regras_de_resposta>',
            $this->structuredSummary($tenant, $condominium),
        ];

        $docs = $this->search->search($tenant->id, $query, 5, $condominium?->id);
        if ($docs !== []) {
            $parts[] = "\n<trechos_de_documentos>";
            foreach ($docs as $index => $d) {
                $label = 'D'.($index + 1);
                $category = Document::CATEGORIES[$d['category']] ?? $d['category'];
                $parts[] = "[{$label}] Documento: {$d['title']} ({$category})\n".trim($d['content']);
                $sources[] = [
                    'label' => $label,
                    'type' => 'document',
                    'id' => $d['id'],
                    'title' => $d['title'],
                    'category' => $category,
                    'scope' => $condominium?->name ?? 'Tenant',
                ];
            }
            $parts[] = '</trechos_de_documentos>';
        }

        $legalDocs = $this->legalSearch->search($query, 5);
        if ($legalDocs !== []) {
            $parts[] = "\n<base_legal_global>";
            foreach ($legalDocs as $index => $d) {
                $label = 'L'.($index + 1);
                $category = AiLegalDocument::CATEGORIES[$d['category']] ?? $d['category'];
                $parts[] = "[{$label}] Base legal: {$d['title']} ({$category})\n".trim($d['content']);
                $sources[] = [
                    'label' => $label,
                    'type' => 'legal',
                    'id' => $d['id'],
                    'title' => $d['title'],
                    'category' => $category,
                    'scope' => 'Base legal global',
                ];
            }
            $parts[] = '</base_legal_global>';
        }

        $parts[] = '</contexto_do_condominio>';

        return [
            'content' => implode("\n", $parts),
            'sources' => $sources,
        ];
    }

    private function structuredSummary(Tenant $tenant, ?Condominium $condominium = null): string
    {
        $overdue = Charge::where('tenant_id', $tenant->id)
            ->when($condominium, fn ($q, $c) => $q->where('condominium_id', $c->id))
            ->overdue();
        $overdueCount = (clone $overdue)->count();
        $overdueTotal = (clone $overdue)->sum('amount');

        $openOccurrences = Occurrence::where('tenant_id', $tenant->id)
            ->when($condominium, fn ($q, $c) => $q->where('condominium_id', $c->id))
            ->whereIn('status', ['open', 'in_progress'])
            ->count();
        $pendingReservations = Reservation::where('tenant_id', $tenant->id)
            ->when($condominium, fn ($q, $c) => $q->where('condominium_id', $c->id))
            ->where('status', 'pending')
            ->count();

        $recentComms = Announcement::where('tenant_id', $tenant->id)
            ->when($condominium, fn ($q, $c) => $q->where('condominium_id', $c->id))
            ->where('status', 'published')
            ->latest('published_at')
            ->limit(5)
            ->pluck('title')
            ->all();

        $lines = [
            $condominium ? "Condominio selecionado: {$condominium->name}." : 'Condominio selecionado: tenant inteiro.',
            "Inadimplência: {$overdueCount} cobrança(s) vencida(s), total em aberto R$ ".number_format((float) $overdueTotal, 2, ',', '.').'.',
            "Ocorrências abertas/em andamento: {$openOccurrences}.",
            "Reservas pendentes de aprovação: {$pendingReservations}.",
        ];
        if ($recentComms !== []) {
            $lines[] = 'Comunicados recentes: '.implode('; ', $recentComms).'.';
        }

        return implode("\n", $lines);
    }

    private function delinquencyContext(Tenant $tenant, ?Condominium $condominium = null): string
    {
        $charges = Charge::where('tenant_id', $tenant->id)
            ->when($condominium, fn ($q, $c) => $q->where('condominium_id', $c->id))
            ->overdue()
            ->with(['unit:id,number', 'person:id,name'])
            ->orderBy('due_date')
            ->limit(50)
            ->get();

        if ($charges->isEmpty()) {
            return ($condominium ? "Condominio selecionado: {$condominium->name}.\n" : '').'Nao ha cobrancas vencidas no momento.';
        }

        $total = 0.0;
        $lines = [
            $condominium ? "Condominio selecionado: {$condominium->name}." : 'Condominio selecionado: tenant inteiro.',
            'Cobranças vencidas (até 50):',
        ];
        foreach ($charges as $c) {
            $amount = $c->currentAmount();
            $total += $amount;
            $unit = $c->unit?->number ?? '—';
            $person = $c->person?->name ?? 'morador';
            $due = $c->due_date?->format('d/m/Y');
            $lines[] = "- Unid. {$unit} ({$person}): {$c->description}, venceu {$due}, atualizado R$ ".number_format($amount, 2, ',', '.');
        }
        array_splice($lines, 2, 0, 'Total atualizado em aberto: R$ '.number_format($total, 2, ',', '.').'.');

        return implode("\n", $lines);
    }

    /** Extrai o primeiro objeto JSON de um texto (tolerante a cercas de código). */
    private function extractJson(string $raw): array
    {
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
