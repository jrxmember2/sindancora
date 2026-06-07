<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Prestação de Contas</title>
    <style>
        * { font-family: DejaVu Sans, Arial, sans-serif; }
        body { color: #374151; font-size: 12px; margin: 0; }
        h1 { font-size: 18px; color: #111827; margin: 0 0 2px; }
        h2 { font-size: 13px; color: #1e40af; margin: 18px 0 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }
        .muted { color: #6b7280; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { text-align: left; padding: 5px 8px; font-size: 11px; }
        thead th { background: #f3f4f6; color: #6b7280; text-transform: uppercase; font-size: 10px; }
        tbody tr { border-bottom: 1px solid #f0f0f0; }
        .right { text-align: right; }
        .summary td { padding: 4px 8px; }
        .summary .label { color: #6b7280; }
        .summary .value { text-align: right; font-weight: bold; color: #111827; }
        .total-row { font-weight: bold; background: #f9fafb; }
    </style>
</head>
@php
    $fmt = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $s = $report['summary'];
@endphp
<body>
    <h1>Prestação de Contas</h1>
    <p class="muted">
        {{ $tenant->getBrandName() }} ·
        Período de {{ $from->format('d/m/Y') }} a {{ $to->format('d/m/Y') }} ·
        Emitido em {{ now()->format('d/m/Y H:i') }}
    </p>

    <h2>Resumo</h2>
    <table class="summary">
        <tr><td class="label">Total cobrado</td><td class="value">{{ $fmt($s['charged']) }}</td></tr>
        <tr><td class="label">Total recebido</td><td class="value">{{ $fmt($s['received']) }}</td></tr>
        <tr><td class="label">Em aberto</td><td class="value">{{ $fmt($s['open']) }}</td></tr>
        <tr><td class="label">Vencido (valor atualizado)</td><td class="value">{{ $fmt($s['overdue_total']) }}</td></tr>
        <tr><td class="label">Contas pagas</td><td class="value">{{ $fmt($s['expenses']) }}</td></tr>
        <tr class="total-row"><td class="label">Saldo (recebido − contas pagas)</td><td class="value">{{ $fmt($s['balance']) }}</td></tr>
    </table>

    <h2>Movimentação mensal</h2>
    <table>
        <thead>
            <tr><th>Mês</th><th class="right">Cobrado</th><th class="right">Recebido</th><th class="right">Contas pagas</th></tr>
        </thead>
        <tbody>
            @foreach ($report['months'] as $m)
                <tr>
                    <td>{{ $m['label'] }}</td>
                    <td class="right">{{ $fmt($m['charged']) }}</td>
                    <td class="right">{{ $fmt($m['received']) }}</td>
                    <td class="right">{{ $fmt($m['expenses']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Inadimplência por unidade ({{ $s['delinquent_units'] }} unidade(s) · {{ $fmt($s['overdue_total']) }})</h2>
    <table>
        <thead>
            <tr><th>Unidade</th><th>Responsável</th><th class="right">Cobranças</th><th class="right">Total devido</th></tr>
        </thead>
        <tbody>
            @forelse ($report['delinquents'] as $d)
                <tr>
                    <td>{{ $d['unit'] }}</td>
                    <td>{{ $d['person'] }}</td>
                    <td class="right">{{ $d['count'] }}</td>
                    <td class="right">{{ $fmt($d['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Nenhuma cobrança vencida no momento.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
