<?php

namespace App\Services;

use App\Mail\ResidentInvitationMail;
use App\Models\CondominiumManager;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Services\Whatsapp\EvolutionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
        private readonly EvolutionManager $evolution,
    ) {}

    /**
     * Convida (ou reconvida) uma pessoa para o portal: garante o User vinculado com status
     * "invited", sincroniza os papéis e envia o link de ativação pelos canais escolhidos
     * (e-mail e/ou WhatsApp). O login do portal é por e-mail, então o e-mail é sempre obrigatório
     * para a conta — o WhatsApp é apenas um canal de entrega do link.
     *
     * @param array<int,string> $channels  subconjunto de ['email','whatsapp']
     */
    public function invite(Person $person, array $channels = ['email']): User
    {
        if (empty($person->email)) {
            throw ValidationException::withMessages([
                'email' => 'A pessoa precisa ter um e-mail cadastrado (o login do portal é por e-mail).',
            ]);
        }

        $channels = array_values(array_intersect($channels, ['email', 'whatsapp'])) ?: ['email'];
        $tenant = app('tenant');

        // Pré-valida o WhatsApp ANTES de qualquer envio, para não enviar pela metade.
        $waNumber = null;
        $waConnection = null;
        if (in_array('whatsapp', $channels, true)) {
            $waNumber = $this->whatsappNumber($person);
            if (! $waNumber) {
                throw ValidationException::withMessages(['phone' => 'A pessoa não tem telefone para receber o convite por WhatsApp.']);
            }
            $waConnection = $this->resolveConnection($person, $tenant->id);
            if (! $waConnection) {
                throw ValidationException::withMessages(['whatsapp' => 'Nenhuma conexão de WhatsApp conectada para enviar o convite.']);
            }
        }

        $user = $this->resolveUser($person, $tenant->id);
        $this->syncRoles($user, $person);

        // Token do broker de senha do Laravel — reutilizado para a ativação do convite.
        $token = Password::broker()->createToken($user);
        $url = route('invitation.accept', ['token' => $token]).'?email='.urlencode($user->email);

        if (in_array('email', $channels, true)) {
            Mail::to($user->email)->send(new ResidentInvitationMail(
                userName: $user->name,
                tenantName: $tenant->getBrandName(),
                url: $url,
                tenantId: $tenant->id,
            ));
        }

        if (in_array('whatsapp', $channels, true)) {
            $message = "Olá, {$user->name}! 👋\nVocê foi convidado para o portal do {$tenant->getBrandName()}.\n"
                ."Crie sua senha aqui: {$url}\nSeu login é o e-mail: {$user->email}";
            $this->evolution->sendText($waConnection, $waNumber, $message);
        }

        return $user;
    }

    /** Número do WhatsApp da pessoa (dígitos com DDI; prefixa 55 quando BR sem DDI). */
    private function whatsappNumber(Person $person): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $person->phone);
        if (blank($digits)) {
            return null;
        }

        return strlen($digits) <= 11 ? '55'.$digits : $digits;
    }

    /** Conexão conectada do tenant, preferindo a que atende um condomínio da pessoa. */
    private function resolveConnection(Person $person, string $tenantId): ?WhatsappConnection
    {
        $connected = WhatsappConnection::where('tenant_id', $tenantId)
            ->where('status', 'connected')
            ->with('condominiums:id')
            ->get();

        if ($connected->isEmpty()) {
            return null;
        }

        $condoIds = $person->activeLinks()->with('unit:id,condominium_id')->get()
            ->pluck('unit.condominium_id')->filter()->unique();

        if ($condoIds->isNotEmpty()) {
            foreach ($connected as $connection) {
                if ($connection->condominiums->pluck('id')->intersect($condoIds)->isNotEmpty()) {
                    return $connection;
                }
            }
        }

        return $connected->first();
    }

    /**
     * Encontra o User já vinculado à pessoa ou cria um novo com status "invited".
     * Bloqueia reenvio para contas já ativas.
     */
    private function resolveUser(Person $person, string $tenantId): User
    {
        $user = $person->user;

        if ($user) {
            if ($user->status === 'active') {
                throw ValidationException::withMessages([
                    'email' => 'Esta pessoa já possui acesso ativo ao portal.',
                ]);
            }

            return $user;
        }

        // Evita colisão com um usuário administrativo que já use o mesmo e-mail no tenant.
        $existing = User::where('email', $person->email)->first();
        if ($existing) {
            throw ValidationException::withMessages([
                'email' => 'Já existe um usuário com este e-mail neste condomínio.',
            ]);
        }

        $this->planLimitService->check($tenant = app('tenant'), 'users');

        return DB::transaction(function () use ($person, $tenant) {
            $user = User::create([
                'tenant_id' => $tenant->id,
                'person_id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'phone' => $person->phone,
                'document' => $person->cpf,
                'password' => bcrypt(Str::random(40)), // senha aleatória; será definida na ativação
                'status' => 'invited',
            ]);

            $this->planLimitService->increment($tenant, 'users');

            return $user;
        });
    }

    /**
     * Define os papéis do usuário: morador (base) + papéis de gestão ativos
     * (síndico/subsíndico/conselheiro) derivados de CondominiumManager.
     * Atende ao item adiado da Fase 2 (role automático ao marcar como síndico).
     */
    private function syncRoles(User $user, Person $person): void
    {
        $names = ['morador'];

        $managerRoles = CondominiumManager::where('person_id', $person->id)
            ->whereNull('end_date')
            ->pluck('role')
            ->unique()
            ->all();

        $names = array_values(array_unique([...$names, ...$managerRoles]));

        $roleIds = Role::system()->whereIn('name', $names)->pluck('id');

        $user->userRoles()->whereIn('role_id', $roleIds)->delete();
        foreach ($roleIds as $roleId) {
            $user->userRoles()->create(['role_id' => $roleId]);
        }
    }
}
