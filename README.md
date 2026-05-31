# SindÂncora

SaaS multitenant de gestão condominial para síndicos, administradoras e condomínios.

## Requisitos para desenvolvimento local

- PHP 8.2+
- Composer 2+
- Node.js 22+
- Docker e Docker Compose

## Configuração inicial

```bash
# 1. Clonar o repositório
git clone <repo-url>
cd sindancora

# 2. Copiar o .env
cp .env.example .env

# 3. Gerar a chave da aplicação
php artisan key:generate

# 4. Instalar dependências PHP
composer install

# 5. Instalar dependências JS
npm install --legacy-peer-deps

# 6. Subir os containers de suporte (PostgreSQL, Redis, MinIO, Mailpit)
docker compose up postgres redis minio mailpit -d

# 7. Executar as migrations e seeds
php artisan migrate --seed

# 8. Iniciar o servidor de desenvolvimento
composer run dev
# OU em terminais separados:
# php artisan serve
# npm run dev
```

## Acessos locais

| Serviço | URL | Credenciais padrão |
|---|---|---|
| Aplicação | http://localhost:8000 | — |
| Mailpit (e-mails) | http://localhost:8025 | — |
| MinIO (storage) | http://localhost:9001 | sindancora / sindancora_secret |

**Super Admin inicial:**
- E-mail: `admin@sindancora.com.br`
- Senha: `SindAncora@2026!`

> Altere a senha imediatamente após o primeiro acesso.

## Deploy com Docker (produção)

```bash
# Build da imagem de produção
docker build -t sindancora:latest --target production .

# Rodar com docker-compose
docker compose up -d
```

Ver `docs/deploy/easypanel.md` para instruções de deploy no EasyPanel.

## Arquitetura

- **Backend:** Laravel 12 + PHP 8.4
- **Frontend:** Inertia.js + React + TailwindCSS
- **Banco:** PostgreSQL 16
- **Cache/Filas:** Redis 7
- **Storage:** Cloudflare R2 (prod) / MinIO (dev)
- **Deploy:** Docker + EasyPanel

## Estrutura do Projeto

```
app/
├── Http/Controllers/
│   ├── Api/V1/          # API REST pública (v1)
│   └── Panel/           # Controllers Inertia (painel)
├── Middleware/          # ResolveTenant, CheckPermission, etc.
├── Models/              # Eloquent Models
├── Services/            # TenantService, PlanLimitService, StorageService
└── Traits/              # BelongsToTenant, HasAuditLog, HasUuidKey

database/
├── migrations/          # Migrations ordenadas
└── seeders/             # Planos, permissões, roles, super admin

resources/js/
├── Layouts/             # AppLayout (painel admin)
├── Pages/               # Páginas Inertia por módulo
└── Components/          # Componentes React reutilizáveis

docs/
├── produto/             # Documentação técnica de produto
└── deploy/              # Guias de deploy
```

## Fases de Desenvolvimento

| Fase | Status | Descrição |
|---|---|---|
| Fase 1 — Base SaaS | ✅ Em andamento | Auth, Tenants, Planos, RBAC, Storage |
| Fase 2 — Cadastros | 🔜 | Condomínios, Blocos, Unidades, Pessoas |
| Fase 3 — Operação | 🔜 | Comunicados, Ocorrências, Reservas, Documentos |
| Fase 4 — Portal Morador | 🔜 | Portal web do condômino |
| Fase 5 — Financeiro | 🔜 | Cobranças, boleto, PIX |
| Fase 6 — Integrações | 🔜 | API pública, WhatsApp, IA |

## Comandos úteis

```bash
# Migrations
php artisan migrate
php artisan migrate:fresh --seed

# Testes
php artisan test

# Filas (desenvolvimento)
php artisan queue:work

# Build frontend
npm run build

# Formatar código PHP
./vendor/bin/pint
```

## Licença

Proprietário — todos os direitos reservados.
