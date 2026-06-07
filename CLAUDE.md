# SindÂncora — Instruções permanentes do projeto

Você está trabalhando no projeto SindÂncora.

O SindÂncora será um SaaS multitenant de gestão condominial, voltado para síndicos, administradoras e condomínios.

Este projeto NÃO deve copiar código, marca, textos, identidade visual, banco de dados, assets, telas idênticas ou informações privadas de concorrentes. Os arquivos em docs/mapeamento-concorrente servem apenas como referência funcional e estudo de mercado.

Objetivo:
Criar um sistema próprio, moderno, escalável, com arquitetura limpa, preparado para venda recorrente, white-label, limites comerciais por plano, controle de armazenamento por cliente e API pública futura.

Premissas obrigatórias:
- Sistema SaaS multitenant.
- Cada cliente/empresa/síndico é um tenant.
- Dados de tenants devem ser isolados.
- Todo registro operacional deve ter tenant_id direto ou indireto.
- Preparar desde o início limites por plano:
  - quantidade de condomínios;
  - quantidade de unidades;
  - quantidade de usuários;
  - quantidade de armazenamento contratado;
  - módulos habilitados;
  - consumo de API;
  - consumo de notificações.
- Preparar API versionada em /api/v1.
- Preparar deploy em VPS com EasyPanel via Docker.
- Usar português do Brasil na interface.
- Manter acentuação correta.
- Priorizar UI/UX clean, moderna, profissional e responsiva.
- Não quebrar funcionalidades existentes ao evoluir o projeto.
- Sempre propor plano antes de alterações grandes.
- Sempre criar ou atualizar documentação técnica quando implementar algo relevante.

Antes de dar continuidade ao desenvolvimento, leia `docs/produto/07-andamento-atual.md` para o
estado mais recente, validações, pendências de deploy e próximos passos sugeridos.

Stack preferencial:
- Backend: Laravel ou NestJS, conforme definido no início do projeto.
- Banco: PostgreSQL.
- Cache/Fila: Redis.
- Frontend: React, Next.js ou Inertia, com TailwindCSS.
- Storage: S3 compatível, preferencialmente Cloudflare R2 ou MinIO para ambiente local.
- Deploy: Docker + EasyPanel.
- API: REST versionada com documentação OpenAPI/Swagger.
