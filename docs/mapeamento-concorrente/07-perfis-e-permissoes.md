# 07 — Perfis e Permissões

> Gerado automaticamente em 31/05/2026, 11:51:45

---

## 1. Perfil Atual Analisado

| Campo | Valor |
|---|---|
| Usuário | sindicaandressamendes@gmail.com |
| Nome | ROYAL PALACE
Atrasado por 88 dias
Áreas de Lazer - Analisar um ralo mais moderno para a churrasqueira |
| Tipo | **Administrador** |
| Menus visíveis | 1 itens detectados |

---

## 2. Perfis do Sistema (Observados e Inferidos)

| Perfil | Descrição | Acesso | Status |
| --- | --- | --- | --- |
| **Super Admin** | Equipe do SaaS — acesso total ao sistema | Todos os tenants, todos os módulos, configurações globais | `[RECOMENDADO]` |
| **Administrador (Administradora)** | Funcionários da administradora — acesso ao tenant | Múltiplos condomínios, relatórios, financeiro, usuários, configurações do tenant | `[OBSERVADO]` |
| **Síndico** | Síndico eleito do condomínio | Condomínio específico, operação completa, sem acesso financeiro avançado | `[INFERIDO]` |
| **Subsíndico / Conselheiro** | Cargo de apoio à gestão | Acesso de leitura amplo, ações limitadas | `[INFERIDO]` |
| **Morador / Condômino** | Portal do morador | Dados próprios, comunicados, reservas, ocorrências, boletos próprios | `[INFERIDO]` |
| **Portaria** | Controle de acesso e registro de visitantes | Módulo de portaria apenas | `[RECOMENDADO]` |



---

## 3. Matriz Módulo × Perfil

> ✅ Acesso total | 👁 Somente leitura | ❌ Sem acesso

| Módulo | Super Admin | Administrador | Síndico | Morador |
| --- | --- | --- | --- | --- |
| Condomínios | ✅ | ✅ | 👁 | ❌ |
| Unidades | ✅ | ✅ | ✅ | 👁 |
| Moradores | ✅ | ✅ | ✅ | 👁 |
| Comunicados | ✅ | ✅ | ✅ | 👁 |
| Ocorrências | ✅ | ✅ | ✅ | ✅ |
| Reservas | ✅ | ✅ | ✅ | ✅ |
| Documentos | ✅ | ✅ | ✅ | 👁 |
| Financeiro | ✅ | ✅ | 👁 | 👁 |
| Boletos | ✅ | ✅ | ❌ | 👁 |
| Relatórios | ✅ | ✅ | 👁 | ❌ |
| Usuários | ✅ | ✅ | ❌ | ❌ |
| Configurações | ✅ | ✅ | ❌ | ❌ |
| Assembleias | ✅ | ✅ | ✅ | ✅ |



> **Nota:** Matriz baseada em padrões de mercado + inferências. Confirmar contra o sistema real.

---

## 4. Menus Detectados para Perfil Administrador

- **Trocar de empresa** → `—`

---

## 5. Estrutura RBAC Recomendada para o Novo SaaS

### 5.1 Hierarquia de Papéis

```
superadmin         → acesso global, sem restrição de tenant
  admin            → acesso total ao tenant
    sindico        → acesso operacional ao condomínio
      subsindico   → acesso de leitura + ações específicas
        porteiro   → módulo de portaria
        morador    → portal do morador
```

### 5.2 Permissões Granulares Sugeridas

| Módulo | Ação | Chave de Permissão |
| --- | --- | --- |
| condominiums | create | `condominiums:create` |
| units | create | `units:create` |
| persons | create | `persons:create` |
| announcements | create | `announcements:create` |
| occurrences | create | `occurrences:create` |
| reservations | create | `reservations:create` |
| documents | create | `documents:create` |
| charges | create | `charges:create` |
| reports | create | `reports:create` |
| users | create | `users:create` |
| condominiums | read | `condominiums:read` |
| units | read | `units:read` |
| persons | read | `persons:read` |
| announcements | read | `announcements:read` |
| occurrences | read | `occurrences:read` |
| reservations | read | `reservations:read` |
| documents | read | `documents:read` |
| charges | read | `charges:read` |
| reports | read | `reports:read` |
| users | read | `users:read` |
| condominiums | update | `condominiums:update` |
| units | update | `units:update` |
| persons | update | `persons:update` |
| announcements | update | `announcements:update` |
| occurrences | update | `occurrences:update` |
| reservations | update | `reservations:update` |
| documents | update | `documents:update` |
| charges | update | `charges:update` |
| reports | update | `reports:update` |
| users | update | `users:update` |



### 5.3 Implementação Recomendada

- `[RECOMENDADO]` Usar RBAC com herança de roles (role hierarchy)
- `[RECOMENDADO]` Permissão = module:action (ex: charges:create)
- `[RECOMENDADO]` Role custom por tenant: administrador pode criar sub-roles
- `[RECOMENDADO]` Auditoria: toda alteração de permissão registrada em audit_logs
- `[RECOMENDADO]` JWT com claims de roles para validação stateless

---

> **Próximo passo:** Execute `npm run phase:08` para mapear consultas e relatórios.
