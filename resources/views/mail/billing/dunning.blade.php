@component('mail::message')
# {{ $headline }}

Olá, {{ $tenantName }}.

@switch($stage)
@case('reminder')
Passando para lembrar que a fatura da sua assinatura do Sindâncora vence em breve. Para evitar qualquer interrupção, mantenha o pagamento em dia.
@break
@case('overdue_1')
Identificamos que a fatura da sua assinatura está em atraso. Regularize para continuar usando o sistema sem interrupções.
@break
@case('overdue_2')
Sua fatura segue em atraso. Caso não seja regularizada, **sua conta poderá ser bloqueada** em breve.
@break
@case('overdue_3')
Este é o **último aviso** antes do bloqueio automático da sua conta por inadimplência. Regularize o quanto antes.
@break
@case('suspended')
Sua conta foi **bloqueada** por falta de pagamento. Assim que a fatura for paga, o acesso é liberado automaticamente.
@break
@case('trust')
Como você é um bom pagador, **liberamos um prazo extra de cortesia**@if($graceUntil) até **{{ $graceUntil }}**@endif. Aproveite para regularizar a fatura dentro desse período.
@break
@endswitch

@if($invoiceUrl)
@component('mail::button', ['url' => $invoiceUrl])
Pagar / ver fatura
@endcomponent
@endif

Se o pagamento já foi feito, desconsidere este aviso.

Equipe Sindâncora
@endcomponent
