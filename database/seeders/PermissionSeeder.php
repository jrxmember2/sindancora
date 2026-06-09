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

            // Assembleias
            ['module' => 'assemblies', 'action' => 'create', 'description' => 'Criar assembleias'],
            ['module' => 'assemblies', 'action' => 'read',   'description' => 'Visualizar assembleias'],
            ['module' => 'assemblies', 'action' => 'update', 'description' => 'Editar/conduzir assembleias'],
            ['module' => 'assemblies', 'action' => 'delete', 'description' => 'Remover assembleias'],

            // IA
            ['module' => 'ai', 'action' => 'use', 'description' => 'Usar o assistente de IA'],

            // Configurações
            ['module' => 'settings', 'action' => 'read',     'description' => 'Visualizar configurações'],
            ['module' => 'settings', 'action' => 'update',   'description' => 'Editar configurações'],
            ['module' => 'settings', 'action' => 'payments', 'description' => 'Configurar integração de pagamento'],
            ['module' => 'settings', 'action' => 'whatsapp', 'description' => 'Configurar integração de WhatsApp'],
            ['module' => 'settings', 'action' => 'email',    'description' => 'Configurar SMTP/e-mail do tenant'],

            // API e Webhooks
            ['module' => 'api_keys',  'action' => 'manage', 'description' => 'Gerenciar chaves de API'],
            ['module' => 'webhooks',  'action' => 'manage', 'description' => 'Gerenciar webhooks'],

            // Auditoria
            ['module' => 'audit', 'action' => 'read', 'description' => 'Visualizar logs de auditoria'],

            // Portaria
            ['module' => 'gatehouse', 'action' => 'read',     'description' => 'Visualizar visitantes e acessos'],
            ['module' => 'gatehouse', 'action' => 'register', 'description' => 'Registrar entradas/saídas e validar QR'],
            ['module' => 'gatehouse', 'action' => 'manage',   'description' => 'Gerenciar autorizações de visitantes'],

            // Inbox WhatsApp
            ['module' => 'inbox', 'action' => 'use', 'description' => 'Atender conversas de WhatsApp'],

            // Setores de atendimento + chatbot
            ['module' => 'sectors', 'action' => 'manage', 'description' => 'Gerenciar setores e o chatbot de WhatsApp'],

            // Disparo em massa por WhatsApp
            ['module' => 'campaigns', 'action' => 'manage', 'description' => 'Criar e enviar campanhas de WhatsApp'],

            // Categorias customizáveis (ocorrências/documentos)
            ['module' => 'categories', 'action' => 'manage', 'description' => 'Gerenciar categorias customizáveis'],

            // Fornecedores/prestadores
            ['module' => 'suppliers', 'action' => 'create', 'description' => 'Cadastrar fornecedores'],
            ['module' => 'suppliers', 'action' => 'read',   'description' => 'Visualizar fornecedores'],
            ['module' => 'suppliers', 'action' => 'update', 'description' => 'Editar fornecedores e avaliar'],
            ['module' => 'suppliers', 'action' => 'delete', 'description' => 'Remover fornecedores e avaliações'],

            // Manutenção preventiva
            ['module' => 'maintenance', 'action' => 'create', 'description' => 'Criar planos de manutenção'],
            ['module' => 'maintenance', 'action' => 'read',   'description' => 'Visualizar manutenções'],
            ['module' => 'maintenance', 'action' => 'update', 'description' => 'Editar manutenções e registrar execuções'],
            ['module' => 'maintenance', 'action' => 'delete', 'description' => 'Remover manutenções e execuções'],

            // Orçamentos/Cotações
            ['module' => 'quotations', 'action' => 'create',  'description' => 'Criar orçamentos e cotações'],
            ['module' => 'quotations', 'action' => 'read',    'description' => 'Visualizar orçamentos e propostas'],
            ['module' => 'quotations', 'action' => 'update',  'description' => 'Editar orçamentos e propostas'],
            ['module' => 'quotations', 'action' => 'approve', 'description' => 'Aprovar ou reprovar propostas'],
            ['module' => 'quotations', 'action' => 'delete',  'description' => 'Remover orçamentos e propostas'],

            // Obras/Reformas
            ['module' => 'works', 'action' => 'create', 'description' => 'Criar obras e reformas'],
            ['module' => 'works', 'action' => 'read',   'description' => 'Visualizar obras e reformas'],
            ['module' => 'works', 'action' => 'update', 'description' => 'Editar obras, anexos, andamentos e contas vinculadas'],
            ['module' => 'works', 'action' => 'delete', 'description' => 'Remover obras e reformas'],

            // Cronograma consolidado
            // Funcionarios e ferias
            ['module' => 'employees', 'action' => 'create', 'description' => 'Cadastrar funcionarios'],
            ['module' => 'employees', 'action' => 'read',   'description' => 'Visualizar funcionarios e ferias'],
            ['module' => 'employees', 'action' => 'update', 'description' => 'Editar funcionarios e periodos de ferias'],
            ['module' => 'employees', 'action' => 'delete', 'description' => 'Remover funcionarios e periodos de ferias'],

            ['module' => 'schedule', 'action' => 'read', 'description' => 'Visualizar cronograma consolidado'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['name' => "{$perm['module']}:{$perm['action']}"],
                array_merge($perm, ['name' => "{$perm['module']}:{$perm['action']}"]),
            );
        }
    }
}
