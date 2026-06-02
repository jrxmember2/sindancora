<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $keys = ApiKey::where('tenant_id', $tenant->id)
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ApiKey $k) => [
                'id' => $k->id,
                'name' => $k->name,
                'key_prefix' => $k->key_prefix,
                'scopes' => $k->scopes,
                'expires_at' => $k->expires_at?->toDateString(),
                'last_used_at' => $k->last_used_at?->toIso8601String(),
                'revoked_at' => $k->revoked_at?->toIso8601String(),
                'active' => $k->isActive(),
                'created_by' => $k->creator?->name,
                'created_at' => $k->created_at?->toIso8601String(),
            ]);

        $logs = ApiRequestLog::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['method', 'path', 'status_code', 'duration_ms', 'created_at']);

        return Inertia::render('Settings/ApiKeys', [
            'keys' => $keys,
            'scopes' => collect(ApiKey::SCOPES)->map(fn ($desc, $scope) => ['value' => $scope, 'label' => $desc])->values(),
            'logs' => $logs,
            'newKey' => $request->session()->get('newApiKey'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'scopes' => 'required|array|min:1',
            'scopes.*' => 'required|string|in:'.implode(',', array_keys(ApiKey::SCOPES)),
            'expires_at' => 'nullable|date|after:today',
        ]);

        $plaintext = ApiKey::generatePlaintext();

        ApiKey::create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'key_hash' => ApiKey::hashKey($plaintext),
            'key_prefix' => substr($plaintext, 0, 12),
            'scopes' => $data['scopes'],
            'expires_at' => $data['expires_at'] ?? null,
            'created_by' => Auth::id(),
        ]);

        // A chave em claro é exibida uma única vez (flash); nunca é persistida.
        return redirect()->route('api-keys.index')
            ->with('newApiKey', $plaintext)
            ->with('success', 'API Key criada. Copie agora — ela não será exibida novamente.');
    }

    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        abort_unless($apiKey->tenant_id === app('tenant')->id, 403);

        $apiKey->forceFill(['revoked_at' => now()])->save();

        return redirect()->route('api-keys.index')->with('success', 'API Key revogada.');
    }
}
