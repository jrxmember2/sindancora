<?php

namespace App\Dashboard;

/**
 * Catálogo de tipos de widget suportados pelo dashboard modular.
 *
 * O tipo define qual componente visual o frontend usa para renderizar
 * (ver resources/js/Components/Dashboard/WidgetRenderer.tsx) e qual o
 * formato do payload retornado pelo resolver.
 */
final class WidgetType
{
    /** KPI simples: rótulo + valor (+ ícone). */
    public const KPI = 'kpi';

    /** KPI com variação percentual e mini série (sparkline). */
    public const KPI_TREND = 'kpi_trend';

    /** Gráfico de linha (evolução mensal). */
    public const LINE = 'line';

    /** Gráfico de barras (comparativos). */
    public const BAR = 'bar';

    /** Gráfico de área (crescimento acumulado). */
    public const AREA = 'area';

    /** Gráfico donut/pizza (distribuição por status). */
    public const DONUT = 'donut';

    /** Medidor/gauge (percentual crítico, sensores, metas). */
    public const GAUGE = 'gauge';

    /** Radial/progresso (metas, conclusão). */
    public const RADIAL = 'radial';

    /** Ranking em barras horizontais. */
    public const RANKING = 'ranking';

    /** Linha do tempo de eventos recentes. */
    public const TIMELINE = 'timeline';

    /** Alerta com lista de itens críticos. */
    public const ALERT = 'alert';

    /** Atalhos rápidos (links). */
    public const QUICK_ACTIONS = 'quick_actions';

    /** Tabela resumida com indicadores. */
    public const SUMMARY_TABLE = 'summary_table';

    /** Card de status (rótulo + estado visual). */
    public const STATUS_CARD = 'status_card';

    public const ALL = [
        self::KPI,
        self::KPI_TREND,
        self::LINE,
        self::BAR,
        self::AREA,
        self::DONUT,
        self::GAUGE,
        self::RADIAL,
        self::RANKING,
        self::TIMELINE,
        self::ALERT,
        self::QUICK_ACTIONS,
        self::SUMMARY_TABLE,
        self::STATUS_CARD,
    ];
}
