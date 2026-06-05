<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\TenantMailSetting;
use App\Services\Mail\TenantMailManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuração de SMTP (e IMAP/Sent) do tenant — e-mail white-label. Permissão settings:email.
 */
class MailSettingController extends Controller
{
    public function __construct(private readonly TenantMailManager $mail) {}

    public function edit(): Response
    {
        $tenant = app('tenant');
        $s = TenantMailSetting::where('tenant_id', $tenant->id)->first();

        return Inertia::render('Settings/Mail', [
            'settings' => [
                'enabled' => $s->enabled ?? false,
                'host' => $s->host ?? '',
                'port' => $s->port ?? 587,
                'encryption' => $s->encryption ?? 'tls',
                'username' => $s->username ?? '',
                'has_password' => filled($s?->password),
                'from_address' => $s->from_address ?? '',
                'from_name' => $s->from_name ?? $tenant->getBrandName(),
                'save_to_sent' => $s->save_to_sent ?? false,
                'imap_host' => $s->imap_host ?? '',
                'imap_port' => $s->imap_port ?? 993,
                'imap_encryption' => $s->imap_encryption ?? 'ssl',
                'imap_username' => $s->imap_username ?? '',
                'has_imap_password' => filled($s?->imap_password),
                'sent_folder' => $s->sent_folder ?? 'Sent',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'enabled' => 'boolean',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'encryption' => 'nullable|in:tls,ssl',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'from_address' => 'nullable|email|max:255',
            'from_name' => 'nullable|string|max:255',
            'save_to_sent' => 'boolean',
            'imap_host' => 'nullable|string|max:255',
            'imap_port' => 'nullable|integer|min:1|max:65535',
            'imap_encryption' => 'nullable|in:tls,ssl',
            'imap_username' => 'nullable|string|max:255',
            'imap_password' => 'nullable|string|max:255',
            'sent_folder' => 'nullable|string|max:120',
        ]);

        $setting = TenantMailSetting::firstOrNew(['tenant_id' => $tenant->id]);
        $setting->fill([
            'enabled' => $request->boolean('enabled'),
            'host' => $data['host'] ?? null,
            'port' => $data['port'] ?? 587,
            'encryption' => $data['encryption'] ?? null,
            'username' => $data['username'] ?? null,
            'from_address' => $data['from_address'] ?? null,
            'from_name' => $data['from_name'] ?? null,
            'save_to_sent' => $request->boolean('save_to_sent'),
            'imap_host' => $data['imap_host'] ?? null,
            'imap_port' => $data['imap_port'] ?? 993,
            'imap_encryption' => $data['imap_encryption'] ?? 'ssl',
            'imap_username' => $data['imap_username'] ?? null,
            'sent_folder' => $data['sent_folder'] ?? 'Sent',
        ]);
        // Só sobrescreve as senhas quando informadas (deixa em branco = mantém a atual).
        if (filled($data['password'] ?? null)) {
            $setting->password = $data['password'];
        }
        if (filled($data['imap_password'] ?? null)) {
            $setting->imap_password = $data['imap_password'];
        }
        $setting->save();

        return back()->with('success', 'Configurações de e-mail salvas.');
    }

    /** Envia um e-mail de teste para o endereço informado, usando o SMTP do tenant. */
    public function test(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $request->validate(['test_email' => 'required|email']);

        $setting = TenantMailSetting::where('tenant_id', $tenant->id)->first();
        if (! $setting || ! $setting->isUsable()) {
            return back()->with('error', 'Configure e ative o SMTP antes de testar.');
        }

        // Garante que o mailer use o SMTP do tenant (o middleware já aplicou, reforça aqui).
        $this->mail->apply($tenant->id);

        try {
            Mail::raw('Este é um e-mail de teste do SindÂncora. Se você recebeu, o SMTP está funcionando. ✅', function ($m) use ($data, $tenant) {
                $m->to($data['test_email'])->subject('Teste de e-mail — '.$tenant->getBrandName());
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Falha ao enviar: '.$e->getMessage());
        }

        return back()->with('success', 'E-mail de teste enviado para '.$data['test_email'].'.');
    }
}
