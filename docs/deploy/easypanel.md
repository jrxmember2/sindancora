# Deploy do SindÂncora no EasyPanel

> Guia passo a passo para deploy em VPS com EasyPanel

---

## Pré-requisitos

- VPS com Ubuntu 22.04+ (mínimo 2 GB RAM, 20 GB disco)
- EasyPanel instalado ([easypanel.io](https://easypanel.io))
- Domínio configurado com DNS apontando para o servidor
- Docker instalado (EasyPanel gerencia automaticamente)

---

## 1. Configurar o Projeto no EasyPanel

### 1.1 Criar novo projeto

1. Acesse o painel do EasyPanel
2. Clique em **"New Project"**
3. Nome do projeto: `sindancora`

### 1.2 Criar os serviços

Dentro do projeto, crie os seguintes serviços:

#### Serviço: PostgreSQL

- **Type:** PostgreSQL
- **Name:** `sindancora-postgres`
- **Version:** 16
- **Database:** `sindancora`
- **Username:** `sindancora`
- **Password:** (gere uma senha forte)

#### Serviço: Redis

- **Type:** Redis
- **Name:** `sindancora-redis`
- **Password:** (gere uma senha forte)

#### Serviço: App (Aplicação)

- **Type:** App (Docker)
- **Name:** `sindancora-app`
- **Source:** GitHub Repository

---

## 2. Configurar o Repositório GitHub

No serviço da aplicação:

1. Conecte o repositório GitHub
2. **Branch:** `main`
3. **Dockerfile:** `Dockerfile` (raiz do projeto)
4. **Build target:** `production`

---

## 3. Variáveis de Ambiente

No serviço `sindancora-app`, configure as variáveis:

```env
APP_NAME=SindÂncora
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.sindancora.com.br

# Banco de Dados (usar os dados do serviço postgres)
DB_CONNECTION=pgsql
DB_HOST=sindancora-postgres
DB_PORT=5432
DB_DATABASE=sindancora
DB_USERNAME=sindancora
DB_PASSWORD=SUA_SENHA_POSTGRES

# Redis (usar os dados do serviço redis)
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=sindancora-redis
REDIS_PASSWORD=SUA_SENHA_REDIS
REDIS_PORT=6379

# E-mail (Resend ou SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=587
MAIL_USERNAME=resend
MAIL_PASSWORD=SUA_API_KEY_RESEND
MAIL_FROM_ADDRESS=noreply@sindancora.com.br
MAIL_FROM_NAME="SindÂncora"

# Storage Cloudflare R2
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=SUA_R2_ACCESS_KEY
AWS_SECRET_ACCESS_KEY=SEU_R2_SECRET_KEY
AWS_DEFAULT_REGION=auto
AWS_BUCKET=sindancora-prod
AWS_ENDPOINT=https://SEU_ACCOUNT_ID.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true

# Domínio SindÂncora
SINDANCORA_DOMAIN=sindancora.com.br

# Super Admin
SUPER_ADMIN_EMAIL=admin@sindancora.com.br
SUPER_ADMIN_PASSWORD=SENHA_SUPER_FORTE_AQUI
```

**IMPORTANTE:** Gere a `APP_KEY` rodando:
```bash
php artisan key:generate --show
```
E adicione o valor às variáveis do EasyPanel.

---

## 4. Domínios e SSL

No serviço `sindancora-app`:

1. Adicione o domínio principal: `app.sindancora.com.br`
2. Adicione wildcard para subdomínios: `*.sindancora.com.br`
3. EasyPanel configura SSL automaticamente via Let's Encrypt

**Configurar DNS (no Cloudflare ou registrador):**
```
A     app.sindancora.com.br    → IP_DO_SERVIDOR
CNAME *.sindancora.com.br      → app.sindancora.com.br
```

---

## 5. Comandos de Deploy

O EasyPanel executa o `Dockerfile` automaticamente. Após o primeiro deploy:

### 5.1 Migrations e Seeds (primeiro deploy)

No terminal do EasyPanel (ou via SSH):
```bash
docker exec -it sindancora-app php artisan migrate --seed --force
```

### 5.2 Migrations em atualizações subsequentes

```bash
docker exec -it sindancora-app php artisan migrate --force
```

### 5.3 Limpar caches após deploy

```bash
docker exec -it sindancora-app php artisan optimize
docker exec -it sindancora-app php artisan config:cache
docker exec -it sindancora-app php artisan route:cache
docker exec -it sindancora-app php artisan view:cache
```

---

## 6. Configurar Bucket MinIO/R2

### Para Cloudflare R2 (produção):

1. Acesse o painel Cloudflare → R2
2. Crie um bucket: `sindancora-prod`
3. Crie uma API Key com permissão de leitura/escrita
4. Configure CORS para o domínio da aplicação:

```json
[
  {
    "AllowedOrigins": ["https://app.sindancora.com.br", "https://*.sindancora.com.br"],
    "AllowedMethods": ["GET", "PUT", "POST", "DELETE"],
    "AllowedHeaders": ["*"],
    "MaxAgeSeconds": 3600
  }
]
```

---

## 7. Verificação Pós-Deploy

```bash
# Health check
curl https://app.sindancora.com.br/api/health

# Verificar status das migrations
docker exec -it sindancora-app php artisan migrate:status

# Verificar filas
docker exec -it sindancora-app php artisan queue:monitor

# Ver logs
docker logs sindancora-app --tail=100
```

---

## 8. Backups Automáticos

Configure via EasyPanel:

- **PostgreSQL:** backup diário automático (EasyPanel Pro)
- **Storage R2:** Cloudflare R2 replica automática entre regiões

Ou manualmente:
```bash
# Backup do banco
docker exec sindancora-postgres pg_dump -U sindancora sindancora > backup_$(date +%Y%m%d).sql

# Restaurar backup
docker exec -i sindancora-postgres psql -U sindancora sindancora < backup_20260101.sql
```

---

## 9. Monitoramento

- **Healthcheck:** `GET /up` (configurado no Laravel)
- **Logs:** EasyPanel exibe logs em tempo real
- **Erros:** configure Sentry no `.env`:
  ```env
  SENTRY_LARAVEL_DSN=https://SEU_DSN@sentry.io/projeto
  ```

---

## 10. Atualização da Aplicação

Para atualizar:

1. Faça push para o branch `main` no GitHub
2. EasyPanel detecta e faz rebuild automático (se configurado)
3. Ou clique em **"Deploy"** no painel do EasyPanel

O processo: build Docker → swap zero-downtime → migrations automáticas.

---

*Documento de deploy interno — SindÂncora v1.0*
