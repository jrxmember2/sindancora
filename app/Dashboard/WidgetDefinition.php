<?php

namespace App\Dashboard;

/**
 * Definição imutável de um widget registrado por um módulo.
 *
 * O registro acontece no DashboardServiceProvider. A visibilidade efetiva é
 * resolvida pelo WidgetRegistry::visibleFor() respeitando permissão e módulo do
 * plano, no mesmo critério usado no menu lateral (AppLayout.tsx).
 */
final class WidgetDefinition
{
    /**
     * @param  string  $key  Identificador único e estável (ex.: 'financial.open_amount').
     * @param  string|null  $module  Módulo de origem (ex.: 'financial'); null = geral, sempre visível.
     * @param  string  $name  Título exibido no card.
     * @param  string  $description  Descrição curta (tooltip / painel de personalização).
     * @param  string  $type  Um dos WidgetType::*.
     * @param  string  $size  Um dos WidgetSize::*.
     * @param  class-string  $resolver  Classe que implementa WidgetDataResolver.
     * @param  string|null  $permission  Permissão necessária (ex.: 'charges:read'); null = sem restrição.
     * @param  int  $order  Ordem padrão dentro do grid.
     * @param  bool  $lazy  Se true, o payload é buscado sob demanda (endpoint JSON) em vez de no carregamento inicial.
     * @param  bool  $active  Se false, o widget fica desabilitado globalmente.
     * @param  array<string, mixed>  $config  Configuração extra passada ao componente (cores, formato, etc.).
     */
    public function __construct(
        public readonly string $key,
        public readonly ?string $module,
        public readonly string $name,
        public readonly string $description,
        public readonly string $type,
        public readonly string $size,
        public readonly string $resolver,
        public readonly ?string $permission = null,
        public readonly int $order = 100,
        public readonly bool $lazy = false,
        public readonly bool $active = true,
        public readonly array $config = [],
    ) {}

    /**
     * Metadados enviados ao frontend (sem o payload de dados, que é resolvido à parte).
     *
     * @return array<string, mixed>
     */
    public function toMeta(): array
    {
        return [
            'key' => $this->key,
            'module' => $this->module ?? 'general',
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'size' => $this->size,
            'lazy' => $this->lazy,
            'order' => $this->order,
            'config' => (object) $this->config,
        ];
    }
}
