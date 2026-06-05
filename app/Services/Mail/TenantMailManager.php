<?php

namespace App\Services\Mail;

use App\Models\TenantMailSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webklex\PHPIMAP\ClientManager;

/**
 * Aplica, em tempo de execução, o SMTP do tenant (e-mail white-label) para TODOS os envios —
 * tanto síncronos (web, via middleware) quanto em fila (via hooks de fila). Também guarda a
 * configuração ativa para, após o envio, copiar a mensagem na pasta Enviados via IMAP.
 *
 * Mantém a config global do .env quando o tenant não tem SMTP próprio configurado.
 */
class TenantMailManager
{
    private ?TenantMailSetting $current = null;
    private ?array $original = null;

    /** Configura o mailer com o SMTP do tenant (se houver e estiver utilizável); senão, mantém o global. */
    public function apply(?string $tenantId): void
    {
        $this->captureOriginal();
        $this->restore(); // parte sempre do global (importante no worker, entre jobs)

        if (! $tenantId) {
            return;
        }

        $setting = TenantMailSetting::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->first();
        if (! $setting || ! $setting->isUsable()) {
            return;
        }

        config([
            'mail.default' => 'tenant_smtp',
            'mail.mailers.tenant_smtp' => [
                'transport' => 'smtp',
                'host' => $setting->host,
                'port' => $setting->port,
                'encryption' => $setting->encryption ?: null,
                'username' => $setting->username,
                'password' => $setting->password,
                'timeout' => 15,
            ],
            'mail.from.address' => $setting->from_address,
            'mail.from.name' => $setting->from_name ?: config('mail.from.name'),
        ]);

        $this->forgetMailer(); // recria o transport com a nova config no próximo uso
        $this->current = $setting;
    }

    /** Volta para a config global de e-mail. */
    public function reset(): void
    {
        $this->restore();
        $this->current = null;
    }

    /** Memoriza a config global do .env na primeira vez, para poder restaurar depois. */
    private function captureOriginal(): void
    {
        $this->original ??= [
            'default' => config('mail.default'),
            'from' => config('mail.from'),
        ];
    }

    private function restore(): void
    {
        if ($this->original !== null) {
            config(['mail.default' => $this->original['default'], 'mail.from' => $this->original['from']]);
            $this->forgetMailer();
        }
    }

    public function current(): ?TenantMailSetting
    {
        return $this->current;
    }

    /** Copia uma mensagem (MIME bruto) para a pasta Enviados do tenant, se configurado. */
    public function copyToSent(string $rawMime): void
    {
        $setting = $this->current;
        if (! $setting || ! $setting->imapUsable()) {
            return;
        }

        try {
            $client = (new ClientManager())->make([
                'host' => $setting->imap_host,
                'port' => $setting->imap_port,
                'encryption' => $setting->imap_encryption ?: false,
                'validate_cert' => true,
                'username' => $setting->imap_username,
                'password' => $setting->imap_password,
                'protocol' => 'imap',
            ]);
            $client->connect();
            $client->getFolderByName($setting->sent_folder ?: 'Sent')->appendMessage($rawMime, ['\\Seen']);
            $client->disconnect();
        } catch (\Throwable $e) {
            Log::warning('Falha ao salvar e-mail na pasta Enviados (IMAP)', ['error' => $e->getMessage()]);
        }
    }

    private function forgetMailer(): void
    {
        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');
        Mail::clearResolvedInstances();
    }
}
