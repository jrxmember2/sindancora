# Servidor Evolution API — deploy e configuração

A SindÂncora usa um **servidor Evolution API próprio (auto-hospedado)** para WhatsApp. A conexão
da plataforma com esse servidor é **global** e configurada pelo **super admin** em
`/admin/whatsapp` (não depende mais de variáveis de ambiente, embora elas sirvam de fallback).

## 1. Subir o Evolution no EasyPanel (Docker)

Criar um **App/Service** no EasyPanel a partir da imagem oficial:

- Imagem: `atendai/evolution-api:latest` (Evolution v2)
- Porta interna: `8080` (expor via domínio próprio, ex.: `https://evolution.seudominio.com`)
- **Volume persistente** para as sessões (ex.: montar `/evolution/instances`), senão os números
  desconectam a cada deploy.

A Evolution v2 precisa de **Postgres** e **Redis** (crie como serviços no EasyPanel e aponte via env).
Variáveis mínimas (confirme os nomes na versão escolhida — a doc oficial é a referência):

```
AUTHENTICATION_API_KEY=<gere-uma-chave-forte>     # esta é a "chave global"
SERVER_URL=https://evolution.seudominio.com
DATABASE_ENABLED=true
DATABASE_PROVIDER=postgresql
DATABASE_CONNECTION_URI=postgresql://user:pass@evolution_postgres:5432/evolution
CACHE_REDIS_ENABLED=true
CACHE_REDIS_URI=redis://evolution_redis:6379
DEL_INSTANCE=false
```

> Importante: **não reutilizar** o Postgres da aplicação SindÂncora para o Evolution — use um banco/serviço
> separado. A Evolution gerencia as próprias tabelas/sessões.

## 2. Conectar a plataforma ao servidor (super admin)

No painel do **super admin** → menu **WhatsApp** (`/admin/whatsapp`):
1. **URL base** = `SERVER_URL` do Evolution.
2. **Chave global** = `AUTHENTICATION_API_KEY` (campo write-only/encriptado; em branco mantém a atual).
3. **URL do webhook** = (opcional, Fase 2) `https://app.sindancora.com/api/webhooks/evolution`.
4. **Salvar** e **Testar conexão** (faz `GET /instance/fetchInstances`; grava `last_checked_at`).

A config fica na tabela `evolution_settings` (linha única, `api_key` encriptada). O `EvolutionManager`
lê dela primeiro e cai no `config/services.evolution` (env `EVOLUTION_BASE_URL`/`EVOLUTION_API_KEY`)
como fallback. **A chave global nunca é exposta aos tenants** — os síndicos só veem suas conexões.

## 3. Fluxo depois de configurado

Tenant (síndico) em **Configurações → WhatsApp** (`/configuracoes/whatsapp/conexoes`):
criar conexão (respeita a licença) → a plataforma chama `POST /instance/create` na Evolution → o
síndico lê o **QR Code** → o número conecta → aloca quais condomínios aquela conexão atende. Ver
`docs/tecnico/whatsapp-conexoes.md`.

## 4. Segurança e operação

- A chave global dá controle total do servidor Evolution → fica **só** no super admin (encriptada).
- Cada conexão = uma instância = um número/chip real que o síndico mantém online.
- Quedas de conexão: o síndico reabre a tela e relê o QR (status atualiza por polling; webhook na Fase 2).
- Backup do volume de sessões do Evolution evita re-pareamento em massa após manutenção.

## 5. Deploy desta funcionalidade (lado SindÂncora)

`migrate --force` (cria `evolution_settings`) + `optimize:clear`. Sem seed. As envs
`EVOLUTION_BASE_URL`/`EVOLUTION_API_KEY` são opcionais (fallback) — o recomendado é configurar pela
tela do super admin.
