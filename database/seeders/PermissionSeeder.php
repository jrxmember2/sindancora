<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Condomínios
            ['module' => 'condominiums', 'action' => 'create', 'description' => 'Criar condomínios'],
            ['module' => 'condominiums', 'action' => 'read',   'description' => 'Visualizar condomínios'],
            ['module' => 'condominiums', 'action' => 'update', 'description' => 'Editar condomínios'],
            ['module' => 'condominiums', 'action' => 'delete', 'description' => 'Arquivar condomínios'],

            // Unidades
            ['module' => 'units', 'action' => 'create', 'description' => 'Criar unidades'],
            ['module' => 'units', 'action' => 'read',   'description' => 'Visualizar unidades'],
            ['module' => 'units', 'action' => 'update', 'description' => 'Editar unidades'],
            ['module' => 'units', 'action' => 'delete', 'description' => 'Remover unidades'],
            ['module' => 'units', 'action' => 'import', 'description' => 'Importar unidades via CSV'],

            // Pessoas
            ['module' => 'persons', 'action' => 'create', 'description' => 'Cadastrar pessoas'],
            ['module' => 'persons', 'action' => 'read',   'description' => 'Visualizar pessoas'],
            ['module' => 'persons', 'action' => 'update', 'description' => 'Editar pessoas'],
            ['module' => 'persons', 'action' => 'delete', 'description' => 'Remover pessoas'],
            ['module' => 'persons', 'action' => 'link',   'description' => 'Vincular pessoas a unidades'],

            // Comunicados
            ['module' => 'announcements', 'action' => 'create',  'description' => 'Criar comunicados'],
            ['module' => 'announcements', 'action' => 'read',    'description' => 'Visualizar comunicados'],
            ['module' => 'announcements', 'action' => 'update',  'description' => 'Editar comunicados'],
            ['module' => 'announcements', 'action' => 'delete',  'description' => 'Remover comunicados'],
            ['module' => 'announcements', 'action' => 'publish', 'description' => 'Publicar comunicados'],

            // Ocorrências
            ['module' => 'occurrences', 'action' => 'create', 'description' => 'Registrar ocorrências'],
            ['module' => 'occurrences', 'action' => 'read',   'description' => 'Visualizar ocorrências'],
            ['module' => 'occurrences', 'action' => 'update', 'description' => 'Atualizar ocorrências'],
            ['module' => 'occurrences', 'action' => 'close',  'description' => 'Encerrar ocorrências'],
            ['module' => 'occurrences', 'action' => 'delete', 'description' => 'Remover ocorrências'],

            // Reservas
            ['module' => 'reservations', 'action' => 'create',  'description' => 'Fazer reservas'],
            ['module' => 'reservations', 'action' => 'read',    'description' => 'Visualizar reservas'],
            ['module' => 'reservations', 'action' => 'approve', 'description' => 'Aprovar reservas'],
            ['module' => 'reservations', 'action' => 'reject',  'description' => 'Recusar reservas'],
            ['module' => 'reservations', 'action' => 'cancel',  'description' => 'Cancelar reservas'],

            // Documentos
            ['module' => 'documents', 'action' => 'upload',   'description' => 'Enviar documentos'],
            ['module' => 'documents', 'action' => 'read',     'description' => 'Visualizar documentos'],
            ['module' => 'documents', 'action' => 'download', 'description' => 'Baixar documentos'],
            ['module' => 'documents', 'action' => 'delete',   'description' => 'Remover documentos'],

            // Financeiro — Cobranças
            ['module' => 'charges', 'action' => 'create',   'description' => 'Criar cobranças'],
            ['module' => 'charges', 'action' => 'read',     'description' => 'Visualizar cobranças'],
            ['module' => 'charges', 'action' => 'update',   'description' => 'Editar cobranças'],
            ['module' => 'charges', 'action' => 'delete',   'description' => 'Cancelar/remover cobranças'],
            ['module' => 'charges', 'action' => 'mark_paid','description' => 'Registrar pagamento'],

            // Financeiro — Despesas
            ['module' => 'expenses', 'action' => 'create', 'description' => 'Lançar despesas'],
            ['module' => 'expenses', 'action' => 'read',   'description' => 'Visualizar despesas'],
            ['module' => 'expenses', 'action' => 'update', 'description' => 'Editar despesas'],
            ['module' => 'expenses', 'action' => 'delete', 'description' => 'Remover despesas'],

            // Relatórios
            ['module' => 'reports', 'action' => 'read',   'description' => 'Visualizar relatórios'],
            ['module' => 'reports', 'action' => 'export', 'description' => 'Exportar relatórios'],

            // Usuários
            ['module' => 'users', 'action' => 'create', 'description' => 'Criar usuários'],
            ['module' => 'users', 'action' => 'read',   'description' => 'Visualizar usuários'],
            ['module' => 'users', 'action' => 'update', 'description' => 'Editar usuários'],
            ['module' => 'users', 'action' => 'delete', 'description' => 'Desativar usuários'],
            ['module' => 'users', 'action' => 'manage', 'description' => 'Gerenciar roles de usuários'],

            // Configurações
            ['module' => 'settings', 'action' => 'read',   'description' => 'Visualizar configurações'],
            ['module' => 'settings', 'action' => 'update', 'description' => 'Editar configurações'],

            // API e Webhooks
            ['module' => 'api_keys',  'action' => 'manage', 'description' => 'Gerenciar chaves de API'],
            ['module' => 'webhooks',  'action' => 'manage', 'description' => 'Gerenciar webhooks'],

            // Auditoria
            ['module' => 'audit', 'action' => 'read', 'description' => 'Visualizar logs de auditoria'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['name' => "{$perm['module']}:{$perm['action']}"],
                array_merge($perm, ['name' => "{$perm['module']}:{$perm['action']}"]),
            );
        }
    }
}
