import { EmptyWidgetState, ErrorWidgetState } from './WidgetStates';
import {
    KpiWidget, KpiTrendWidget, LineChartWidget, AreaChartWidget, BarChartWidget,
    DonutChartWidget, GaugeWidget, RadialWidget, RankingWidget, ActivityTimelineWidget,
    AlertWidget, QuickActionsWidget, SummaryTableWidget, StatusCardWidget, SeeAllLink,
} from './WidgetRenderers';
import type { WidgetMeta, WidgetPayload } from './types';

/** Mapeia o tipo do widget para o componente visual e trata empty/error central. */
export default function WidgetRenderer({
    meta,
    payload,
    onRetry,
}: {
    meta: WidgetMeta;
    payload: WidgetPayload;
    onRetry?: () => void;
}) {
    if (payload.error) {
        return <ErrorWidgetState message={payload.message} onRetry={onRetry} />;
    }

    if (payload.empty) {
        return <EmptyWidgetState text={(payload.emptyText as string) ?? (payload.text as string)} />;
    }

    switch (meta.type) {
        case 'kpi':
            return <KpiWidget p={payload} />;
        case 'kpi_trend':
            return <KpiTrendWidget p={payload} />;
        case 'line':
            return <LineChartWidget p={payload} />;
        case 'area':
            return <AreaChartWidget p={payload} />;
        case 'bar':
            return <BarChartWidget p={payload} />;
        case 'donut':
            return <DonutChartWidget p={payload} />;
        case 'gauge':
            return <GaugeWidget p={payload} />;
        case 'radial':
            return <RadialWidget p={payload} />;
        case 'ranking':
            return <RankingWidget p={payload} />;
        case 'timeline':
            return (
                <>
                    <ActivityTimelineWidget p={payload} />
                    <SeeAllLink href={payload.href as string} />
                </>
            );
        case 'alert':
            return (
                <>
                    <AlertWidget p={payload} />
                    <SeeAllLink href={payload.href as string} />
                </>
            );
        case 'quick_actions':
            return <QuickActionsWidget p={payload} />;
        case 'summary_table':
            return (
                <>
                    <SummaryTableWidget p={payload} />
                    <SeeAllLink href={payload.href as string} />
                </>
            );
        case 'status_card':
            return <StatusCardWidget p={payload} />;
        default:
            return <EmptyWidgetState text="Tipo de widget não suportado." />;
    }
}
