# E-mail por tenant (SMTP/IMAP) + convite por WhatsApp

## SMTP do tenant (white-label)

Cada tenant pode usar seu próprio servidor de e-mail. Configuração em **Configurações → E-mail (SMTP)**
(`/configuracoes/email`, permissão `settings:email`).

- **Dados** (`tenant_mail_settings`, migration `2026_06_16_000001`): SMTP (host, port, encryption,
  username, password [encrypted], from_address, from_name) + IMAP/Sent (save_to_sent, imap_host/port/
  encryption/username/password [encrypted], sent_folder). Model `TenantMailSetting` (`isUsable()`,
  `imapUsable()`). Senhas em branco no form mantêm as atuais.
- **Aplicação a TODOS os e-mails** via `App\Services\Mail\TenantMailManager` (singleton):
  - **Síncronos** (recuperação de senha, etc.): middleware `ApplyTenantMail` no grupo web aplica o
    SMTP do tenant (resolvido por host) no início do request.
  - **Em fila** (convite, comunicados, cobranças, notificação de inadimplência): hooks `Queue::before/`
    `after` no `AppServiceProvider` aplicam/limpam o SMTP do tenant do job. O tenant do job é lido de
    uma propriedade **plana `tenantId`** nos mailables/notificações (`ResidentInvitationMail`,
    `AnnouncementPublishedMail`, `ChargeIssuedMail`, `ChargeOverdue`) — necessária porque os modelos
    serializados não estão "hidratados" no momento da introspecção.
  - O manager **memoriza a config global** e a **restaura** entre jobs (evita um job sem tenant herdar
    o SMTP do job anterior no mesmo worker).
- **Cópia na pasta Enviados (IMAP):** listener de `MessageSent` chama `copyToSent()`, que usa
  `webklex/php-imap` para fazer `appendMessage` do MIME na pasta configurada. Best-effort (falha só
  loga). Requer `save_to_sent` + credenciais IMAP.
- **Teste:** botão "Enviar teste" (`settings.email.test`) manda um e-mail de verificação.
- Sem SMTP do tenant configurado/ativo, tudo continua pelo **mailer global do `.env`**.

## Convite ao portal por WhatsApp

`InvitationService::invite(Person, array $channels)` agora entrega o link de ativação por **e-mail e/ou
WhatsApp** (escolhido na ficha da Pessoa → "Acesso ao portal"). O **e-mail continua obrigatório** para
a conta (o login do portal é por e-mail); o WhatsApp é canal de entrega. O WhatsApp usa o telefone da
pessoa (dígitos + DDI 55) e uma **conexão conectada** (preferindo a que atende o condomínio da pessoa);
sem telefone/conexão, o serviço avisa (validação) antes de enviar qualquer coisa. A mensagem inclui o
link e o e-mail de login. `PersonController@invite` recebe `channels[]`.

## Deploy

`migrate --force` (tenant_mail_settings + colunas) + `db:seed --force` (permissão `settings:email`) +
`optimize:clear` + rebuild do front. Dependência nova: `webklex/php-imap` (composer). Para o Sent,
o servidor precisa de acesso de saída IMAP (porta 993/143). Worker de fila ativo.

## Observação

A cópia no Sent em envios **síncronos** (ex.: recuperação de senha) roda no próprio request e pode
adicionar 1–2s; em fila roda no worker.
