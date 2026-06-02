<?php

namespace App\Services;

use App\Mail\ResidentInvitationMail;
use App\Models\CondominiumManager;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function __construct(private readonly PlanLimitService $planLimitService) {}

    /**
     * Convida (ou reconvida) uma pessoa para o portal: garante o User vinculado com
     * status "invited", sincroniza os papéis e envia o e-mail com link de ativação.
     */
    public function invite(Person $person): User
    {
        if (empty($person->email)) {
            throw ValidationException::withMessages([
                'email' => 'A pessoa precisa ter um e-mail cadastrado para receber o convite.',
            ]);
        }

        $tenant = app('tenant');

        $user = $this->resolveUser($person, $tenant->id);

        $this->syncRoles($user, $person);

        // Token do broker de senha do Laravel — reutilizado para a ativação do convite.
        $token = Password::broker()->createToken($user);

        Mail::to($user->email)->send(new ResidentInvitationMail(
            userName: $user->name,
            tenantName: $tenant->getBrandName(),
            url: route('invitation.accept', ['token' => $token]).'?email='.urlencode($user->email),
        ));

        return $user;
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
