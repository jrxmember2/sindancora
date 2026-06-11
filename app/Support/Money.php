<?php

namespace App\Support;

/**
 * Formatação monetária em Real (pt-BR). Usado pelos resolvers do dashboard para
 * entregar o valor já formatado ao frontend, evitando divergência de locale.
 */
final class Money
{
    public static function brl(float|int $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    /** Formato compacto para eixos/sparklines (ex.: R$ 12,3 mil). */
    public static function compactBrl(float|int $value): string
    {
        $value = (float) $value;
        $abs = abs($value);

        if ($abs >= 1_000_000) {
            return 'R$ '.number_format($value / 1_000_000, 1, ',', '.').' mi';
        }

        if ($abs >= 1_000) {
            return 'R$ '.number_format($value / 1_000, 1, ',', '.').' mil';
        }

        return self::brl($value);
    }
}
