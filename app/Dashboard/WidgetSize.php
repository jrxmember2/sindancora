<?php

namespace App\Dashboard;

/**
 * Tamanhos padrão de widget. O token é interpretado pelo grid responsivo do
 * frontend (resources/js/Components/Dashboard/DashboardGrid.tsx), que converte
 * em col-span sobre uma grade de 4 colunas (xl).
 *
 * small  → 1 coluna
 * medium → 2 colunas
 * large  → 2 colunas (mais alto)
 * wide   → 3 colunas
 * full   → largura total (4 colunas)
 */
final class WidgetSize
{
    public const SMALL = 'small';
    public const MEDIUM = 'medium';
    public const LARGE = 'large';
    public const WIDE = 'wide';
    public const FULL = 'full';

    public const ALL = [
        self::SMALL,
        self::MEDIUM,
        self::LARGE,
        self::WIDE,
        self::FULL,
    ];
}
