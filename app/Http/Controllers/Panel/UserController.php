<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly PlanLimitService $planLimitService) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $users = User::where('tenant_id', $tenant->id)
            ->with(['userRoles.role'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        $tenant = app('tenant');
        $this->planLimitService->check($tenant, 'users');

        return Inertia::render('Users/Create', [
            'roles' => Role::forTenant($tenant->id)->orderBy('display_name')->get(['id', 'name', 'display_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $this->planLimitService->check($tenant, 'users');

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => "required|email|unique:users,email,NULL,id,tenant_id,{$tenant->id}",
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role_id' => 'nullable|uuid|exists:roles,id',
            'status' => 'in:active,inactive',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => $data['status'] ?? 'active',
        ]);

        if (! empty($data['role_id'])) {
            $user->userRoles()->create(['role_id' => $data['role_id']]);
        }

        $this->planLimitService->increment($tenant, 'users');

        return redirect()->route('users.index')->with('success', "Usuário \"{$user->name}\" criado com sucesso.");
    }

    public function edit(User $user): Response
    {
        $tenant = app('tenant');
        abort_unless($user->tenant_id === $tenant->id, 403);

        $user->load('userRoles.role');

        return Inertia::render('Users/Edit', [
            'user' => $user,
            'roles' => Role::forTenant($tenant->id)->orderBy('display_name')->get(['id', 'name', 'display_name']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($user->tenant_id === $tenant->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => "required|email|unique:users,email,{$user->id},id,tenant_id,{$tenant->id}",
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'role_id' => 'nullable|uuid|exists:roles,id',
            'status' => 'in:active,inactive',
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            ...($data['password'] ? ['password' => Hash::make($data['password'])] : []),
        ]);

        if (isset($data['role_id'])) {
            $user->userRoles()->delete();
            $user->userRoles()->create(['role_id' => $data['role_id']]);
        }

        return redirect()->route('users.index')->with('success', "Usuário \"{$user->name}\" atualizado.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $tenant = app('tenant');
        abort_unless($user->tenant_id === $tenant->id, 403);
        abort_if($user->id === auth()->id(), 403, 'Você não pode excluir sua própria conta.');

        $user->delete();
        $this->planLimitService->decrement($tenant, 'users');

        return redirect()->route('users.index')->with('success', "Usuário excluído.");
    }
}
