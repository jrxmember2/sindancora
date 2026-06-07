# Identidade do tenant, logos e limites de plano

## Limites de plano

`PlanLimitService` agora usa a base real para recursos permanentes:

- `condominiums`: contagem viva em `condominiums`;
- `units`: contagem viva em `units`;
- `users`: contagem viva em `users`;
- `residents`: usuários vinculados a pessoa ou role `morador`;
- `storage_mb`: soma viva de `storage_objects` ativos.

O contador `tenant_usage_counters` continua existindo para dashboard/histórico, mas é sincronizado a
partir da base real nesses recursos. Isso evita falha após downgrade de plano: se um tenant sai de
Enterprise para Starter com 1 condomínio, a próxima criação de condomínio é bloqueada porque o uso
real já está no limite.

`Admin\TenantController::changePlan()` também sincroniza contadores permanentes após trocar o plano.

## Logo do condomínio

O cadastro/edição de condomínio aceita upload de logo (`png`, `jpg`, `webp`, até 2 MB). O arquivo é
armazenado via `StorageService` em `storage_objects`:

- `entity_type = condominium_logo`;
- `entity_id = condominiums.id`;
- `condominium_id = condominiums.id`;
- o id do arquivo fica em `condominiums.settings.brand.logo_storage_object_id`.

`Condominium::logo_url` resolve a URL assinada e é exposto para o Inertia e para a API pública.
A listagem de condomínios mostra thumbnail quando houver logo.

## Dados do tenant

Nova tela: **Configurações → Dados do tenant** (`settings.tenant.*`), protegida por
`settings:update`.

Campos salvos:

- tipo PF/PJ;
- razão social/nome completo;
- nome fantasia;
- CPF/CNPJ;
- telefone/e-mail;
- endereço;
- cor principal;
- logo do tenant.

Os dados ficam em `tenants.settings.profile` e a marca em `tenants.settings.brand`. Campos base
(`tenants.name`, `document`, `email`, `phone`) são atualizados para manter compatibilidade.

Logo do tenant usa `storage_objects`:

- `entity_type = tenant_logo`;
- `entity_id = tenants.id`;
- id em `tenants.settings.brand.logo_storage_object_id`.

`Tenant::getReportProfile()` centraliza os dados para relatórios. O PDF financeiro já usa esse perfil
no cabeçalho, deixando Sindâncora apenas como assinatura de sistema.
