# WhatsApp — Setores & Chatbot de triagem (Fase 3 da iniciativa)

Terceira fatia: o sistema passa a **triar** automaticamente as conversas recebidas. Ao primeiro
contato, um chatbot identifica o **condomínio** (quando a conexão atende mais de um) e o **setor**
desejado, encaminha a conversa para a equipe certa e respeita o **horário de atendimento** do setor.
A inbox passa a ser **escopada por setor**: cada atendente só vê as conversas dos seus setores.

## Conceitos

- **Setor** (`sectors`): entidade por condomínio (Portaria, Administração, etc.). Tem horário de
  atendimento, mensagem de fora de expediente, ordem e membros (atendentes). É o **destino** do
  roteamento e define **quem vê** a conversa na inbox.
- **Membro do setor** (`sector_user`): usuário de painel atribuído ao setor — escopo do atendente.
- **Config do chatbot por condomínio** (`whatsapp_bot_settings`): saudação, cabeçalho do menu de
  setores e mensagem de opção inválida (todas com padrões sensíveis quando em branco) + liga/desliga
  o menu de setores daquele condomínio.
- **Cabeçalho do menu de condomínio** (`whatsapp_connections.condominium_menu_header`): só usado
  quando a conexão atende **mais de um** condomínio.

## Máquina de estados do bot

`App\Services\WhatsappBotService` é acionado por `WaInboxService::ingestMessage` apenas para
mensagens **recebidas** (`direction = in`), quando `connection.bot_enabled` e a conversa ainda
**não está roteada**. Resolvido via container (`app(...)`) para evitar ciclo de dependência.

Estados em `wa_conversations.bot_state`: `new → awaiting_condominium → awaiting_sector → routed`.

1. **`new`**: se a conexão atende >1 condomínio → envia o menu de condomínio (`awaiting_condominium`);
   se atende exatamente 1 → entra direto no menu de setor; se nenhum → roteia para a inbox geral.
2. **`awaiting_condominium`**: interpreta o número escolhido, grava `condominium_id` e segue para o
   menu de setor. Opção inválida → reenvia o menu.
3. **menu de setor**: lista os setores **ativos** do condomínio. 0 setores → roteia geral; 1 setor →
   roteia direto; vários → envia menu (`awaiting_sector`).
4. **`awaiting_sector`**: interpreta o número, define `sector_id`. Fora do horário do setor → envia a
   `away_message`; senão envia uma confirmação. Em ambos os casos marca `routed`.

As respostas do bot são enviadas por `EvolutionManager::sendText` e **registradas na thread** como
saída com `sent_by = null` (= bot). O eco dessas mensagens volta pelo webhook e é deduplicado por
`wa_message_id` (igual à inbox da Fase 2). A escolha do usuário aceita "1", "1.", "opção 1" etc.
(extrai o primeiro número; índice 1-based).

## Horário de atendimento

`Sector::office_hours` (JSON): `{ "mon": {"enabled": true, "open": "08:00", "close": "18:00"}, ... }`
com chaves `mon..sun`. `Sector::isWithinOfficeHours()` usa o dia/hora atuais. **Sem nenhum dia
configurado**, o setor é tratado como **sempre disponível**.

## Escopo da inbox

`Panel\InboxController` agora distingue:
- **Gestor** (`sectors:manage` — admin/síndico): vê **todas** as conversas do tenant; filtros por
  condomínio, setor e status.
- **Atendente** (só `inbox:use`): vê **apenas** conversas cujo `sector_id` esteja entre seus setores.
  As ações (`send`/`assign`/`toggleStatus`) reforçam esse escopo (403 fora dele).

A página `Inbox/Index.tsx` ganhou filtro de setor, o setor na lista/cabeçalho e um estilo próprio
(verde, com selo 🤖) para as mensagens do chatbot.

## Permissões / papéis

Permissão nova **`sectors:manage`** (PermissionSeeder) concedida a **admin** e **síndico**
(RoleSeeder). Atendentes seguem com `inbox:use`.

## Telas (painel)

- **Setores** (`/setores`, `sectors.*`, `Settings/Sectors.tsx`): CRUD de setores por condomínio com
  horário semanal, mensagem de fora de expediente e atribuição de atendentes. Menu "Setores"
  (`Headset`, permissão `sectors:manage`).
- **Chatbot** (`/configuracoes/chatbot`, `chatbot.*`, `Settings/Chatbot.tsx`): por conexão
  (ligar/desligar o bot + cabeçalho do menu de condomínio quando multi) e por condomínio (saudação,
  cabeçalho do menu de setores, opção inválida + ligar/desligar o menu de setores). Menu "Chatbot"
  (`Bot`, permissão `sectors:manage`).

## Dados (migration `2026_06_11_000001_create_whatsapp_sectors_tables`)

- `sectors` (tenant_id, condominium_id, name, is_active, office_hours json, away_message, sort_order).
- `sector_user` (pivô sector_id+user_id, único).
- `whatsapp_bot_settings` (único por condominium_id; is_enabled + 3 mensagens).
- `whatsapp_connections` += `condominium_menu_header` (text nullable).
- `wa_conversations` += `sector_id` (FK nullOnDelete) + `bot_state` (default `new`).

Models `Sector` e `WhatsappBotSetting` (`$table` explícito, `BelongsToTenant`). `WaConversation`
ganhou `sector()`/`sector_id`/`bot_state`; `User::sectors()`; `Condominium::sectors()`/`botSetting()`.

## Deploy

`migrate --force` (cria sectors/sector_user/whatsapp_bot_settings + colunas) + `db:seed --force`
(permissão `sectors:manage`) + `optimize:clear`. Worker de fila ativo. Servidor Evolution no ar com
o webhook configurado no super admin (pré-requisito para testar a triagem ao vivo).

## Teste

1. Em **Setores**, criar setores do condomínio (ex.: Portaria, Administração), definir horários e
   atribuir atendentes.
2. Em **Chatbot**, garantir o bot ligado na conexão e ajustar as mensagens (opcional).
3. Enviar uma mensagem de um número externo para o WhatsApp da conexão → receber a saudação + menu →
   responder com o número do setor → ver a confirmação (ou a mensagem de fora de expediente).
4. Conferir na **inbox** que a conversa aparece roteada para o setor; um atendente daquele setor a vê,
   um de outro setor não.

## Próximas fases

4) mídia (StorageService) + respostas prontas; 5) tempo real (Reverb) + relatórios; (paralelo)
disparo em massa. Hardening pendente: autenticação/segredo no webhook da Evolution.
