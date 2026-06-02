<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class FinancialReportExport implements FromArray, WithTitle
{
    public function __construct(
        private readonly array $report,
        private readonly Carbon $from,
        private readonly Carbon $to,
    ) {}

    public function title(): string
    {
        return 'Relatório financeiro';
    }

    public function array(): array
    {
        $s = $this->report['summary'];
        $rows = [];

        $rows[] = ['Relatório Financeiro'];
        $rows[] = ['Período', $this->from->format('d/m/Y').' a '.$this->to->format('d/m/Y')];
        $rows[] = [];

        $rows[] = ['Resumo'];
        $rows[] = ['Total cobrado', $s['charged']];
        $rows[] = ['Total recebido', $s['received']];
        $rows[] = ['Em aberto', $s['open']];
        $rows[] = ['Vencido (atualizado)', $s['overdue_total']];
        $rows[] = ['Despesas', $s['expenses']];
        $rows[] = ['Saldo (recebido - despesas)', $s['balance']];
        $rows[] = [];

        $rows[] = ['Mês', 'Cobrado', 'Recebido', 'Despesas'];
        foreach ($this->report['months'] as $m) {
            $rows[] = [$m['label'], $m['charged'], $m['received'], $m['expenses']];
        }
        $rows[] = [];

        $rows[] = ['Inadimplência por unidade'];
        $rows[] = ['Unidade', 'Responsável', 'Cobranças', 'Total devido'];
        foreach ($this->report['delinquents'] as $d) {
            $rows[] = [$d['unit'], $d['person'], $d['count'], $d['total']];
        }

        return $rows;
    }
}
