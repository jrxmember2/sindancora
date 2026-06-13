<?php

namespace App\Mail\Billing;

use App\Models\BillingPayment;
use App\Models\BillingSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mails da régua de cobrança SaaS. Um único Mailable parametrizado pelo estágio:
 * reminder (D-3), overdue_1/2/3, suspended (bloqueio) e trust (cortesia por confiança).
 */
class BillingDunningMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @var array<string,array{subject:string,headline:string}> */
    private const STAGES = [
        'reminder' => ['subject' => 'Sua fatura do Sindâncora vence em breve', 'headline' => 'Lembrete de vencimento'],
        'overdue_1' => ['subject' => 'Fatura do Sindâncora em atraso', 'headline' => 'Pagamento em atraso'],
        'overdue_2' => ['subject' => 'Atenção: fatura em atraso — risco de bloqueio', 'headline' => 'Atraso com aviso de bloqueio'],
        'overdue_3' => ['subject' => 'Último aviso antes do bloqueio da sua conta', 'headline' => 'Último aviso'],
        'suspended' => ['subject' => 'Sua conta do Sindâncora foi bloqueada', 'headline' => 'Conta bloqueada'],
        'trust' => ['subject' => 'Liberamos um prazo extra para você (cortesia)', 'headline' => 'Carência de cortesia'],
    ];

    public function __construct(public string $subscriptionId, public string $stage) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (self::STAGES[$this->stage] ?? self::STAGES['overdue_1'])['subject']);
    }

    public function content(): Content
    {
        $subscription = BillingSubscription::with('tenant', 'plan')->find($this->subscriptionId);

        $invoiceUrl = BillingPayment::where('billing_subscription_id', $this->subscriptionId)
            ->whereNotIn('status', BillingPayment::PAID_STATUSES)
            ->orderByDesc('due_date')
            ->value('invoice_url');

        return new Content(markdown: 'mail.billing.dunning', with: [
            'stage' => $this->stage,
            'headline' => (self::STAGES[$this->stage] ?? self::STAGES['overdue_1'])['headline'],
            'subscription' => $subscription,
            'tenantName' => $subscription?->tenant?->name ?? 'cliente',
            'graceUntil' => $subscription?->grace_until?->format('d/m/Y'),
            'invoiceUrl' => $invoiceUrl,
        ]);
    }
}
